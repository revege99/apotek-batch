<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AppSetting;
use App\Models\PharmacyProfile;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

trait InteractsWithLicenses
{
    /**
     * Renewal duration options shown on the page.
     *
     * @return array<int, string>
     */
    protected function renewalOptions(): array
    {
        return [
            30 => '1 Bulan',
            90 => '3 Bulan',
            180 => '6 Bulan',
            365 => '12 Bulan',
        ];
    }

    /**
     * Get the current pharmacy profile, creating a default one if needed.
     */
    protected function currentProfile(): PharmacyProfile
    {
        return PharmacyProfile::query()->active()->latest('id')->first()
            ?? PharmacyProfile::query()->latest('id')->first()
            ?? PharmacyProfile::query()->create([
                'name' => 'Apotik Baru',
                'owner_name' => 'Owner Apotik',
                'phone' => '081234567890',
                'email' => 'halo@apotikbaru.local',
                'city' => 'Medan',
                'province' => 'Sumatera Utara',
                'postal_code' => '20100',
                'license_number' => 'SIA belum diatur.',
                'address' => 'Alamat apotik belum diatur.',
                'invoice_footer' => 'Terima kasih telah berbelanja di Apotik Baru.',
                'app_license_status' => 'inactive',
                'app_license_expires_at' => null,
                'is_active' => true,
            ]);
    }

    /**
     * Build a status summary for the current application license.
     *
     * @return array<string, mixed>
     */
    protected function licenseStatusSummary(PharmacyProfile $profile): array
    {
        $now = now();
        $expiresAt = $profile->app_license_expires_at?->copy();
        $status = $profile->app_license_status ?: 'inactive';

        if ($expiresAt === null) {
            $status = 'inactive';
        }

        if ($expiresAt !== null && $expiresAt->lte($now)) {
            $status = 'expired';
        }

        $remainingDays = $expiresAt !== null && $expiresAt->gt($now)
            ? $now->diffInDays($expiresAt)
            : 0;

        return [
            'code' => $status,
            'label' => match ($status) {
                'active' => 'Aktif',
                'expired' => 'Tidak Aktif',
                default => 'Tidak Ada Lisensi',
            },
            'expires_at' => $expiresAt,
            'expires_at_label' => $expiresAt?->format('d-m-Y H:i') ?? '-',
            'remaining_days' => $remainingDays,
            'remaining_days_label' => $expiresAt === null
                ? '-'
                : ($remainingDays > 0 ? number_format($remainingDays).' Hari' : '0 Hari'),
        ];
    }

    /**
     * Calculate projected expiry from the current license state.
     */
    protected function projectedExpiry(PharmacyProfile $profile, int $durationDays): Carbon
    {
        $now = now();
        $currentExpiry = $profile->app_license_expires_at?->copy();

        if ($currentExpiry instanceof CarbonInterface && $currentExpiry->gt($now)) {
            return $currentExpiry->copy()->addDays($durationDays);
        }

        return $now->copy()->endOfDay()->addDays($durationDays);
    }

    /**
     * Read payment settings used on the license pages.
     *
     * @return array<string, ?string>
     */
    protected function paymentSettings(): array
    {
        return [
            'qris_image_path' => AppSetting::valueOf('license.qris_image_path'),
            'receiver_name' => AppSetting::valueOf('license.qris_receiver_name', 'QRIS Lisensi'),
            'notes' => AppSetting::valueOf(
                'license.qris_notes',
                'Scan QRIS ini untuk pembayaran lisensi, lalu ajukan perpanjangan dari halaman lisensi.'
            ),
        ];
    }

    /**
     * Upsert one app setting row.
     */
    protected function upsertSetting(
        string $group,
        string $key,
        string $label,
        ?string $value,
        string $type,
        ?string $notes = null,
    ): void {
        AppSetting::query()->updateOrCreate([
            'setting_key' => $key,
        ], [
            'setting_group' => $group,
            'label' => $label,
            'value' => $value,
            'type' => $type,
            'notes' => $notes,
        ]);
    }

    /**
     * Build page metadata from navigation config.
     *
     * @return array<string, mixed>
     */
    protected function licensePageData(string $routeName): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $item): bool => $item['label'] === 'Pengaturan');

        $siblings = $section['children'] ?? [];
        $page = collect($siblings)->firstWhere('route', $routeName);

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Pengaturan',
            'siblings' => $siblings,
        ];
    }
}
