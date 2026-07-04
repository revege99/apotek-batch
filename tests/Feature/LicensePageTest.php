<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\LicenseActivationCode;
use App\Models\LicenseRenewalRequest;
use App\Models\PharmacyProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LicensePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_license_page_shows_no_license_before_any_activation(): void
    {
        $user = User::factory()->create();
        $this->createProfileWithoutLicense();

        $this
            ->actingAs($user)
            ->get(route('pengaturan.lisensi'))
            ->assertOk()
            ->assertSee('Tidak Ada Lisensi')
            ->assertSee('-');
    }

    public function test_authenticated_user_can_view_license_page_and_submit_renewal_request(): void
    {
        $user = User::factory()->create();
        $profile = $this->createProfile('2026-12-31');

        $this
            ->actingAs($user)
            ->get(route('pengaturan.lisensi'))
            ->assertOk()
            ->assertSee('Status Lisensi')
            ->assertSee('Perpanjang Lisensi')
            ->assertDontSee('Scan QRIS ini untuk pembayaran sebelum pengajuan diproses oleh superadmin.');

        $projectedExpiry = Carbon::parse('2026-12-31')->addDays(90)->toDateString();

        $this
            ->actingAs($user)
            ->post(route('pengaturan.lisensi.renewal-request'), [
                'duration_days' => 90,
                'notes' => 'Konfirmasi pembayaran QRIS.',
                '_license_form' => 'renew',
            ])
            ->assertRedirect(route('pengaturan.lisensi'))
            ->assertSessionHas('toast')
            ->assertSessionHas('license_qris_request_id');

        $renewalRequest = LicenseRenewalRequest::query()->first();

        $this->assertNotNull($renewalRequest);
        $this->assertSame($profile->id, $renewalRequest->pharmacy_profile_id);
        $this->assertSame($user->id, $renewalRequest->requested_by);
        $this->assertSame(90, $renewalRequest->duration_days);
        $this->assertSame('pending', $renewalRequest->status);
        $this->assertSame($projectedExpiry, $renewalRequest->projected_expires_at?->toDateString());

        $this
            ->actingAs($user)
            ->get(route('pengaturan.lisensi'))
            ->assertOk()
            ->assertSee('Sedang Diproses Admin')
            ->assertSee('Lihat QRIS');
    }

    public function test_expired_license_page_marks_status_inactive_with_red_notice(): void
    {
        $user = User::factory()->create();
        $this->createProfile(now()->subDay()->toDateString());

        $this
            ->actingAs($user)
            ->get(route('pengaturan.lisensi'))
            ->assertOk()
            ->assertSee('Tidak Aktif')
            ->assertSee('Lisensi berakhir')
            ->assertSee('bg-rose-50');
    }

    public function test_license_page_marks_same_day_passed_expiry_as_inactive(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 4, 11, 39, 0, 'Asia/Jakarta'));

        try {
            $user = User::factory()->create();

            PharmacyProfile::query()->create([
                'name' => 'Apotik Uji',
                'license_number' => 'SIA-UJI',
                'app_license_status' => 'active',
                'app_license_expires_at' => '2026-06-04 11:37:00',
                'is_active' => true,
            ]);

            $this
                ->actingAs($user)
                ->get(route('pengaturan.lisensi'))
                ->assertOk()
                ->assertSee('Tidak Aktif')
                ->assertSee('04-06-2026 11:37');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_expired_license_blocks_transaction_entry_routes_and_redirects_to_license_page(): void
    {
        $user = User::factory()->create();
        $this->createProfile(now()->subDay()->toDateString());

        foreach ([
            'pembelian.input-faktur-pembelian',
            'pembelian.retur-pembelian',
            'pembelian.riwayat-retur-pembelian',
            'pembelian.realisasi-pengganti-retur',
            'pembelian.riwayat-realisasi-pengganti-retur',
            'penjualan.kasir-penjualan',
            'penjualan.retur-penjualan',
            'penjualan.riwayat-retur-penjualan',
        ] as $routeName) {
            $this
                ->actingAs($user)
                ->get(route($routeName))
                ->assertRedirect(route('pengaturan.lisensi'))
                ->assertSessionHas('toast', fn (array $toast): bool => ($toast['type'] ?? null) === 'error'
                    && ($toast['message'] ?? null) === 'Lisensi berakhir, silahkan lakukan perpanjangan lisensi di menu profile lisensi.');
        }
    }

    public function test_license_activation_uses_generated_code_and_extends_current_expiry(): void
    {
        $user = User::factory()->create();
        $profile = $this->createProfile('2026-12-31');
        $code = LicenseActivationCode::query()->create([
            'pharmacy_profile_id' => $profile->id,
            'code' => 'LIC-260604-ABC123',
            'duration_days' => 30,
            'status' => 'available',
        ]);

        $this
            ->actingAs($user)
            ->post(route('pengaturan.lisensi.activate'), [
                'activation_code' => 'LIC-260604-ABC123',
                '_license_form' => 'activate',
            ])
            ->assertRedirect(route('pengaturan.lisensi'))
            ->assertSessionHas('toast');

        $profile->refresh();
        $code->refresh();

        $this->assertSame('2027-01-30', $profile->app_license_expires_at?->toDateString());
        $this->assertSame('active', $profile->app_license_status);
        $this->assertSame('used', $code->status);
        $this->assertSame($user->id, $code->used_by);
    }

    public function test_superadmin_only_management_page_requires_role(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('pengaturan.manajemen-lisensi'))
            ->assertForbidden();

        $superadmin = $this->createSuperadmin();

        $this
            ->actingAs($superadmin)
            ->get(route('pengaturan.manajemen-lisensi'))
            ->assertOk()
            ->assertSee('Pengajuan Perpanjangan Lisensi');
    }

    public function test_superadmin_can_generate_license_code_for_a_request(): void
    {
        $profile = $this->createProfile('2027-12-31');
        $superadmin = $this->createSuperadmin();
        $requester = User::factory()->create();

        $renewalRequest = LicenseRenewalRequest::query()->create([
            'pharmacy_profile_id' => $profile->id,
            'requested_by' => $requester->id,
            'duration_days' => 365,
            'status' => 'pending',
            'current_expires_at' => $this->licenseMoment('2027-12-31'),
            'projected_expires_at' => $this->licenseMoment('2028-12-30'),
        ]);

        $this
            ->actingAs($superadmin)
            ->post(route('pengaturan.manajemen-lisensi.generate', $renewalRequest))
            ->assertRedirect(route('pengaturan.manajemen-lisensi'))
            ->assertSessionHas('toast');

        $renewalRequest->refresh();

        $this->assertSame('code_generated', $renewalRequest->status);
        $this->assertSame($superadmin->id, $renewalRequest->generated_by);
        $this->assertDatabaseHas('license_activation_codes', [
            'license_renewal_request_id' => $renewalRequest->id,
            'license_type' => 'duration',
            'duration_days' => 365,
            'status' => 'available',
        ]);
    }

    public function test_superadmin_can_create_manual_license_code_without_renewal_request(): void
    {
        $profile = $this->createProfileWithoutLicense();
        $superadmin = $this->createSuperadmin();

        $this
            ->actingAs($superadmin)
            ->post(route('pengaturan.manajemen-lisensi.manual'), [
                'manual_expires_at' => '2027-12-31T20:30',
                '_manual_license_form' => 'manual',
            ])
            ->assertRedirect(route('pengaturan.manajemen-lisensi'))
            ->assertSessionHas('toast');

        $manualCode = LicenseActivationCode::query()
            ->where('pharmacy_profile_id', $profile->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($manualCode);
        $this->assertSame('manual', $manualCode->license_type);
        $this->assertNull($manualCode->license_renewal_request_id);
        $this->assertSame(0, $manualCode->duration_days);
        $this->assertSame('2027-12-31 20:30:00', $manualCode->fixed_expires_at?->format('Y-m-d H:i:s'));
        $this->assertSame('available', $manualCode->status);
    }

    public function test_superadmin_can_cancel_generated_license_code_and_restore_request_status(): void
    {
        $profile = $this->createProfile('2027-12-31');
        $superadmin = $this->createSuperadmin();
        $requester = User::factory()->create();

        $renewalRequest = LicenseRenewalRequest::query()->create([
            'pharmacy_profile_id' => $profile->id,
            'requested_by' => $requester->id,
            'duration_days' => 180,
            'status' => 'code_generated',
            'current_expires_at' => $this->licenseMoment('2027-12-31'),
            'projected_expires_at' => $this->licenseMoment('2028-06-28'),
            'generated_by' => $superadmin->id,
            'generated_at' => now(),
        ]);

        $activationCode = LicenseActivationCode::query()->create([
            'pharmacy_profile_id' => $profile->id,
            'license_renewal_request_id' => $renewalRequest->id,
            'generated_by' => $superadmin->id,
            'code' => 'LIC-260604-CANCEL',
            'duration_days' => 180,
            'status' => 'available',
        ]);

        $this
            ->actingAs($superadmin)
            ->delete(route('pengaturan.manajemen-lisensi.cancel-generate', $renewalRequest))
            ->assertRedirect(route('pengaturan.manajemen-lisensi'))
            ->assertSessionHas('toast');

        $renewalRequest->refresh();

        $this->assertSame('pending', $renewalRequest->status);
        $this->assertNull($renewalRequest->generated_by);
        $this->assertNull($renewalRequest->generated_at);
        $this->assertDatabaseMissing('license_activation_codes', [
            'id' => $activationCode->id,
        ]);
    }

    public function test_superadmin_can_delete_available_license_code_from_recent_codes(): void
    {
        $profile = $this->createProfile('2027-12-31');
        $superadmin = $this->createSuperadmin();
        $requester = User::factory()->create();

        $renewalRequest = LicenseRenewalRequest::query()->create([
            'pharmacy_profile_id' => $profile->id,
            'requested_by' => $requester->id,
            'duration_days' => 90,
            'status' => 'code_generated',
            'current_expires_at' => $this->licenseMoment('2027-12-31'),
            'projected_expires_at' => $this->licenseMoment('2028-03-30'),
            'generated_by' => $superadmin->id,
            'generated_at' => now(),
        ]);

        $activationCode = LicenseActivationCode::query()->create([
            'pharmacy_profile_id' => $profile->id,
            'license_renewal_request_id' => $renewalRequest->id,
            'generated_by' => $superadmin->id,
            'code' => 'LIC-260604-DELETE',
            'duration_days' => 90,
            'status' => 'available',
        ]);

        $this
            ->actingAs($superadmin)
            ->delete(route('pengaturan.manajemen-lisensi.codes.destroy', $activationCode))
            ->assertRedirect(route('pengaturan.manajemen-lisensi'))
            ->assertSessionHas('toast');

        $renewalRequest->refresh();

        $this->assertSame('pending', $renewalRequest->status);
        $this->assertNull($renewalRequest->generated_by);
        $this->assertNull($renewalRequest->generated_at);
        $this->assertDatabaseMissing('license_activation_codes', [
            'id' => $activationCode->id,
        ]);
    }

    public function test_superadmin_can_revoke_latest_activated_license_and_restore_previous_expiry(): void
    {
        $profile = $this->createProfile('2027-03-31');
        $superadmin = $this->createSuperadmin();
        $requester = User::factory()->create();

        $renewalRequest = LicenseRenewalRequest::query()->create([
            'pharmacy_profile_id' => $profile->id,
            'requested_by' => $requester->id,
            'duration_days' => 90,
            'status' => 'activated',
            'current_expires_at' => $this->licenseMoment('2027-03-31'),
            'projected_expires_at' => $this->licenseMoment('2027-06-29'),
            'generated_by' => $superadmin->id,
            'generated_at' => now()->subDay(),
            'activated_at' => now(),
        ]);

        $activationCode = LicenseActivationCode::query()->create([
            'pharmacy_profile_id' => $profile->id,
            'license_renewal_request_id' => $renewalRequest->id,
            'generated_by' => $superadmin->id,
            'used_by' => $requester->id,
            'code' => 'LIC-260604-REVOKE',
            'duration_days' => 90,
            'status' => 'used',
            'used_at' => now(),
            'previous_expires_at' => $this->licenseMoment('2027-03-31'),
            'applied_from' => $this->licenseStart('2027-04-01'),
            'applied_until' => $this->licenseMoment('2027-06-29'),
        ]);

        $profile->update([
            'app_license_status' => 'active',
            'app_license_expires_at' => $this->licenseMoment('2027-06-29'),
            'app_license_activated_at' => now(),
        ]);

        $this
            ->actingAs($superadmin)
            ->delete(route('pengaturan.manajemen-lisensi.codes.destroy', $activationCode))
            ->assertRedirect(route('pengaturan.manajemen-lisensi'))
            ->assertSessionHas('toast');

        $profile->refresh();
        $renewalRequest->refresh();

        $this->assertSame('2027-03-31', $profile->app_license_expires_at?->toDateString());
        $this->assertSame('active', $profile->app_license_status);
        $this->assertNull($profile->app_license_activated_at);
        $this->assertSame('pending', $renewalRequest->status);
        $this->assertNull($renewalRequest->generated_by);
        $this->assertNull($renewalRequest->generated_at);
        $this->assertNull($renewalRequest->activated_at);
        $this->assertDatabaseMissing('license_activation_codes', [
            'id' => $activationCode->id,
        ]);
    }

    public function test_manual_license_code_can_be_activated_and_revoked_to_restore_previous_expiry(): void
    {
        $profile = $this->createProfile('2027-03-31');
        $superadmin = $this->createSuperadmin();
        $user = User::factory()->create();

        $manualCode = LicenseActivationCode::query()->create([
            'pharmacy_profile_id' => $profile->id,
            'generated_by' => $superadmin->id,
            'code' => 'LIC-260604-MANUAL',
            'license_type' => 'manual',
            'duration_days' => 0,
            'fixed_expires_at' => '2027-12-31 20:30:00',
            'status' => 'available',
        ]);

        $this
            ->actingAs($user)
            ->post(route('pengaturan.lisensi.activate'), [
                'activation_code' => 'LIC-260604-MANUAL',
                '_license_form' => 'activate',
            ])
            ->assertRedirect(route('pengaturan.lisensi'))
            ->assertSessionHas('toast');

        $profile->refresh();
        $manualCode->refresh();

        $this->assertSame('2027-12-31 20:30:00', $profile->app_license_expires_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2027-03-31 23:59:59', $manualCode->previous_expires_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2027-12-31 20:30:00', $manualCode->applied_until?->format('Y-m-d H:i:s'));

        $this
            ->actingAs($superadmin)
            ->delete(route('pengaturan.manajemen-lisensi.codes.destroy', $manualCode))
            ->assertRedirect(route('pengaturan.manajemen-lisensi'))
            ->assertSessionHas('toast');

        $profile->refresh();

        $this->assertSame('2027-03-31 23:59:59', $profile->app_license_expires_at?->format('Y-m-d H:i:s'));
        $this->assertSame('active', $profile->app_license_status);
        $this->assertDatabaseMissing('license_activation_codes', [
            'id' => $manualCode->id,
        ]);
    }

    public function test_superadmin_can_update_qris_payment_settings(): void
    {
        Storage::fake('public');

        $superadmin = $this->createSuperadmin();

        $this
            ->actingAs($superadmin)
            ->post(route('pengaturan.manajemen-lisensi.qris'), [
                'receiver_name' => 'QRIS Apotik',
                'payment_notes' => 'Scan lalu kirim pengajuan perpanjangan.',
                'qris_image' => UploadedFile::fake()->image('qris.png'),
            ])
            ->assertRedirect(route('pengaturan.manajemen-lisensi'))
            ->assertSessionHas('toast');

        $path = AppSetting::valueOf('license.qris_image_path');

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame('QRIS Apotik', AppSetting::valueOf('license.qris_receiver_name'));
        $this->assertSame('Scan lalu kirim pengajuan perpanjangan.', AppSetting::valueOf('license.qris_notes'));

        $this
            ->actingAs($superadmin)
            ->get(route('pengaturan.lisensi.qris-image'))
            ->assertOk();
    }

    private function createProfile(string $expiresAt): PharmacyProfile
    {
        return PharmacyProfile::query()->create([
            'name' => 'Apotik Uji',
            'license_number' => 'SIA-UJI',
            'app_license_status' => 'active',
            'app_license_expires_at' => $this->licenseMoment($expiresAt),
            'is_active' => true,
        ]);
    }

    private function createProfileWithoutLicense(): PharmacyProfile
    {
        return PharmacyProfile::query()->create([
            'name' => 'Apotik Uji',
            'license_number' => 'SIA-UJI',
            'app_license_status' => 'inactive',
            'app_license_expires_at' => null,
            'app_license_activated_at' => null,
            'is_active' => true,
        ]);
    }

    private function createSuperadmin(): User
    {
        $user = User::factory()->create([
            'email' => 'superadmin@example.test',
        ]);

        $role = Role::query()->create([
            'code' => 'superadmin',
            'name' => 'Superadmin',
            'description' => 'Hak akses superadmin lisensi.',
            'is_active' => true,
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }

    private function licenseMoment(string $value): string
    {
        return Carbon::parse($value)->endOfDay()->toDateTimeString();
    }

    private function licenseStart(string $value): string
    {
        return Carbon::parse($value)->startOfDay()->toDateTimeString();
    }
}
