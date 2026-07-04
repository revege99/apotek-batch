<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithLicenses;
use App\Models\LicenseActivationCode;
use App\Models\LicenseRenewalRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LicenseManagementController extends Controller
{
    use InteractsWithLicenses;

    /**
     * Display the superadmin license management page.
     */
    public function index(): View
    {
        $profile = $this->currentProfile();

        $requests = LicenseRenewalRequest::query()
            ->with(['requestedBy:id,name,email', 'generatedBy:id,name', 'activationCode'])
            ->where('pharmacy_profile_id', $profile->id)
            ->latest('created_at')
            ->paginate(12, ['*'], 'requests_page');

        $recentCodes = LicenseActivationCode::query()
            ->with(['renewalRequest.requestedBy:id,name', 'generatedBy:id,name', 'usedBy:id,name'])
            ->where('pharmacy_profile_id', $profile->id)
            ->latest('created_at')
            ->limit(10)
            ->get();
        $revocableUsedCodeId = $this->revocableUsedCodeId($profile->id, $profile->app_license_expires_at?->format('Y-m-d H:i:s'));

        return view('licenses.manage', [
            ...$this->licensePageData('pengaturan.manajemen-lisensi'),
            'licenseStatus' => $this->licenseStatusSummary($profile),
            'licenseRequests' => $requests,
            'recentCodes' => $recentCodes,
            'revocableUsedCodeId' => $revocableUsedCodeId,
            'paymentSettings' => $this->paymentSettings(),
            'stats' => [
                'pending' => LicenseRenewalRequest::query()
                    ->where('pharmacy_profile_id', $profile->id)
                    ->where('status', 'pending')
                    ->count(),
                'ready_codes' => LicenseActivationCode::query()
                    ->where('pharmacy_profile_id', $profile->id)
                    ->where('status', 'available')
                    ->count(),
            ],
        ]);
    }

    /**
     * Generate a license code for a renewal request.
     */
    public function generateCode(LicenseRenewalRequest $licenseRenewalRequest, Request $request): RedirectResponse
    {
        $profile = $this->currentProfile();

        abort_unless($licenseRenewalRequest->pharmacy_profile_id === $profile->id, 404);

        $licenseRenewalRequest->loadMissing('activationCode');

        if ($licenseRenewalRequest->status === 'activated') {
            return back()->with('toast', [
                'type' => 'warning',
                'message' => 'Pengajuan ini sudah diaktivasi sebelumnya.',
            ]);
        }

        if ($licenseRenewalRequest->activationCode !== null && $licenseRenewalRequest->activationCode->status === 'available') {
            return back()->with('toast', [
                'type' => 'info',
                'message' => 'Kode lisensi untuk pengajuan ini sudah tersedia.',
            ]);
        }

        DB::transaction(function () use ($licenseRenewalRequest, $request, $profile): void {
            LicenseActivationCode::query()->create([
                'pharmacy_profile_id' => $profile->id,
                'license_renewal_request_id' => $licenseRenewalRequest->id,
                'generated_by' => $request->user()->id,
                'code' => $this->generateLicenseCode(),
                'license_type' => 'duration',
                'duration_days' => $licenseRenewalRequest->duration_days,
                'status' => 'available',
            ]);

            $licenseRenewalRequest->update([
                'status' => 'code_generated',
                'generated_by' => $request->user()->id,
                'generated_at' => now(),
            ]);
        });

        return redirect()
            ->route('pengaturan.manajemen-lisensi')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Kode lisensi berhasil dibuat dan siap diberikan ke admin.',
            ]);
    }

    /**
     * Create a manual license code without a renewal request.
     */
    public function storeManualCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'manual_expires_at' => ['required', 'date', 'after:now'],
            '_manual_license_form' => ['nullable', 'string'],
        ]);

        $profile = $this->currentProfile();
        $fixedExpiry = Carbon::parse($validated['manual_expires_at']);

        LicenseActivationCode::query()->create([
            'pharmacy_profile_id' => $profile->id,
            'generated_by' => $request->user()->id,
            'code' => $this->generateLicenseCode(),
            'license_type' => 'manual',
            'duration_days' => 0,
            'fixed_expires_at' => $fixedExpiry->toDateTimeString(),
            'status' => 'available',
        ]);

        return redirect()
            ->route('pengaturan.manajemen-lisensi')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Kode lisensi manual berhasil dibuat tanpa pengajuan user.',
            ]);
    }

    /**
     * Cancel a generated license code and restore the request to its previous state.
     */
    public function cancelGeneratedCode(LicenseRenewalRequest $licenseRenewalRequest): RedirectResponse
    {
        $profile = $this->currentProfile();

        abort_unless($licenseRenewalRequest->pharmacy_profile_id === $profile->id, 404);

        $licenseRenewalRequest->loadMissing('activationCode');
        $activationCode = $licenseRenewalRequest->activationCode;

        if ($licenseRenewalRequest->status !== 'code_generated' || $activationCode === null) {
            return back()->with('toast', [
                'type' => 'warning',
                'message' => 'Kode lisensi ini tidak bisa dibatalkan karena belum pernah dibuat.',
            ]);
        }

        if ($activationCode->status !== 'available') {
            return back()->with('toast', [
                'type' => 'warning',
                'message' => 'Kode lisensi ini sudah digunakan sehingga tidak bisa dibatalkan.',
            ]);
        }

        DB::transaction(function () use ($licenseRenewalRequest, $activationCode): void {
            $activationCode->delete();

            $licenseRenewalRequest->update([
                'status' => 'pending',
                'generated_by' => null,
                'generated_at' => null,
            ]);
        });

        return redirect()
            ->route('pengaturan.manajemen-lisensi')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Generate kode lisensi berhasil dibatalkan dan pengajuan dikembalikan ke status sebelumnya.',
            ]);
    }

    /**
     * Delete an available code or revoke the latest activated license safely.
     */
    public function destroyCode(LicenseActivationCode $licenseActivationCode): RedirectResponse
    {
        $profile = $this->currentProfile();

        abort_unless($licenseActivationCode->pharmacy_profile_id === $profile->id, 404);

        $licenseActivationCode->loadMissing('renewalRequest');

        if ($licenseActivationCode->status === 'available') {
            DB::transaction(function () use ($licenseActivationCode): void {
                $renewalRequest = $licenseActivationCode->renewalRequest;

                $licenseActivationCode->delete();

                if ($renewalRequest !== null) {
                    $renewalRequest->update([
                        'status' => 'pending',
                        'generated_by' => null,
                        'generated_at' => null,
                        'activated_at' => null,
                    ]);
                }
            });

            $message = $licenseActivationCode->renewalRequest === null
                ? 'Kode lisensi manual berhasil dihapus.'
                : 'Kode lisensi berhasil dihapus dan pengajuannya dikembalikan ke status menunggu.';

            return redirect()
                ->route('pengaturan.manajemen-lisensi')
                ->with('toast', [
                    'type' => 'success',
                    'message' => $message,
                ]);
        }

        if ($licenseActivationCode->status !== 'used') {
            return back()->with('toast', [
                'type' => 'warning',
                'message' => 'Lisensi ini tidak bisa dibatalkan dari halaman ini.',
            ]);
        }

        $renewalRequest = $licenseActivationCode->renewalRequest;

        if (! $this->canRevokeActivatedCode($licenseActivationCode, $profile->id, $profile->app_license_expires_at?->format('Y-m-d H:i:s'))) {
            return back()->with('toast', [
                'type' => 'warning',
                'message' => 'Lisensi ini tidak bisa dibatalkan karena bukan aktivasi terakhir yang sedang berlaku.',
            ]);
        }

        DB::transaction(function () use ($profile, $licenseActivationCode, $renewalRequest): void {
            $previousExpiry = $licenseActivationCode->previous_expires_at?->copy()
                ?? $renewalRequest?->current_expires_at?->copy();
            $now = now();

            $profile->update([
                'app_license_status' => $previousExpiry === null
                    ? 'inactive'
                    : ($previousExpiry->gt($now) ? 'active' : 'expired'),
                'app_license_expires_at' => $previousExpiry?->toDateTimeString(),
                'app_license_activated_at' => null,
            ]);

            $licenseActivationCode->delete();

            if ($renewalRequest !== null) {
                $renewalRequest->update([
                    'status' => 'pending',
                    'generated_by' => null,
                    'generated_at' => null,
                    'activated_at' => null,
                ]);
            }
        });

        return redirect()
            ->route('pengaturan.manajemen-lisensi')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Lisensi berhasil dibatalkan dan masa berlaku dikembalikan ke kondisi sebelumnya.',
            ]);
    }

    /**
     * Update QRIS payment settings used on the license page.
     */
    public function updatePaymentSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'qris_image' => ['nullable', 'image', 'max:2048'],
            'receiver_name' => ['required', 'string', 'max:120'],
            'payment_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $currentPath = $this->paymentSettings()['qris_image_path'];
        $storedPath = $currentPath;

        if ($request->hasFile('qris_image')) {
            if (is_string($currentPath) && $currentPath !== '') {
                Storage::disk('public')->delete($currentPath);
            }

            $storedPath = $request->file('qris_image')->store('license-qris', 'public');
        }

        $this->upsertSetting(
            'license',
            'license.qris_image_path',
            'Gambar QRIS Lisensi',
            $storedPath,
            'image',
            'Gambar QRIS yang tampil pada halaman lisensi.'
        );
        $this->upsertSetting(
            'license',
            'license.qris_receiver_name',
            'Penerima QRIS Lisensi',
            $validated['receiver_name'],
            'string',
            'Nama penerima pembayaran lisensi via QRIS.'
        );
        $this->upsertSetting(
            'license',
            'license.qris_notes',
            'Catatan QRIS Lisensi',
            $validated['payment_notes'] ?: null,
            'text',
            'Catatan yang tampil pada halaman lisensi untuk pembayaran.'
        );

        return redirect()
            ->route('pengaturan.manajemen-lisensi')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Pengaturan QRIS lisensi berhasil diperbarui.',
            ]);
    }

    /**
     * Generate a unique license code.
     */
    private function generateLicenseCode(): string
    {
        do {
            $code = 'LIC-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (LicenseActivationCode::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * Get the latest used code that is still safe to revoke.
     */
    private function revocableUsedCodeId(int $profileId, ?string $currentExpiryDateTime): ?int
    {
        if ($currentExpiryDateTime === null) {
            return null;
        }

        return LicenseActivationCode::query()
            ->where('pharmacy_profile_id', $profileId)
            ->where('status', 'used')
            ->where('applied_until', $currentExpiryDateTime)
            ->latest('used_at')
            ->value('id');
    }

    /**
     * Determine whether a used code is the latest active license layer.
     */
    private function canRevokeActivatedCode(LicenseActivationCode $licenseActivationCode, int $profileId, ?string $currentExpiryDateTime): bool
    {
        if ($currentExpiryDateTime === null || $licenseActivationCode->applied_until === null) {
            return false;
        }

        if ($licenseActivationCode->applied_until->format('Y-m-d H:i:s') !== $currentExpiryDateTime) {
            return false;
        }

        return $this->revocableUsedCodeId($profileId, $currentExpiryDateTime) === $licenseActivationCode->id;
    }
}
