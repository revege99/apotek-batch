<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithLicenses;
use App\Models\LicenseActivationCode;
use App\Models\LicenseRenewalRequest;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LicenseController extends Controller
{
    use InteractsWithLicenses;

    /**
     * Display the license status page.
     */
    public function index(): View
    {
        $profile = $this->currentProfile();
        $status = $this->licenseStatusSummary($profile);
        $options = $this->renewalOptions();
        $history = LicenseRenewalRequest::query()
            ->with(['requestedBy:id,name', 'generatedBy:id,name', 'activationCode'])
            ->where('pharmacy_profile_id', $profile->id)
            ->latest('created_at')
            ->paginate(10);
        $qrisPayloads = $history->getCollection()
            ->mapWithKeys(function (LicenseRenewalRequest $request): array {
                return [
                    $request->id => [
                        'id' => $request->id,
                        'requested_at' => $request->created_at?->format('d-m-Y H:i') ?? '-',
                        'duration_label' => number_format($request->duration_days).' Hari',
                        'current_expires_at' => $request->current_expires_at?->format('d-m-Y H:i') ?? '-',
                        'projected_expires_at' => $request->projected_expires_at?->format('d-m-Y H:i') ?? '-',
                        'status_label' => match ($request->status) {
                            'pending' => 'Sedang Diproses Admin',
                            'code_generated' => 'Kode Siap Aktivasi',
                            'activated' => 'Lisensi Aktif',
                            default => 'Draft',
                        },
                    ],
                ];
            })
            ->all();

        $projectedExpiryDates = collect($options)
            ->mapWithKeys(fn (string $label, int $days): array => [
                $days => $this->projectedExpiry($profile, $days)->format('d-m-Y H:i'),
            ])
            ->all();

        return view('licenses.index', [
            ...$this->licensePageData('pengaturan.lisensi'),
            'licenseStatus' => $status,
            'licenseHistory' => $history,
            'renewalOptions' => $options,
            'projectedExpiryDates' => $projectedExpiryDates,
            'paymentSettings' => $this->paymentSettings(),
            'qrisPayloads' => $qrisPayloads,
            'autoOpenQrisRequestId' => (int) session('license_qris_request_id'),
        ]);
    }

    /**
     * Display the stored QRIS image without relying on a public storage symlink.
     */
    public function qrisImage(): BinaryFileResponse
    {
        $path = $this->paymentSettings()['qris_image_path'];

        abort_unless(is_string($path) && $path !== '' && Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path));
    }

    /**
     * Store a renewal request that will be processed by the superadmin.
     */
    public function storeRenewalRequest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'duration_days' => ['required', 'integer', 'in:'.implode(',', array_keys($this->renewalOptions()))],
            'notes' => ['nullable', 'string', 'max:500'],
            '_license_form' => ['nullable', 'string'],
        ]);

        $profile = $this->currentProfile();
        $durationDays = (int) $validated['duration_days'];

        $renewalRequest = LicenseRenewalRequest::query()->create([
            'pharmacy_profile_id' => $profile->id,
            'requested_by' => $request->user()->id,
            'duration_days' => $durationDays,
            'status' => 'pending',
            'notes' => $validated['notes'] ?: null,
            'current_expires_at' => $profile->app_license_expires_at?->toDateTimeString(),
            'projected_expires_at' => $this->projectedExpiry($profile, $durationDays)->toDateTimeString(),
        ]);

        return redirect()
            ->route('pengaturan.lisensi')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Pengajuan perpanjangan lisensi berhasil dikirim dan QRIS pembayaran sudah disiapkan.',
            ])
            ->with('license_qris_request_id', $renewalRequest->id);
    }

    /**
     * Activate a license from a generated code.
     */
    public function activate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'activation_code' => ['required', 'string', 'max:80'],
            '_license_form' => ['nullable', 'string'],
        ]);

        $profile = $this->currentProfile();
        $normalizedCode = $this->normalizeCode($validated['activation_code']);

        $code = LicenseActivationCode::query()
            ->with('renewalRequest')
            ->where('pharmacy_profile_id', $profile->id)
            ->where('code', $normalizedCode)
            ->where('status', 'available')
            ->first();

        if ($code === null) {
            return back()
                ->withErrors([
                    'activation_code' => 'Kode lisensi tidak ditemukan atau sudah pernah digunakan.',
                ])
                ->withInput($request->only('activation_code', '_license_form'));
        }

        if ($code->license_type === 'manual' && $code->fixed_expires_at !== null && $code->fixed_expires_at->lte(now())) {
            return back()
                ->withErrors([
                    'activation_code' => 'Kode lisensi manual ini sudah melewati batas waktu yang ditentukan.',
                ])
                ->withInput($request->only('activation_code', '_license_form'));
        }

        DB::transaction(function () use ($code, $profile, $request): void {
            $now = now();
            $currentExpiry = $profile->app_license_expires_at?->copy();
            $previousExpiry = $currentExpiry?->copy();

            if ($code->license_type === 'manual' && $code->fixed_expires_at !== null) {
                $appliedFrom = $now->copy();
                $newExpiry = $code->fixed_expires_at->copy();
            } elseif ($currentExpiry !== null && $currentExpiry->gt($now)) {
                $appliedFrom = $currentExpiry->copy()->addSecond();
                $newExpiry = $currentExpiry->copy()->addDays((int) $code->duration_days);
            } else {
                $appliedFrom = $now->copy();
                $newExpiry = $now->copy()->endOfDay()->addDays((int) $code->duration_days);
            }

            $profile->update([
                'app_license_status' => 'active',
                'app_license_expires_at' => $newExpiry->toDateTimeString(),
                'app_license_activated_at' => now(),
            ]);

            $code->update([
                'status' => 'used',
                'used_by' => $request->user()->id,
                'used_at' => now(),
                'previous_expires_at' => $previousExpiry?->toDateTimeString(),
                'applied_from' => $appliedFrom->toDateTimeString(),
                'applied_until' => $newExpiry->toDateTimeString(),
            ]);

            if ($code->renewalRequest !== null) {
                $code->renewalRequest->update([
                    'status' => 'activated',
                    'activated_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('pengaturan.lisensi')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Lisensi berhasil diaktivasi dan masa aktif sudah diperbarui.',
            ]);
    }

    /**
     * Normalize a license code before lookup.
     */
    private function normalizeCode(string $value): string
    {
        return Str::upper(trim(preg_replace('/\s+/', '', $value) ?? $value));
    }
}
