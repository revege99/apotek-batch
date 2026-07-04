<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Principal;
use App\Models\PharmacyProfile;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\PurchaseReturnReplacement;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReturnReplacementPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activateOperationalLicense();
    }

    public function test_authenticated_user_can_view_purchase_return_replacement_page(): void
    {
        $user = User::factory()->create();
        [$purchaseReturn] = $this->seedReplaceableReturn($user);

        $response = $this
            ->actingAs($user)
            ->get(route('pembelian.realisasi-pengganti-retur', ['purchase_return_id' => $purchaseReturn->id]));

        $response
            ->assertOk()
            ->assertSee('Realisasi Pengganti Retur')
            ->assertSee('Form realisasi pengganti retur')
            ->assertSee($purchaseReturn->return_number)
            ->assertSee('Riwayat Realisasi')
            ->assertDontSee('Riwayat realisasi pengganti retur');
    }

    public function test_authenticated_user_can_view_purchase_return_replacement_history_page(): void
    {
        $user = User::factory()->create();
        [$purchaseReturn] = $this->seedReplaceableReturn($user);

        $response = $this
            ->actingAs($user)
            ->get(route('pembelian.riwayat-realisasi-pengganti-retur', ['purchase_return_id' => $purchaseReturn->id]));

        $response
            ->assertOk()
            ->assertSee('Riwayat Realisasi Pengganti Retur')
            ->assertSee('Riwayat realisasi pengganti retur')
            ->assertSee('Form Realisasi')
            ->assertDontSee('Form realisasi pengganti retur');
    }

    public function test_authenticated_user_can_view_exchange_replacement_page(): void
    {
        $user = User::factory()->create();
        [$purchaseReturn] = $this->seedReplaceableReturn($user);

        $response = $this
            ->actingAs($user)
            ->get(route('pembelian.realisasi-tukar-barang', ['purchase_return_id' => $purchaseReturn->id]));

        $response
            ->assertOk()
            ->assertSee('Realisasi Tukar Barang')
            ->assertSee('Form realisasi tukar barang')
            ->assertSee($purchaseReturn->return_number);
    }

    public function test_replacement_realization_restores_stock_and_calculates_tax(): void
    {
        $user = User::factory()->create();
        [$purchaseReturn, $returnItem, $stockBatch] = $this->seedReplaceableReturn($user);

        $response = $this
            ->actingAs($user)
            ->post(route('pembelian.realisasi-pengganti-retur.store'), [
                'purchase_return_id' => $purchaseReturn->id,
                'replacement_date' => now()->toDateString(),
                'notes' => 'Pengganti diterima bertahap',
                'items' => [
                    [
                        'purchase_return_item_id' => $returnItem->id,
                        'quantity' => 4,
                    ],
                ],
            ]);

        $replacement = PurchaseReturnReplacement::query()->first();

        $response
            ->assertRedirect(route('pembelian.realisasi-pengganti-retur', ['purchase_return_id' => $purchaseReturn->id]))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success'
                && str_contains((string) ($toast['message'] ?? ''), 'berhasil disimpan'));

        $this->assertNotNull($replacement);
        $this->assertSame('RPR-0001', $replacement->replacement_number);
        $this->assertEquals(10000, (float) $replacement->subtotal);
        $this->assertEquals(1100, (float) $replacement->tax_amount);
        $this->assertEquals(11100, (float) $replacement->total_amount);

        $this->assertDatabaseHas('purchase_return_replacement_items', [
            'purchase_return_replacement_id' => $replacement->id,
            'purchase_return_item_id' => $returnItem->id,
            'purchase_invoice_item_id' => $returnItem->purchase_invoice_item_id,
            'medicine_id' => $stockBatch->medicine_id,
            'quantity' => 4,
            'unit_price' => 2500,
            'line_total' => 10000,
        ]);

        $this->assertDatabaseHas('stock_batches', [
            'id' => $stockBatch->id,
            'quantity_in' => 44,
            'quantity_out' => 10,
            'quantity_balance' => 34,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'purchase_return_replacement',
            'reference_table' => 'purchase_return_replacement_items',
            'medicine_id' => $stockBatch->medicine_id,
            'stock_batch_id' => $stockBatch->id,
            'quantity_in' => 4,
            'balance_after' => 34,
            'unit_cost' => 2500,
        ]);

        $movement = StockMovement::query()->where('movement_type', 'purchase_return_replacement')->first();
        $this->assertNotNull($movement);
    }

    public function test_purchase_return_with_replacement_realization_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        [$purchaseReturn, $returnItem] = $this->seedReplaceableReturn($user);

        $this
            ->actingAs($user)
            ->post(route('pembelian.realisasi-pengganti-retur.store'), [
                'purchase_return_id' => $purchaseReturn->id,
                'replacement_date' => now()->toDateString(),
                'items' => [
                    [
                        'purchase_return_item_id' => $returnItem->id,
                        'quantity' => 4,
                    ],
                ],
            ])
            ->assertRedirect(route('pembelian.realisasi-pengganti-retur', ['purchase_return_id' => $purchaseReturn->id]));

        $response = $this
            ->actingAs($user)
            ->delete(route('pembelian.retur-pembelian.destroy', $purchaseReturn));

        $response
            ->assertRedirect(route('pembelian.riwayat-retur-pembelian', ['purchase_invoice_id' => $purchaseReturn->purchase_invoice_id]))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'error'
                && str_contains((string) ($toast['message'] ?? ''), 'realisasi pengganti'));
    }

    public function test_replacement_realization_can_be_deleted_and_stock_is_withdrawn_again(): void
    {
        $user = User::factory()->create();
        [$purchaseReturn, $returnItem, $stockBatch] = $this->seedReplaceableReturn($user);

        $this
            ->actingAs($user)
            ->post(route('pembelian.realisasi-pengganti-retur.store'), [
                'purchase_return_id' => $purchaseReturn->id,
                'replacement_date' => now()->toDateString(),
                'items' => [
                    [
                        'purchase_return_item_id' => $returnItem->id,
                        'quantity' => 4,
                    ],
                ],
            ])
            ->assertRedirect(route('pembelian.realisasi-pengganti-retur', ['purchase_return_id' => $purchaseReturn->id]));

        $replacement = PurchaseReturnReplacement::query()->with('items')->first();

        $this->assertNotNull($replacement);
        $this->assertCount(1, $replacement->items);

        $replacementItemId = $replacement->items->first()->id;

        $response = $this
            ->actingAs($user)
            ->delete(route('pembelian.realisasi-pengganti-retur.destroy', $replacement));

        $response
            ->assertRedirect(route('pembelian.riwayat-realisasi-pengganti-retur', ['purchase_return_id' => $purchaseReturn->id]))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success'
                && str_contains((string) ($toast['message'] ?? ''), 'berhasil dihapus'));

        $this->assertDatabaseMissing('purchase_return_replacements', [
            'id' => $replacement->id,
        ]);

        $this->assertDatabaseMissing('purchase_return_replacement_items', [
            'id' => $replacementItemId,
        ]);

        $this->assertDatabaseMissing('stock_movements', [
            'movement_type' => 'purchase_return_replacement',
            'reference_table' => 'purchase_return_replacement_items',
            'reference_id' => $replacementItemId,
        ]);

        $this->assertDatabaseHas('stock_batches', [
            'id' => $stockBatch->id,
            'quantity_in' => 40,
            'quantity_out' => 10,
            'quantity_balance' => 30,
            'status' => 'active',
        ]);
    }

    public function test_exchange_replacement_submission_uses_same_logic_and_redirects_to_exchange_replacement_page(): void
    {
        $user = User::factory()->create();
        [$purchaseReturn, $returnItem] = $this->seedReplaceableReturn($user);

        $response = $this
            ->actingAs($user)
            ->post(route('pembelian.realisasi-tukar-barang.store'), [
                'purchase_return_id' => $purchaseReturn->id,
                'replacement_date' => now()->toDateString(),
                'items' => [
                    [
                        'purchase_return_item_id' => $returnItem->id,
                        'quantity' => 4,
                    ],
                ],
            ]);

        $replacement = PurchaseReturnReplacement::query()->first();

        $response
            ->assertRedirect(route('pembelian.realisasi-tukar-barang', ['purchase_return_id' => $purchaseReturn->id]))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success'
                && str_contains((string) ($toast['message'] ?? ''), 'Realisasi tukar barang'));

        $this->assertNotNull($replacement);
    }

    /**
     * Seed a purchase return with remaining replacement quantity.
     *
     * @return array{0: PurchaseReturn, 1: PurchaseReturnItem, 2: StockBatch}
     */
    private function seedReplaceableReturn(User $user): array
    {
        $principal = Principal::query()->create([
            'code' => 'PRN-RPL-001',
            'name' => 'Kalbe Farma',
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create([
            'code' => 'SUP-RPL-001',
            'name' => 'PT Supplier Pengganti',
            'is_active' => true,
        ]);

        $medicine = Medicine::query()->create([
            'code' => 'OBT-RPL-001',
            'name' => 'Paracetamol 500 mg',
            'principal_id' => $principal->id,
            'small_unit' => 'Tablet',
            'large_unit' => 'Box',
            'small_unit_per_large_unit' => 10,
            'is_active' => true,
        ]);

        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'INV-RPL-001',
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'status' => 'posted',
            'payment_status' => 'unpaid',
            'subtotal' => 100000,
            'discount_amount' => 0,
            'tax_percentage' => 11,
            'tax_amount' => 11000,
            'grand_total' => 111000,
            'outstanding_amount' => 111000,
            'created_by' => $user->id,
        ]);

        $invoiceItem = PurchaseInvoiceItem::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $medicine->id,
            'purchase_unit' => 'Tablet',
            'unit_content' => 1,
            'batch_number' => 'BATCH-RPL-01',
            'expiry_date' => now()->addMonths(8)->toDateString(),
            'quantity' => 40,
            'unit_price' => 2500,
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'tax_amount' => 11000,
            'line_total' => 100000,
        ]);

        $stockBatch = StockBatch::query()->create([
            'medicine_id' => $medicine->id,
            'purchase_invoice_item_id' => $invoiceItem->id,
            'batch_number' => 'BATCH-RPL-01',
            'expiry_date' => now()->addMonths(8)->toDateString(),
            'received_at' => now()->toDateString(),
            'purchase_price' => 2500,
            'initial_quantity' => 40,
            'quantity_in' => 40,
            'quantity_out' => 10,
            'quantity_balance' => 30,
            'status' => 'active',
        ]);

        $purchaseReturn = PurchaseReturn::query()->create([
            'return_number' => 'RTB-RPL-0001',
            'purchase_invoice_id' => $invoice->id,
            'supplier_id' => $supplier->id,
            'return_date' => now()->toDateString(),
            'status' => 'posted',
            'subtotal' => 25000,
            'tax_amount' => 2750,
            'total_amount' => 27750,
            'notes' => 'Kemasan penyok',
            'created_by' => $user->id,
        ]);

        $returnItem = PurchaseReturnItem::query()->create([
            'purchase_return_id' => $purchaseReturn->id,
            'purchase_invoice_item_id' => $invoiceItem->id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'BATCH-RPL-01',
            'expiry_date' => now()->addMonths(8)->toDateString(),
            'quantity' => 10,
            'unit_price' => 2500,
            'line_total' => 25000,
            'reason' => 'Kemasan penyok',
        ]);

        return [$purchaseReturn, $returnItem, $stockBatch];
    }

    private function activateOperationalLicense(string $expiresAt = '2030-12-31'): PharmacyProfile
    {
        return PharmacyProfile::query()->create([
            'name' => 'Apotik Uji',
            'license_number' => 'SIA-UJI',
            'app_license_status' => 'active',
            'app_license_expires_at' => \Illuminate\Support\Carbon::parse($expiresAt)->endOfDay()->toDateTimeString(),
            'is_active' => true,
        ]);
    }
}
