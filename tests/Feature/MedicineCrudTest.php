<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Principal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicineCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_medicine_index_page(): void
    {
        $user = User::factory()->create();
        $principal = Principal::query()->create([
            'code' => 'PRN-0001',
            'name' => 'Kalbe Farma',
            'is_active' => true,
        ]);
        $medicine = Medicine::query()->create([
            'code' => 'OBT-0001',
            'name' => 'Paracetamol 500 mg',
            'principal_id' => $principal->id,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('master-data.data-obat'));

        $response
            ->assertOk()
            ->assertSee('Data Obat')
            ->assertSee('Tambah Obat')
            ->assertSee('Lihat Detail')
            ->assertSee(route('master-data.data-obat', ['edit' => $medicine->id]));
    }

    public function test_edit_route_redirects_to_index_modal(): void
    {
        $user = User::factory()->create();
        $principal = Principal::query()->create([
            'code' => 'PRN-0002',
            'name' => 'Dexa Medica',
            'is_active' => true,
        ]);
        $medicine = Medicine::query()->create([
            'code' => 'OBT-0009',
            'name' => 'Cetirizine 10 mg',
            'principal_id' => $principal->id,
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->get(route('master-data.data-obat.edit', $medicine))
            ->assertRedirect(route('master-data.data-obat', ['edit' => $medicine->id]));
    }

    public function test_user_can_store_medicine_and_auto_create_principal(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('master-data.data-obat.store'), [
                'code' => 'OBT-0001',
                'principal_name' => 'Kalbe Farma',
                'name' => 'Paracetamol 500 mg',
                'medicine_type' => 'Tablet',
                'category_name' => 'Analgesik',
                'medicine_group' => 'Obat bebas',
                'large_unit' => 'Box',
                'small_unit' => 'Tablet',
                'small_unit_per_large_unit' => 10,
                'purchase_price' => 2500,
                'composition' => 'Paracetamol 500 mg per tablet.',
                'is_active' => '1',
            ]);

        $response
            ->assertRedirect(route('master-data.data-obat'))
            ->assertSessionHas('status', 'Data obat berhasil ditambahkan.');

        $this->assertDatabaseHas('principals', [
            'name' => 'Kalbe Farma',
        ]);

        $this->assertDatabaseHas('medicines', [
            'code' => 'OBT-0001',
            'name' => 'Paracetamol 500 mg',
            'medicine_type' => 'Tablet',
            'category_name' => 'Analgesik',
            'medicine_group' => 'Obat bebas',
            'large_unit' => 'Box',
            'small_unit' => 'Tablet',
            'small_unit_per_large_unit' => 10,
            'purchase_price' => 2500,
            'composition' => 'Paracetamol 500 mg per tablet.',
            'is_active' => true,
        ]);
    }

    public function test_user_can_store_medicine_with_formatted_purchase_price(): void
    {
        $user = User::factory()->create();
        $principal = Principal::query()->create([
            'code' => 'PRN-0100',
            'name' => 'Kimia Farma',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('master-data.data-obat.store'), [
                'code' => 'OBT-0100',
                'principal_id' => $principal->id,
                'name' => 'Ibuprofen 400 mg',
                'purchase_price' => '2.500',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('master-data.data-obat'));

        $this->assertDatabaseHas('medicines', [
            'code' => 'OBT-0100',
            'purchase_price' => 2500,
        ]);
    }

    public function test_user_can_update_medicine_with_formatted_purchase_price_without_decimal_fraction(): void
    {
        $user = User::factory()->create();
        $principal = Principal::query()->create([
            'code' => 'PRN-0101',
            'name' => 'Tempo Scan',
            'is_active' => true,
        ]);
        $medicine = Medicine::query()->create([
            'code' => 'OBT-0101',
            'name' => 'Vitamin C 500 mg',
            'principal_id' => $principal->id,
            'purchase_price' => 1000,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('master-data.data-obat.update', $medicine), [
                'code' => $medicine->code,
                'principal_id' => $principal->id,
                'name' => $medicine->name,
                'purchase_price' => '7.500,90',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('master-data.data-obat'));

        $this->assertDatabaseHas('medicines', [
            'id' => $medicine->id,
            'purchase_price' => 7500,
        ]);
    }

    public function test_user_can_update_and_delete_medicine(): void
    {
        $user = User::factory()->create();
        $principal = Principal::query()->create([
            'code' => 'PRN-0001',
            'name' => 'Sanbe Farma',
            'is_active' => true,
        ]);
        $medicine = Medicine::query()->create([
            'code' => 'OBT-0002',
            'name' => 'Amoxicillin 500 mg',
            'medicine_type' => 'Kapsul',
            'category_name' => 'Antibiotik',
            'medicine_group' => 'Obat keras',
            'large_unit' => 'Box',
            'small_unit' => 'Kapsul',
            'small_unit_per_large_unit' => 10,
            'purchase_price' => 6000,
            'composition' => 'Amoxicillin trihydrate 500 mg.',
            'principal_id' => $principal->id,
            'is_active' => true,
        ]);

        $updateResponse = $this
            ->actingAs($user)
            ->patch(route('master-data.data-obat.update', $medicine), [
                'code' => 'OBT-0002',
                'principal_name' => 'Dexa Medica',
                'name' => 'Amoxicillin 500 mg Updated',
                'medicine_type' => 'Kaplet',
                'category_name' => 'Antibiotik Sistemik',
                'medicine_group' => 'Obat keras',
                'large_unit' => 'Strip',
                'small_unit' => 'Kaplet',
                'small_unit_per_large_unit' => 6,
                'purchase_price' => 7500,
                'composition' => 'Amoxicillin 500 mg per kapsul.',
                'is_active' => '0',
            ]);

        $updateResponse
            ->assertRedirect(route('master-data.data-obat'))
            ->assertSessionHas('status', 'Data obat berhasil diperbarui.');

        $this->assertDatabaseHas('principals', [
            'name' => 'Dexa Medica',
        ]);

        $medicine->refresh();

        $this->assertSame('Amoxicillin 500 mg Updated', $medicine->name);
        $this->assertSame('Kaplet', $medicine->medicine_type);
        $this->assertSame('Antibiotik Sistemik', $medicine->category_name);
        $this->assertSame('Obat keras', $medicine->medicine_group);
        $this->assertSame('Strip', $medicine->large_unit);
        $this->assertSame('Kaplet', $medicine->small_unit);
        $this->assertSame(6, $medicine->small_unit_per_large_unit);
        $this->assertFalse($medicine->is_active);

        $deleteResponse = $this
            ->actingAs($user)
            ->delete(route('master-data.data-obat.destroy', $medicine));

        $deleteResponse
            ->assertRedirect(route('master-data.data-obat'))
            ->assertSessionHas('status', 'Obat Amoxicillin 500 mg Updated berhasil dihapus.');

        $this->assertDatabaseMissing('medicines', [
            'id' => $medicine->id,
        ]);
    }
}
