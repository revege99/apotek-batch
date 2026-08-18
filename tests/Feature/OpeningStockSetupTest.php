<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Role;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpeningStockSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_balance_setup_is_restricted_to_superadmin(): void
    {
        $regularUser = User::factory()->create();
        $superadmin = User::factory()->create();
        $role = Role::query()->create([
            'code' => 'superadmin',
            'name' => 'Super Admin',
            'is_active' => true,
        ]);
        $superadmin->roles()->attach($role->id);

        $this->actingAs($regularUser)
            ->get(route('setup-saldo-awal.stok'))
            ->assertForbidden();

        $this->actingAs($regularUser)
            ->post(route('setup-saldo-awal.stok.store'))
            ->assertForbidden();

        $this->actingAs($superadmin)
            ->get(route('setup-saldo-awal.stok'))
            ->assertOk();
    }

    public function test_multiple_opening_stock_rows_are_saved_in_one_submission(): void
    {
        $this->withoutMiddleware();
        $user = User::factory()->create();
        $location = StorageLocation::query()->create([
            'code' => 'GDG-TEST',
            'name' => 'Gudang Test',
            'is_active' => true,
        ]);
        $medicines = collect(range(1, 200))->map(fn (int $number): Medicine => Medicine::query()->create([
            'code' => 'OBT-TEST-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'name' => 'Obat Test '.$number,
            'small_unit' => 'Tablet',
            'purchase_price' => 1000 * $number,
            'is_active' => true,
        ]));

        $items = $medicines->values()->map(fn (Medicine $medicine, int $index): array => [
            'medicine_id' => $medicine->id,
            'storage_location_id' => $location->id,
            'batch_number' => 'BATCH-'.($index + 1),
            'expiry_date' => now()->addYear()->toDateString(),
            'quantity' => ($index + 1) * 10,
        ])->all();

        $this->actingAs($user)
            ->post(route('setup-saldo-awal.stok.store'), [
                'entry_number' => 'SA-TEST-001',
                'opening_date' => now()->toDateString(),
                'storage_location_id' => $location->id,
                'items_payload' => json_encode($items, JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('setup-saldo-awal.stok'));

        $this->assertDatabaseCount('opening_stock_entry_items', 200);
        $this->assertDatabaseCount('stock_batches', 200);
        $this->assertDatabaseCount('stock_movements', 200);

        foreach ($items as $item) {
            $this->assertDatabaseHas('opening_stock_entry_items', [
                'medicine_id' => $item['medicine_id'],
                'quantity' => $item['quantity'],
            ]);
        }
    }
}
