<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReceivablePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_customer_receivable_page(): void
    {
        $user = User::factory()->create();
        [, $customer] = $this->seedCreditSale();

        $response = $this
            ->actingAs($user)
            ->get(route('keuangan.piutang-pelanggan'));

        $response
            ->assertOk()
            ->assertSee('Piutang Pelanggan')
            ->assertSee($customer->name)
            ->assertSee('Total piutang')
            ->assertSee('Total faktur kredit');
    }

    public function test_credit_sale_payment_can_be_recorded_from_customer_receivable_page(): void
    {
        $user = User::factory()->create();
        [, $customer, $sale] = $this->seedCreditSale();

        $response = $this
            ->actingAs($user)
            ->from(route('keuangan.piutang-pelanggan'))
            ->post(route('keuangan.piutang-pelanggan.bayar', $sale), [
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'amount' => 3500,
                'reference_number' => 'TRX-001',
                'notes' => 'Pelunasan faktur kredit',
            ]);

        $sale->refresh();
        $payment = CustomerPayment::query()->first();

        $response
            ->assertRedirect(route('keuangan.piutang-pelanggan'))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success');

        $this->assertNotNull($payment);
        $this->assertSame('BYR-0001', $payment->payment_number);
        $this->assertEquals($sale->id, $payment->sale_id);
        $this->assertEquals($customer->id, $payment->customer_id);
        $this->assertEquals(3500, (float) $payment->amount_paid);
        $this->assertEquals(3500, (float) $sale->paid_amount);

        $this
            ->actingAs($user)
            ->get(route('keuangan.riwayat-pembayaran'))
            ->assertOk()
            ->assertSee('BYR-0001')
            ->assertSee($customer->name)
            ->assertSee($sale->sale_number);
    }

    public function test_credit_sale_payment_cannot_exceed_outstanding_amount(): void
    {
        $user = User::factory()->create();
        [, , $sale] = $this->seedCreditSale();

        $response = $this
            ->actingAs($user)
            ->from(route('keuangan.piutang-pelanggan'))
            ->post(route('keuangan.piutang-pelanggan.bayar', $sale), [
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'amount' => 999999,
            ]);

        $response
            ->assertRedirect(route('keuangan.piutang-pelanggan'))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'error'
                && str_contains((string) ($toast['message'] ?? ''), 'melebihi sisa piutang'));

        $this->assertDatabaseCount('customer_payments', 0);
        $this->assertEquals(0, (float) $sale->fresh()->paid_amount);
    }

    public function test_credit_sale_payment_cannot_be_recorded_as_partial_invoice_payment(): void
    {
        $user = User::factory()->create();
        [, , $sale] = $this->seedCreditSale();

        $response = $this
            ->actingAs($user)
            ->from(route('keuangan.piutang-pelanggan'))
            ->post(route('keuangan.piutang-pelanggan.bayar', $sale), [
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'amount' => 1500,
            ]);

        $response
            ->assertRedirect(route('keuangan.piutang-pelanggan'))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'error'
                && str_contains((string) ($toast['message'] ?? ''), 'harus dilunasi penuh'));

        $this->assertDatabaseCount('customer_payments', 0);
        $this->assertEquals(0, (float) $sale->fresh()->paid_amount);
    }

    public function test_customer_receivable_detail_can_download_pdf_statement(): void
    {
        $user = User::factory()->create();
        [, $customer] = $this->seedCreditSale();

        $response = $this
            ->actingAs($user)
            ->get(route('keuangan.piutang-pelanggan.print', $customer));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('piutang-'.$customer->code.'.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_customer_payment_history_can_download_pdf_statement(): void
    {
        $user = User::factory()->create();
        [, $customer, $sale] = $this->seedCreditSale();

        CustomerPayment::query()->create([
            'payment_number' => 'BYR-PRINT-01',
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'payment_date' => now(),
            'payment_method' => 'cash',
            'amount_paid' => 1200,
            'created_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('keuangan.riwayat-pembayaran.print', [
                'customer' => $customer,
                'date_from' => now()->toDateString(),
                'date_to' => now()->toDateString(),
            ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('pembayaran-piutang-'.$customer->code.'.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_authenticated_user_can_view_receivable_payment_history_page(): void
    {
        $user = User::factory()->create();
        [, $customer, $sale] = $this->seedCreditSale();

        CustomerPayment::query()->create([
            'payment_number' => 'BYR-0099',
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'payment_date' => now(),
            'payment_method' => 'transfer',
            'reference_number' => 'REF-0099',
            'amount_paid' => 2000,
            'created_by' => $user->id,
        ]);

        $sale->update([
            'paid_amount' => 2000,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('keuangan.riwayat-pembayaran'));

        $response
            ->assertOk()
            ->assertSee('Riwayat pembayaran piutang')
            ->assertSee('BYR-0099')
            ->assertSee($customer->name)
            ->assertSee($sale->sale_number);
    }

    public function test_receivable_payment_history_defaults_to_today_range(): void
    {
        $user = User::factory()->create();
        [, $customer, $sale] = $this->seedCreditSale();

        CustomerPayment::query()->create([
            'payment_number' => 'BYR-TODAY',
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'payment_date' => now(),
            'payment_method' => 'cash',
            'amount_paid' => 1000,
            'created_by' => $user->id,
        ]);

        CustomerPayment::query()->create([
            'payment_number' => 'BYR-YESTERDAY',
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'payment_date' => now()->subDay(),
            'payment_method' => 'cash',
            'amount_paid' => 500,
            'created_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('keuangan.riwayat-pembayaran'));

        $response
            ->assertOk()
            ->assertSee('BYR-TODAY')
            ->assertDontSee('BYR-YESTERDAY')
            ->assertSee('value="'.now()->toDateString().'"', false);
    }

    public function test_receivable_payment_can_be_deleted_and_sale_paid_amount_is_restored(): void
    {
        $user = User::factory()->create();
        [, $customer, $sale] = $this->seedCreditSale();

        $payment = CustomerPayment::query()->create([
            'payment_number' => 'BYR-DELETE-01',
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'payment_date' => now(),
            'payment_method' => 'cash',
            'amount_paid' => 1750,
            'created_by' => $user->id,
        ]);

        $sale->update([
            'paid_amount' => 1750,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('keuangan.riwayat-pembayaran'))
            ->delete(route('keuangan.riwayat-pembayaran.destroy', $payment));

        $response
            ->assertRedirect(route('keuangan.riwayat-pembayaran'))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success'
                && str_contains((string) ($toast['message'] ?? ''), 'berhasil dihapus'));

        $this->assertDatabaseMissing('customer_payments', [
            'id' => $payment->id,
        ]);

        $this->assertEquals(0, (float) $sale->fresh()->paid_amount);
    }

    /**
     * Seed a customer and one outstanding credit sale.
     *
     * @return array{0: CustomerGroup, 1: Customer, 2: Sale}
     */
    private function seedCreditSale(): array
    {
        $customerGroup = CustomerGroup::query()->create([
            'code' => 'GPL-CR-001',
            'name' => 'Klinik Kredit',
            'markup_percentage' => 20,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'code' => 'PLG-CR-001',
            'customer_group_id' => $customerGroup->id,
            'name' => 'Klinik Sejahtera',
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        $sale = Sale::query()->create([
            'sale_number' => 'PJL-CR-0001',
            'sale_date' => now(),
            'status' => 'posted',
            'payment_method' => 'credit',
            'customer_id' => $customer->id,
            'customer_group_id' => $customerGroup->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'customer_group_markup_percentage' => 20,
            'subtotal' => 3500,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => 3500,
            'paid_amount' => 0,
            'change_amount' => 0,
        ]);

        return [$customerGroup, $customer, $sale];
    }
}
