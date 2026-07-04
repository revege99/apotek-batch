<?php

namespace Tests\Feature;

use Carbon\Carbon;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Medicine;
use App\Models\PharmacyProfile;
use App\Models\Principal;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleReturnPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activateOperationalLicense();
    }

    public function test_authenticated_user_can_view_sale_return_page(): void
    {
        $user = User::factory()->create();
        [, , $sale] = $this->seedReturnableSale($user);

        $response = $this
            ->actingAs($user)
            ->get(route('penjualan.retur-penjualan', ['sale_id' => $sale->id]));

        $response
            ->assertOk()
            ->assertSee('Retur Penjualan')
            ->assertSee('Form retur penjualan')
            ->assertSee($sale->sale_number)
            ->assertSee('Riwayat Retur')
            ->assertDontSee('Riwayat retur penjualan');
    }

    public function test_authenticated_user_can_view_sale_return_history_page(): void
    {
        $user = User::factory()->create();
        [, , $sale] = $this->seedReturnableSale($user);

        $response = $this
            ->actingAs($user)
            ->get(route('penjualan.riwayat-retur-penjualan', ['sale_id' => $sale->id]));

        $response
            ->assertOk()
            ->assertSee('Riwayat Retur Penjualan')
            ->assertSee('Riwayat retur penjualan')
            ->assertSee('Form Retur')
            ->assertDontSee('Form retur penjualan');
    }

    public function test_sale_return_restores_stock_and_creates_movement(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-03 14:45:30'));

        $user = User::factory()->create();
        [, , $sale, $saleItem, $stockBatch] = $this->seedReturnableSale($user);

        $response = $this
            ->actingAs($user)
            ->post(route('penjualan.retur-penjualan.store'), [
                'sale_id' => $sale->id,
                'return_date' => now()->toDateString(),
                'notes' => 'Barang dikembalikan pelanggan',
                'items' => [
                    [
                        'sale_item_id' => $saleItem->id,
                        'quantity' => 2,
                        'reason' => 'Salah kirim',
                    ],
                ],
            ]);

        $saleReturn = SaleReturn::query()->with('items')->first();

        $response
            ->assertRedirect(route('penjualan.retur-penjualan', ['sale_id' => $sale->id]))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success'
                && str_contains((string) ($toast['message'] ?? ''), 'berhasil disimpan'));

        $this->assertNotNull($saleReturn);
        $this->assertSame('RTJ-0001', $saleReturn->return_number);
        $this->assertEquals(2400, (float) $saleReturn->subtotal);
        $this->assertEquals(2400, (float) $saleReturn->total_amount);

        $this->assertDatabaseHas('sale_return_items', [
            'sale_return_id' => $saleReturn->id,
            'sale_item_id' => $saleItem->id,
            'medicine_id' => $stockBatch->medicine_id,
            'stock_batch_id' => $stockBatch->id,
            'quantity' => 2,
            'unit_price' => 1200,
            'line_total' => 2400,
            'reason' => 'Salah kirim',
        ]);

        $this->assertDatabaseHas('stock_batches', [
            'id' => $stockBatch->id,
            'quantity_in' => 22,
            'quantity_out' => 5,
            'quantity_balance' => 17,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'sale_return',
            'reference_table' => 'sale_return_items',
            'medicine_id' => $stockBatch->medicine_id,
            'stock_batch_id' => $stockBatch->id,
            'quantity_in' => 2,
            'balance_after' => 17,
            'unit_cost' => 1000,
        ]);

        $this->assertCount(1, $saleReturn->items);
        $movement = StockMovement::query()->where('movement_type', 'sale_return')->first();
        $this->assertNotNull($movement);
        $this->assertSame('14:45:30', $movement->movement_date?->format('H:i:s'));
        $this->assertSame('14:45:30', $saleReturn->return_date?->format('H:i:s'));

        Carbon::setTestNow();
    }

    public function test_sale_with_return_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        [, , $sale, $saleItem] = $this->seedReturnableSale($user);

        $this
            ->actingAs($user)
            ->post(route('penjualan.retur-penjualan.store'), [
                'sale_id' => $sale->id,
                'return_date' => now()->toDateString(),
                'items' => [
                    [
                        'sale_item_id' => $saleItem->id,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertRedirect(route('penjualan.retur-penjualan', ['sale_id' => $sale->id]));

        $response = $this
            ->actingAs($user)
            ->delete(route('penjualan.data-penjualan.destroy', $sale));

        $response
            ->assertRedirect(route('penjualan.data-penjualan'))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'error'
                && str_contains((string) ($toast['message'] ?? ''), 'sudah punya retur'));
    }

    public function test_sale_return_can_be_deleted_and_stock_is_withdrawn_again(): void
    {
        $user = User::factory()->create();
        [, , $sale, $saleItem, $stockBatch] = $this->seedReturnableSale($user);

        $this
            ->actingAs($user)
            ->post(route('penjualan.retur-penjualan.store'), [
                'sale_id' => $sale->id,
                'return_date' => now()->toDateString(),
                'items' => [
                    [
                        'sale_item_id' => $saleItem->id,
                        'quantity' => 2,
                    ],
                ],
            ])
            ->assertRedirect(route('penjualan.retur-penjualan', ['sale_id' => $sale->id]));

        $saleReturn = SaleReturn::query()->with('items')->first();

        $this->assertNotNull($saleReturn);
        $this->assertCount(1, $saleReturn->items);

        $saleReturnItemId = $saleReturn->items->first()->id;

        $response = $this
            ->actingAs($user)
            ->delete(route('penjualan.retur-penjualan.destroy', $saleReturn));

        $response
            ->assertRedirect(route('penjualan.riwayat-retur-penjualan', ['sale_id' => $sale->id]))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success'
                && str_contains((string) ($toast['message'] ?? ''), 'berhasil dihapus'));

        $this->assertDatabaseMissing('sale_returns', [
            'id' => $saleReturn->id,
        ]);

        $this->assertDatabaseMissing('sale_return_items', [
            'id' => $saleReturnItemId,
        ]);

        $this->assertDatabaseMissing('stock_movements', [
            'movement_type' => 'sale_return',
            'reference_table' => 'sale_return_items',
            'reference_id' => $saleReturnItemId,
        ]);

        $this->assertDatabaseHas('stock_batches', [
            'id' => $stockBatch->id,
            'quantity_in' => 20,
            'quantity_out' => 5,
            'quantity_balance' => 15,
            'status' => 'active',
        ]);
    }

    /**
     * Seed a posted sale with remaining returnable quantity.
     *
     * @return array{0: CustomerGroup, 1: Customer, 2: Sale, 3: SaleItem, 4: StockBatch}
     */
    private function seedReturnableSale(User $user): array
    {
        $principal = Principal::query()->create([
            'code' => 'PRN-RTJ-001',
            'name' => 'Kalbe Farma',
            'is_active' => true,
        ]);

        $customerGroup = CustomerGroup::query()->create([
            'code' => 'GPL-RTJ-001',
            'name' => 'Umum',
            'markup_percentage' => 20,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'code' => 'PLG-RTJ-001',
            'customer_group_id' => $customerGroup->id,
            'name' => 'William D.N',
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        $medicine = Medicine::query()->create([
            'code' => 'OBT-RTJ-001',
            'name' => 'Lerzin Drop 15 ml',
            'principal_id' => $principal->id,
            'small_unit' => 'Botol',
            'large_unit' => 'Dus',
            'small_unit_per_large_unit' => 1,
            'purchase_price' => 1000,
            'is_active' => true,
        ]);

        $stockBatch = StockBatch::query()->create([
            'medicine_id' => $medicine->id,
            'purchase_invoice_item_id' => null,
            'batch_number' => 'BATCH-RTJ-01',
            'expiry_date' => now()->addMonths(8)->toDateString(),
            'received_at' => now()->toDateString(),
            'purchase_price' => 1000,
            'initial_quantity' => 20,
            'quantity_in' => 20,
            'quantity_out' => 5,
            'quantity_balance' => 15,
            'status' => 'active',
        ]);

        $sale = Sale::query()->create([
            'sale_number' => 'PJL-RTJ-0001',
            'sale_date' => now(),
            'status' => 'posted',
            'payment_method' => 'cash',
            'customer_id' => $customer->id,
            'customer_group_id' => $customerGroup->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'customer_group_markup_percentage' => 20,
            'subtotal' => 6000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => 6000,
            'paid_amount' => 6000,
            'change_amount' => 0,
            'created_by' => $user->id,
        ]);

        $saleItem = SaleItem::query()->create([
            'sale_id' => $sale->id,
            'medicine_id' => $medicine->id,
            'stock_batch_id' => $stockBatch->id,
            'batch_number_snapshot' => 'BATCH-RTJ-01',
            'expiry_date_snapshot' => now()->addMonths(8)->toDateString(),
            'quantity' => 5,
            'unit_cost' => 1000,
            'markup_percentage' => 20,
            'unit_price' => 1200,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => 6000,
        ]);

        return [$customerGroup, $customer, $sale, $saleItem, $stockBatch];
    }

    private function activateOperationalLicense(string $expiresAt = '2030-12-31'): PharmacyProfile
    {
        return PharmacyProfile::query()->create([
            'name' => 'Apotik Uji',
            'license_number' => 'SIA-UJI',
            'app_license_status' => 'active',
            'app_license_expires_at' => Carbon::parse($expiresAt)->endOfDay()->toDateTimeString(),
            'is_active' => true,
        ]);
    }
}
