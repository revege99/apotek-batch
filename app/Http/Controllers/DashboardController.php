<?php

namespace App\Http\Controllers;

use App\Models\CustomerPayment;
use App\Models\Medicine;
use App\Models\PurchaseInvoice;
use App\Models\Sale;
use App\Models\StockBatch;
use App\Models\StockOpname;
use App\Support\NavigationAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the operational dashboard.
     */
    public function index(): View
    {
        $user = auth()->user();
        $sections = NavigationAccess::navigationFor($user);
        $today = now();
        $todayDate = $today->toDateString();
        $expiryLimit = $today->copy()->addDays(90)->toDateString();

        $salesTodayCount = Sale::query()
            ->whereDate('sale_date', $todayDate)
            ->count();

        $salesTodayTotal = (float) Sale::query()
            ->whereDate('sale_date', $todayDate)
            ->sum('grand_total');

        $purchasesTodayCount = PurchaseInvoice::query()
            ->whereDate('invoice_date', $todayDate)
            ->count();

        $purchasesTodayTotal = (float) PurchaseInvoice::query()
            ->whereDate('invoice_date', $todayDate)
            ->sum('grand_total');

        $receivablePaymentToday = (float) CustomerPayment::query()
            ->whereDate('payment_date', $todayDate)
            ->sum('amount_paid');

        $receivableOutstanding = (float) Sale::query()
            ->selectRaw('COALESCE(SUM(GREATEST(grand_total - paid_amount, 0)), 0) as total')
            ->value('total');

        $payableOutstanding = (float) PurchaseInvoice::query()
            ->sum('outstanding_amount');

        $medicineCount = Medicine::query()
            ->where('is_active', true)
            ->count();

        $lowStockCount = DB::query()
            ->fromSub(function ($query) {
                $query->from('medicines')
                    ->leftJoin('stock_batches', 'stock_batches.medicine_id', '=', 'medicines.id')
                    ->where('medicines.is_active', true)
                    ->groupBy(
                        'medicines.id',
                        'medicines.code',
                        'medicines.name',
                        'medicines.minimum_stock'
                    )
                    ->havingRaw('COALESCE(SUM(stock_batches.quantity_balance), 0) <= medicines.minimum_stock')
                    ->selectRaw('medicines.id');
            }, 'low_stock_medicines')
            ->count();

        $expiredSoonCount = StockBatch::query()
            ->where('quantity_balance', '>', 0)
            ->whereDate('expiry_date', '>=', $todayDate)
            ->whereDate('expiry_date', '<=', $expiryLimit)
            ->count();

        $stockValue = (float) StockBatch::query()
            ->selectRaw('COALESCE(SUM(quantity_balance * purchase_price), 0) as total')
            ->value('total');

        $recentSales = Sale::query()
            ->latest('sale_date')
            ->latest('id')
            ->limit(5)
            ->get([
                'id',
                'sale_number',
                'sale_date',
                'customer_name',
                'payment_method',
                'grand_total',
            ]);

        $recentPurchases = PurchaseInvoice::query()
            ->with('supplier:id,name')
            ->latest('invoice_date')
            ->latest('id')
            ->limit(5)
            ->get([
                'id',
                'invoice_number',
                'supplier_id',
                'invoice_date',
                'payment_status',
                'grand_total',
            ]);

        $latestOpnames = StockOpname::query()
            ->withCount('items')
            ->latest('opname_date')
            ->latest('id')
            ->limit(5)
            ->get([
                'id',
                'opname_number',
                'opname_date',
                'status',
            ]);

        $lowStockItems = DB::table('medicines')
            ->leftJoin('stock_batches', 'stock_batches.medicine_id', '=', 'medicines.id')
            ->where('medicines.is_active', true)
            ->groupBy('medicines.id', 'medicines.code', 'medicines.name', 'medicines.small_unit', 'medicines.minimum_stock')
            ->havingRaw('COALESCE(SUM(stock_batches.quantity_balance), 0) <= medicines.minimum_stock')
            ->orderByRaw('COALESCE(SUM(stock_batches.quantity_balance), 0) asc')
            ->limit(6)
            ->get([
                'medicines.code',
                'medicines.name',
                'medicines.small_unit',
                'medicines.minimum_stock',
                DB::raw('COALESCE(SUM(stock_batches.quantity_balance), 0) as stock_total'),
            ]);

        return view('dashboard', [
            'sections' => $sections,
            'todayLabel' => $today->translatedFormat('d M Y'),
            'cards' => [
                [
                    'label' => 'Penjualan hari ini',
                    'value' => $this->formatCurrency($salesTodayTotal),
                    'meta' => $salesTodayCount.' transaksi',
                    'href' => route('penjualan.data-penjualan', ['date_from' => $todayDate, 'date_to' => $todayDate]),
                ],
                [
                    'label' => 'Pembelian hari ini',
                    'value' => $this->formatCurrency($purchasesTodayTotal),
                    'meta' => $purchasesTodayCount.' faktur',
                    'href' => route('pembelian.data-pembelian'),
                ],
                [
                    'label' => 'Piutang berjalan',
                    'value' => $this->formatCurrency($receivableOutstanding),
                    'meta' => 'Bayar hari ini '.$this->formatCurrency($receivablePaymentToday),
                    'href' => route('keuangan.piutang-pelanggan'),
                ],
                [
                    'label' => 'Hutang supplier',
                    'value' => $this->formatCurrency($payableOutstanding),
                    'meta' => 'Tagihan belum lunas',
                    'href' => route('pembelian.hutang-supplier'),
                ],
            ],
            'inventoryCards' => [
                [
                    'label' => 'Obat aktif',
                    'value' => number_format($medicineCount),
                    'meta' => 'Master obat siap transaksi',
                    'href' => route('master-data.data-obat'),
                ],
                [
                    'label' => 'Stok rendah',
                    'value' => number_format($lowStockCount),
                    'meta' => 'Perlu dicek segera',
                    'href' => route('stok-batch.stok-obat', ['stock_state' => 'low']),
                ],
                [
                    'label' => 'Expired <= 90 hari',
                    'value' => number_format($expiredSoonCount),
                    'meta' => 'Batch aktif mendekati expired',
                    'href' => route('stok-batch.stok-per-batch', ['expiry_within_months' => 3]),
                ],
                [
                    'label' => 'Nilai stok',
                    'value' => $this->formatCurrency($stockValue),
                    'meta' => 'Akumulasi saldo batch',
                    'href' => route('stok-batch.stok-obat'),
                ],
            ],
            'recentSales' => $recentSales,
            'recentPurchases' => $recentPurchases,
            'latestOpnames' => $latestOpnames,
            'lowStockItems' => $lowStockItems,
        ]);
    }

    /**
     * Format currency for dashboard cards.
     */
    private function formatCurrency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
