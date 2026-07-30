<?php

namespace Tests\Feature;

use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPayablePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_payable_page_groups_credit_invoices_by_supplier(): void
    {
        $user = User::factory()->create();
        [$supplier] = $this->seedCreditInvoice();

        $this->actingAs($user)
            ->get(route('keuangan.pembayaran-hutang'))
            ->assertOk()
            ->assertSee($supplier->name)
            ->assertSee('Total hutang')
            ->assertSee('Faktur kredit');
    }

    public function test_credit_purchase_invoice_can_be_settled_in_full(): void
    {
        $user = User::factory()->create();
        [$supplier, $invoice] = $this->seedCreditInvoice();

        $response = $this->actingAs($user)
            ->post(route('keuangan.pembayaran-hutang.bayar', $invoice), [
                'payment_date' => now()->toDateString(),
                'payment_method' => 'transfer',
                'amount' => 125000,
                'reference_number' => 'TRF-HUTANG-001',
            ]);

        $invoice->refresh();
        $payment = SupplierPayment::query()->sole();

        $response
            ->assertRedirect(route('keuangan.pembayaran-hutang.show', $supplier))
            ->assertSessionHas('toast', fn ($toast): bool => ($toast['type'] ?? null) === 'success');

        $this->assertSame('BYH-0001', $payment->payment_number);
        $this->assertEquals(125000, (float) $payment->total_amount);
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertEquals(125000, (float) $invoice->paid_amount);
        $this->assertEquals(0, (float) $invoice->outstanding_amount);
        $this->assertDatabaseHas('supplier_payment_allocations', [
            'supplier_payment_id' => $payment->id,
            'purchase_invoice_id' => $invoice->id,
            'amount_paid' => 125000,
        ]);
    }

    public function test_supplier_payment_must_settle_the_full_outstanding_amount(): void
    {
        $user = User::factory()->create();
        [, $invoice] = $this->seedCreditInvoice();

        $this->actingAs($user)
            ->post(route('keuangan.pembayaran-hutang.bayar', $invoice), [
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'amount' => 50000,
            ])
            ->assertSessionHas('toast', fn ($toast): bool => ($toast['type'] ?? null) === 'error');

        $this->assertDatabaseCount('supplier_payments', 0);
        $this->assertEquals(125000, (float) $invoice->fresh()->outstanding_amount);
    }

    /**
     * @return array{0: Supplier, 1: PurchaseInvoice}
     */
    private function seedCreditInvoice(): array
    {
        $supplier = Supplier::query()->create([
            'code' => 'SUP-HUTANG-001',
            'name' => 'PT Supplier Kredit',
            'payment_term_days' => 30,
            'is_active' => true,
        ]);

        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'INV-HUTANG-001',
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'posted',
            'payment_status' => 'unpaid',
            'payment_method' => 'credit',
            'subtotal' => 125000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'other_cost_amount' => 0,
            'grand_total' => 125000,
            'paid_amount' => 0,
            'outstanding_amount' => 125000,
        ]);

        return [$supplier, $invoice];
    }
}
