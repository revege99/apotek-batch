<?php

namespace Tests\Feature;

use App\Models\PharmacyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_pharmacy_profile_page(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('pengaturan.profil-apotik'));

        $response
            ->assertOk()
            ->assertSee('Profil apotik')
            ->assertSee('Kop Faktur Penjualan');

        $this->assertDatabaseCount('pharmacy_profiles', 1);
    }

    public function test_authenticated_user_can_update_pharmacy_profile(): void
    {
        $user = User::factory()->create();

        PharmacyProfile::query()->create([
            'name' => 'Apotik Lama',
            'owner_name' => 'Owner Lama',
            'phone' => '0811111111',
            'email' => 'lama@apotik.test',
            'city' => 'Medan',
            'province' => 'Sumatera Utara',
            'postal_code' => '20100',
            'tax_number' => 'NPWP-LAMA',
            'license_number' => 'SIA-LAMA',
            'address' => 'Alamat lama',
            'invoice_footer' => 'Footer lama',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('pengaturan.profil-apotik.update'), [
                'name' => 'Apotek Sint Lucia',
                'owner_name' => 'Sr. Anastasia KSFL',
                'phone' => '081264942330',
                'email' => 'halo@sintlucia.test',
                'city' => 'Pematang Siantar',
                'province' => 'Sumatera Utara',
                'postal_code' => '21111',
                'tax_number' => '12.345.678.9-123.000',
                'license_number' => '445/7233/SIA/VI/2018',
                'address' => 'Jl. Jend Sudirman No 3248',
                'invoice_footer' => 'Terima kasih atas kepercayaan Anda.',
            ]);

        $response
            ->assertRedirect(route('pengaturan.profil-apotik'))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success');

        $this->assertDatabaseHas('pharmacy_profiles', [
            'name' => 'Apotek Sint Lucia',
            'owner_name' => 'Sr. Anastasia KSFL',
            'phone' => '081264942330',
            'city' => 'Pematang Siantar',
            'license_number' => '445/7233/SIA/VI/2018',
        ]);
    }
}
