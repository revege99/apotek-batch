<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerGroup;
use App\Models\Medicine;
use App\Models\Principal;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_all_report_pages(): void
    {
        $user = User::factory()->create();
        $this->seedReportData($user);

        $pages = [
            'laporan.laporan-pembelian' => 'Laporan Pembelian',
            'laporan.laporan-penjualan' => 'Laporan Penjualan',
            'laporan.laporan-penerimaan-kas' => 'Laporan Penerimaan Kas',
            'laporan.laporan-stok' => 'Laporan Stok',
            'laporan.laporan-expired' => 'Laporan Expired',
            'laporan.laporan-hutang' => 'Laporan Hutang',
            'laporan.laporan-piutang' => 'Laporan Piutang',
            'laporan.laporan-laba-rugi' => 'Laporan Laba Rugi',
        ];

        foreach ($pages as $route => $label) {
            $response = $this
                ->actingAs($user)
                ->get(route($route));

            $response
                ->assertOk()
                ->assertSee($label);
        }
    }

    public function test_profit_loss_report_uses_sales_and_sale_returns_snapshot_values(): void
    {
        $user = User::factory()->create();
        $this->seedReportData($user);

        $response = $this
            ->actingAs($user)
            ->get(route('laporan.laporan-laba-rugi', [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->toDateString(),
            ]));

        $response
            ->assertOk()
            ->assertSee('Rp 6.000')
            ->assertSee('Rp 2.400')
            ->assertSee('Rp 3.600')
            ->assertSee('Rp 3.000')
            ->assertSee('Rp 600');
    }

    public function test_profit_loss_report_ignores_master_medicine_price_for_cogs(): void
    {
        $user = User::factory()->create();
        $this->seedReportData($user);

        Medicine::query()->where('code', 'OBT-RPT-001')->update([
            'purchase_price' => 9000,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('laporan.laporan-laba-rugi', [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->toDateString(),
            ]));

        $response
            ->assertOk()
            ->assertSee('Rp 6.000')
            ->assertSee('Rp 2.400')
            ->assertSee('Rp 3.600')
            ->assertSee('Rp 3.000')
            ->assertSee('Rp 600');
    }

    public function test_sales_report_shows_social_credit_and_net_totals_separately(): void
    {
        $user = User::factory()->create();
        $this->seedReportData($user);

        $customer = Customer::query()->firstOrFail();
        $customerGroup = CustomerGroup::query()->firstOrFail();

        Sale::query()->create([
            'sale_number' => 'PJL-RPT-0002',
            'sale_date' => now(),
            'status' => 'posted',
            'payment_method' => 'cash',
            'customer_id' => $customer->id,
            'customer_group_id' => $customerGroup->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'customer_group_markup_percentage' => 20,
            'subtotal' => 30000,
            'discount_amount' => 0,
            'social_amount' => 10000,
            'tax_amount' => 0,
            'grand_total' => 30000,
            'paid_amount' => 20000,
            'change_amount' => 0,
            'created_by' => $user->id,
        ]);

        Sale::query()->create([
            'sale_number' => 'PJL-RPT-0003',
            'sale_date' => now(),
            'status' => 'posted',
            'payment_method' => 'credit',
            'customer_id' => $customer->id,
            'customer_group_id' => $customerGroup->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'customer_group_markup_percentage' => 20,
            'subtotal' => 15000,
            'discount_amount' => 0,
            'social_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => 15000,
            'paid_amount' => 7000,
            'change_amount' => 0,
            'created_by' => $user->id,
        ]);

        $settledCreditSale = Sale::query()->create([
            'sale_number' => 'PJL-RPT-0004',
            'sale_date' => Carbon::parse('2026-06-03 10:00:00'),
            'status' => 'posted',
            'payment_method' => 'credit',
            'customer_id' => $customer->id,
            'customer_group_id' => $customerGroup->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'customer_group_markup_percentage' => 20,
            'subtotal' => 8000,
            'discount_amount' => 0,
            'social_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => 8000,
            'paid_amount' => 8000,
            'change_amount' => 0,
            'created_by' => $user->id,
        ]);

        CustomerPayment::query()->create([
            'payment_number' => 'BYR-RPT-0001',
            'sale_id' => $settledCreditSale->id,
            'customer_id' => $customer->id,
            'payment_date' => Carbon::parse('2026-06-07 09:30:00'),
            'payment_method' => 'transfer',
            'amount_paid' => 8000,
            'created_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('laporan.laporan-penjualan', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
            ]));

        $response
            ->assertOk()
            ->assertDontSee('Total Transaksi')
            ->assertSee('Total Sosial')
            ->assertSee('Total Kredit')
            ->assertSee('Penjualan Bersih')
            ->assertSee('Tanggal Pelunasan')
            ->assertSee('07/06/2026 09:30')
            ->assertSee('Rp 59.000')
            ->assertSee('Rp 10.000')
            ->assertSee('Rp 23.000')
            ->assertSee('Rp 49.000')
            ->assertSee('Sosial')
            ->assertSee('Kredit')
            ->assertSee('Belum Lunas')
            ->assertSee('Rp 20.000')
            ->assertSee('Tunai');
    }

    public function test_receivable_report_can_filter_paid_and_unpaid_credit_invoices(): void
    {
        $user = User::factory()->create();
        $this->seedReportData($user);

        $customer = Customer::query()->firstOrFail();
        $customerGroup = CustomerGroup::query()->firstOrFail();

        Sale::query()->create([
            'sale_number' => 'PJL-RCV-0001',
            'sale_date' => now(),
            'status' => 'posted',
            'payment_method' => 'credit',
            'customer_id' => $customer->id,
            'customer_group_id' => $customerGroup->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'customer_group_markup_percentage' => 20,
            'subtotal' => 12000,
            'discount_amount' => 0,
            'social_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => 12000,
            'paid_amount' => 0,
            'change_amount' => 0,
            'created_by' => $user->id,
        ]);

        Sale::query()->create([
            'sale_number' => 'PJL-RCV-0002',
            'sale_date' => now(),
            'status' => 'posted',
            'payment_method' => 'credit',
            'customer_id' => $customer->id,
            'customer_group_id' => $customerGroup->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'customer_group_markup_percentage' => 20,
            'subtotal' => 9000,
            'discount_amount' => 0,
            'social_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => 9000,
            'paid_amount' => 9000,
            'change_amount' => 0,
            'created_by' => $user->id,
        ]);

        CustomerPayment::query()->create([
            'payment_number' => 'BYR-RCV-0001',
            'sale_id' => Sale::query()->where('sale_number', 'PJL-RCV-0002')->value('id'),
            'customer_id' => $customer->id,
            'payment_date' => Carbon::parse('2026-06-04 10:30:00'),
            'payment_method' => 'cash',
            'amount_paid' => 9000,
            'created_by' => $user->id,
        ]);

        $allResponse = $this
            ->actingAs($user)
            ->get(route('laporan.laporan-piutang', [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->toDateString(),
                'status' => 'all',
            ]));

        $allResponse
            ->assertOk()
            ->assertSee('PJL-RCV-0001')
            ->assertSee('PJL-RCV-0002')
            ->assertSee('Belum Lunas')
            ->assertSee('Lunas')
            ->assertSee('Tanggal Pelunasan')
            ->assertSee('04/06/2026 10:30');

        $paidResponse = $this
            ->actingAs($user)
            ->get(route('laporan.laporan-piutang', [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->toDateString(),
                'status' => 'paid',
            ]));

        $paidResponse
            ->assertOk()
            ->assertSee('PJL-RCV-0002')
            ->assertDontSee('PJL-RCV-0001');

        $unpaidResponse = $this
            ->actingAs($user)
            ->get(route('laporan.laporan-piutang', [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->toDateString(),
                'status' => 'unpaid',
            ]));

        $unpaidResponse
            ->assertOk()
            ->assertSee('PJL-RCV-0001')
            ->assertDontSee('PJL-RCV-0002');
    }

    public function test_cash_receipt_report_combines_direct_sales_and_receivable_payments_by_receipt_date(): void
    {
        $user = User::factory()->create();
        $this->seedReportData($user);

        $customer = Customer::query()->firstOrFail();
        $customerGroup = CustomerGroup::query()->firstOrFail();

        $creditSale = Sale::query()->create([
            'sale_number' => 'PJL-KAS-0001',
            'sale_date' => Carbon::parse('2026-05-31 15:00:00'),
            'status' => 'posted',
            'payment_method' => 'credit',
            'customer_id' => $customer->id,
            'customer_group_id' => $customerGroup->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'customer_group_markup_percentage' => 20,
            'subtotal' => 8000,
            'discount_amount' => 0,
            'social_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => 8000,
            'paid_amount' => 8000,
            'change_amount' => 0,
            'created_by' => $user->id,
        ]);

        CustomerPayment::query()->create([
            'payment_number' => 'BYR-KAS-0001',
            'sale_id' => $creditSale->id,
            'customer_id' => $customer->id,
            'payment_date' => Carbon::parse('2026-06-02 09:00:00'),
            'payment_method' => 'transfer',
            'amount_paid' => 8000,
            'created_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('laporan.laporan-penerimaan-kas', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
            ]));

        $response
            ->assertOk()
            ->assertSee('Penjualan Langsung')
            ->assertSee('Pembayaran Piutang')
            ->assertSee('Total Penerimaan Kas')
            ->assertSee('PJL-RPT-0001')
            ->assertSee('BYR-KAS-0001')
            ->assertSee('Pelunasan PJL-KAS-0001')
            ->assertSee('Rp 6.000')
            ->assertSee('Rp 8.000')
            ->assertSee('Rp 14.000');
    }

    public function test_profit_loss_report_reduces_net_sales_by_social_amount(): void
    {
        $user = User::factory()->create();
        $this->seedReportData($user);

        $customer = Customer::query()->firstOrFail();
        $customerGroup = CustomerGroup::query()->firstOrFail();

        Sale::query()->create([
            'sale_number' => 'PJL-RPT-0002',
            'sale_date' => now(),
            'status' => 'posted',
            'payment_method' => 'cash',
            'customer_id' => $customer->id,
            'customer_group_id' => $customerGroup->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'customer_group_markup_percentage' => 20,
            'subtotal' => 30000,
            'discount_amount' => 0,
            'social_amount' => 10000,
            'tax_amount' => 0,
            'grand_total' => 30000,
            'paid_amount' => 20000,
            'change_amount' => 0,
            'created_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('laporan.laporan-laba-rugi', [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->toDateString(),
            ]));

        $response
            ->assertOk()
            ->assertSee('Rp 36.000')
            ->assertSee('Rp 2.400')
            ->assertSee('Rp 23.600');
    }

    /**
     * Seed a compact dataset that can feed every report page.
     */
    private function seedReportData(User $user): void
    {
        $principal = Principal::query()->create([
            'code' => 'PRN-RPT-001',
            'name' => 'Kalbe Farma',
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create([
            'code' => 'SUP-RPT-001',
            'name' => 'PT Supplier Laporan',
            'payment_term_days' => 30,
            'is_active' => true,
        ]);

        $customerGroup = CustomerGroup::query()->create([
            'code' => 'GPL-RPT-001',
            'name' => 'Umum',
            'markup_percentage' => 20,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'code' => 'PLG-RPT-001',
            'customer_group_id' => $customerGroup->id,
            'name' => 'William D.N',
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        $medicine = Medicine::query()->create([
            'code' => 'OBT-RPT-001',
            'name' => 'Lerzin Drop 15 ml',
            'principal_id' => $principal->id,
            'small_unit' => 'Botol',
            'large_unit' => 'Dus',
            'small_unit_per_large_unit' => 1,
            'purchase_price' => 1000,
            'is_active' => true,
        ]);

        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'INV-RPT-0001',
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'posted',
            'payment_status' => 'unpaid',
            'subtotal' => 20000,
            'discount_amount' => 0,
            'tax_percentage' => 11,
            'tax_amount' => 2200,
            'other_cost_amount' => 0,
            'grand_total' => 22200,
            'paid_amount' => 0,
            'outstanding_amount' => 22200,
            'created_by' => $user->id,
        ]);

        $invoiceItem = PurchaseInvoiceItem::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $medicine->id,
            'purchase_unit' => 'Botol',
            'unit_content' => 1,
            'batch_number' => 'BATCH-RPT-01',
            'expiry_date' => now()->addMonths(2)->toDateString(),
            'quantity' => 20,
            'unit_price' => 1000,
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'tax_amount' => 2200,
            'line_total' => 20000,
        ]);

        $stockBatch = StockBatch::query()->create([
            'medicine_id' => $medicine->id,
            'purchase_invoice_item_id' => $invoiceItem->id,
            'batch_number' => 'BATCH-RPT-01',
            'expiry_date' => now()->addMonths(2)->toDateString(),
            'received_at' => now()->toDateString(),
            'purchase_price' => 1000,
            'initial_quantity' => 20,
            'quantity_in' => 22,
            'quantity_out' => 5,
            'quantity_balance' => 17,
            'status' => 'active',
        ]);

        $sale = Sale::query()->create([
            'sale_number' => 'PJL-RPT-0001',
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
            'social_amount' => 0,
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
            'batch_number_snapshot' => 'BATCH-RPT-01',
            'expiry_date_snapshot' => now()->addMonths(2)->toDateString(),
            'quantity' => 5,
            'unit_cost' => 1000,
            'markup_percentage' => 20,
            'unit_price' => 1200,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => 6000,
        ]);

        $saleReturn = SaleReturn::query()->create([
            'return_number' => 'RTJ-RPT-0001',
            'sale_id' => $sale->id,
            'return_date' => now(),
            'status' => 'posted',
            'subtotal' => 2400,
            'tax_amount' => 0,
            'total_amount' => 2400,
            'created_by' => $user->id,
        ]);

        SaleReturnItem::query()->create([
            'sale_return_id' => $saleReturn->id,
            'sale_item_id' => $saleItem->id,
            'medicine_id' => $medicine->id,
            'stock_batch_id' => $stockBatch->id,
            'quantity' => 2,
            'unit_price' => 1200,
            'line_total' => 2400,
            'reason' => 'Salah kirim',
        ]);
    }
}
