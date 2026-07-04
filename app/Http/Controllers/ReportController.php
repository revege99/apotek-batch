<?php

namespace App\Http\Controllers;

use App\Models\CustomerPayment;
use App\Models\Medicine;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockAdjustmentRecovery;
use App\Models\StockAdjustmentRecoveryPayment;
use App\Models\StockBatch;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Display the purchase report page.
     */
    public function purchase(Request $request): View
    {
        [$dateFrom, $dateTo] = $this->resolveMonthRange($request);
        $search = trim((string) $request->query('search', ''));
        $query = $this->purchaseReportQuery($search, $dateFrom, $dateTo);
        $rows = $query->paginate(20)->withQueryString();
        $returnTotal = $this->purchaseReturnBaseQuery($search, $dateFrom, $dateTo)->sum('total_amount');

        return view('reports.index', [
            ...$this->pageData('laporan.laporan-pembelian'),
            'mode' => 'purchase',
            'rows' => $rows,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'stats' => [
                ['label' => 'Total Faktur', 'value' => number_format((clone $query)->count()), 'tone' => 'slate'],
                ['label' => 'Total Pembelian', 'value' => $this->formatCurrency((float) (clone $query)->sum('grand_total')), 'tone' => 'emerald'],
                ['label' => 'Total Retur', 'value' => $this->formatCurrency((float) $returnTotal), 'tone' => 'amber'],
            ],
        ]);
    }

    /**
     * Display the sales report page.
     */
    public function sale(Request $request): View
    {
        [$dateFrom, $dateTo] = $this->resolveMonthRange($request);
        $search = trim((string) $request->query('search', ''));
        $query = $this->saleReportQuery($search, $dateFrom, $dateTo);
        $rows = $query->paginate(20)->withQueryString();
        $rows->getCollection()->transform(function (Sale $sale): Sale {
            $sale->payment_method_label = $this->salePaymentMethodLabel((string) $sale->payment_method);
            $sale->settlement_status_label = $this->saleSettlementStatus($sale);
            $sale->settlement_date_label = $this->saleSettlementDateLabel($sale);
            $sale->report_outstanding_amount = $this->saleOutstandingAmount($sale);

            return $sale;
        });
        $returnTotal = $this->saleReturnBaseQuery($search, $dateFrom, $dateTo)->sum('total_amount');
        $saleBaseQuery = $this->saleReportQuery($search, $dateFrom, $dateTo);
        $totalSales = (float) (clone $saleBaseQuery)->sum('grand_total');
        $totalSocial = (float) (clone $saleBaseQuery)->sum('social_amount');
        $totalCredit = (float) (clone $saleBaseQuery)
            ->reorder()
            ->where('payment_method', 'credit')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as total_credit')
            ->value('total_credit');

        return view('reports.index', [
            ...$this->pageData('laporan.laporan-penjualan'),
            'mode' => 'sale',
            'rows' => $rows,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'stats' => [
                ['label' => 'Total Penjualan', 'value' => $this->formatCurrency($totalSales), 'tone' => 'emerald'],
                ['label' => 'Total Sosial', 'value' => $this->formatCurrency($totalSocial), 'tone' => 'sky'],
                ['label' => 'Total Kredit', 'value' => $this->formatCurrency($totalCredit), 'tone' => 'amber'],
                ['label' => 'Penjualan Bersih', 'value' => $this->formatCurrency($totalSales - $totalSocial), 'tone' => 'violet'],
                ['label' => 'Retur Penjualan', 'value' => $this->formatCurrency((float) $returnTotal), 'tone' => 'rose'],
            ],
        ]);
    }

    /**
     * Display the cash receipt report page.
     */
    public function cashReceipt(Request $request): View
    {
        [$dateFrom, $dateTo] = $this->resolveMonthRange($request);
        $search = trim((string) $request->query('search', ''));
        $directSalesQuery = $this->cashReceiptSaleBaseQuery($search, $dateFrom, $dateTo);
        $receivablePaymentsQuery = $this->cashReceiptPaymentBaseQuery($search, $dateFrom, $dateTo);
        $adjustmentRecoveriesQuery = $this->cashReceiptAdjustmentRecoveryBaseQuery($search, $dateFrom, $dateTo);

        $saleRows = (clone $directSalesQuery)
            ->get()
            ->map(function (Sale $sale): object {
                return (object) [
                    'receipt_date' => $sale->sale_date,
                    'source_label' => 'Penjualan Langsung',
                    'document_number' => $sale->sale_number,
                    'customer_name' => $sale->customer_name ?: '-',
                    'payment_method_label' => $this->salePaymentMethodLabel((string) $sale->payment_method),
                    'notes' => (float) $sale->social_amount > 0.001 ? 'Penjualan sosial' : 'Pembayaran saat transaksi',
                    'amount' => $this->saleReceivedAmount($sale),
                ];
            });

        $paymentRows = (clone $receivablePaymentsQuery)
            ->get()
            ->map(function (CustomerPayment $payment): object {
                return (object) [
                    'receipt_date' => $payment->payment_date,
                    'source_label' => 'Pembayaran Piutang',
                    'document_number' => $payment->payment_number,
                    'customer_name' => $payment->customer?->name ?: $payment->sale?->customer_name ?: '-',
                    'payment_method_label' => $this->salePaymentMethodLabel((string) $payment->payment_method),
                    'notes' => 'Pelunasan '.$payment->sale?->sale_number,
                    'amount' => (float) $payment->amount_paid,
                ];
            });

        $adjustmentRows = (clone $adjustmentRecoveriesQuery)
            ->get()
            ->map(function (StockAdjustmentRecoveryPayment $payment): object {
                $recovery = $payment->recovery;
                $movement = $recovery?->stockMovement;
                $followUp = $recovery?->followUp;
                $medicineName = $followUp?->opnameItem?->medicine?->name ?: $movement?->medicine?->name;
                $batchNumber = $movement?->stockBatch?->batch_number;
                $documentNumber = $payment->payment_number;

                return (object) [
                    'receipt_date' => $payment->payment_date,
                    'source_label' => 'Penyesuaian Stok',
                    'document_number' => $documentNumber,
                    'customer_name' => $recovery?->employee_name ?: '-',
                    'payment_method_label' => $this->salePaymentMethodLabel((string) $payment->payment_method),
                    'notes' => trim(collect([
                        'Penggantian stok opname',
                        $medicineName,
                        $batchNumber ? 'batch '.$batchNumber : null,
                    ])->filter()->implode(' - ')),
                    'amount' => (float) $payment->amount_paid,
                ];
            });

        $rows = $this->paginateCollection(
            $saleRows
                ->concat($paymentRows)
                ->concat($adjustmentRows)
                ->sortByDesc(fn (object $row): int => $row->receipt_date?->getTimestamp() ?? 0)
                ->values(),
            $request
        );

        $directSalesTotal = (float) (clone $directSalesQuery)
            ->selectRaw('COALESCE(SUM(paid_amount - change_amount), 0) as total_received')
            ->value('total_received');
        $receivablePaymentTotal = (float) (clone $receivablePaymentsQuery)->sum('amount_paid');
        $adjustmentRecoveryTotal = (float) (clone $adjustmentRecoveriesQuery)->sum('amount_paid');

        return view('reports.index', [
            ...$this->pageData('laporan.laporan-penerimaan-kas'),
            'mode' => 'cash_receipt',
            'rows' => $rows,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'stats' => [
                ['label' => 'Penjualan Langsung', 'value' => $this->formatCurrency($directSalesTotal), 'tone' => 'emerald'],
                ['label' => 'Pembayaran Piutang', 'value' => $this->formatCurrency($receivablePaymentTotal), 'tone' => 'sky'],
                ['label' => 'Penyesuaian Stok', 'value' => $this->formatCurrency($adjustmentRecoveryTotal), 'tone' => 'amber'],
                ['label' => 'Total Penerimaan Kas', 'value' => $this->formatCurrency($directSalesTotal + $receivablePaymentTotal + $adjustmentRecoveryTotal), 'tone' => 'violet'],
            ],
        ]);
    }

    /**
     * Display the writeoff-loss report page.
     */
    public function writeoffLoss(Request $request): View
    {
        [$dateFrom, $dateTo] = $this->resolveMonthRange($request);
        $search = trim((string) $request->query('search', ''));
        $query = $this->writeoffLossBaseQuery($search, $dateFrom, $dateTo);
        $rows = $query->paginate(20)->withQueryString();

        $statsRow = $this->writeoffLossStatsQuery($search, $dateFrom, $dateTo)
            ->selectRaw('
                COUNT(DISTINCT stock_opnames.id) as document_count,
                COUNT(DISTINCT stock_adjustment_follow_ups.id) as follow_up_count,
                COALESCE(SUM(stock_movements.quantity_out), 0) as total_quantity,
                COALESCE(SUM(stock_movements.quantity_out * stock_movements.unit_cost), 0) as total_value
            ')
            ->first();

        return view('reports.index', [
            ...$this->pageData('laporan.laporan-hilang-biasa'),
            'mode' => 'writeoff_loss',
            'rows' => $rows,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'stats' => [
                ['label' => 'Dokumen Opname', 'value' => number_format((int) ($statsRow->document_count ?? 0)), 'tone' => 'slate'],
                ['label' => 'Tindak Lanjut', 'value' => number_format((int) ($statsRow->follow_up_count ?? 0)), 'tone' => 'amber'],
                ['label' => 'Qty Hilang', 'value' => $this->formatQuantity((float) ($statsRow->total_quantity ?? 0)), 'tone' => 'rose'],
                ['label' => 'Nilai Hilang', 'value' => $this->formatCurrency((float) ($statsRow->total_value ?? 0)), 'tone' => 'violet'],
            ],
        ]);
    }

    /**
     * Display the stock report page.
     */
    public function stock(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $query = $this->stockReportQuery($search);
        $rows = $query->paginate(20)->withQueryString();
        $stockSummary = $this->stockSummary($search);

        return view('reports.index', [
            ...$this->pageData('laporan.laporan-stok'),
            'mode' => 'stock',
            'rows' => $rows,
            'search' => $search,
            'dateFrom' => '',
            'dateTo' => '',
            'stats' => [
                ['label' => 'Kode Obat', 'value' => number_format((int) $stockSummary['medicine_count']), 'tone' => 'slate'],
                ['label' => 'Total Stok', 'value' => number_format((float) $stockSummary['total_stock'], 0, ',', '.'), 'tone' => 'emerald'],
                ['label' => 'Nilai Stok', 'value' => $this->formatCurrency((float) $stockSummary['stock_value']), 'tone' => 'sky'],
            ],
        ]);
    }

    /**
     * Display the expiry report page.
     */
    public function expired(Request $request): View
    {
        [$dateFrom, $dateTo] = $this->resolveExpiryRange($request);
        $search = trim((string) $request->query('search', ''));
        $query = $this->expiredReportQuery($search, $dateFrom, $dateTo);
        $rows = $query->paginate(20)->withQueryString();

        $expiredCount = StockBatch::query()
            ->where('quantity_balance', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now()->toDateString())
            ->count();

        return view('reports.index', [
            ...$this->pageData('laporan.laporan-expired'),
            'mode' => 'expired',
            'rows' => $rows,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'stats' => [
                ['label' => 'Batch Tampil', 'value' => number_format((clone $query)->count()), 'tone' => 'slate'],
                ['label' => 'Sudah Expired', 'value' => number_format($expiredCount), 'tone' => 'rose'],
                ['label' => 'Nilai Batch', 'value' => $this->formatCurrency((float) (clone $query)->sum(DB::raw('quantity_balance * purchase_price'))), 'tone' => 'amber'],
            ],
        ]);
    }

    /**
     * Display the payable report page.
     */
    public function payable(Request $request): View
    {
        [$dateFrom, $dateTo] = $this->resolveMonthRange($request);
        $search = trim((string) $request->query('search', ''));
        $query = $this->payableReportQuery($search, $dateFrom, $dateTo);
        $rows = $query->paginate(20)->withQueryString();

        $overdueCount = $this->payableReportQuery($search, $dateFrom, $dateTo)
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        return view('reports.index', [
            ...$this->pageData('laporan.laporan-hutang'),
            'mode' => 'payable',
            'rows' => $rows,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'stats' => [
                ['label' => 'Faktur Hutang', 'value' => number_format((clone $query)->count()), 'tone' => 'slate'],
                ['label' => 'Total Hutang', 'value' => $this->formatCurrency((float) (clone $query)->sum('outstanding_amount')), 'tone' => 'amber'],
                ['label' => 'Lewat Jatuh Tempo', 'value' => number_format($overdueCount), 'tone' => 'rose'],
            ],
        ]);
    }

    /**
     * Display the receivable report page.
     */
    public function receivable(Request $request): View
    {
        [$dateFrom, $dateTo] = $this->resolveMonthRange($request);
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', 'all'));
        $query = $this->receivableReportQuery($search, $dateFrom, $dateTo, $status);
        $rows = $query->paginate(20)->withQueryString();
        $rows->getCollection()->transform(function (Sale $sale): Sale {
            $sale->payment_method_label = $this->salePaymentMethodLabel((string) $sale->payment_method);
            $sale->settlement_status_label = $this->saleOutstandingAmount($sale) > 0.001 ? 'Belum Lunas' : 'Lunas';
            $sale->settlement_date_label = $this->saleSettlementDateLabel($sale);
            $sale->report_outstanding_amount = $this->saleOutstandingAmount($sale);

            return $sale;
        });

        $invoiceBaseQuery = $this->receivableInvoiceBaseQuery($search, $dateFrom, $dateTo);
        $invoiceCount = (clone $invoiceBaseQuery)->count();
        $unpaidCount = (clone $invoiceBaseQuery)
            ->whereRaw('grand_total - paid_amount > 0.001')
            ->count();
        $paidCount = (clone $invoiceBaseQuery)
            ->whereRaw('grand_total - paid_amount <= 0.001')
            ->count();
        $totalReceivable = (float) (clone $invoiceBaseQuery)
            ->whereRaw('grand_total - paid_amount > 0.001')
            ->selectRaw('COALESCE(SUM(grand_total - paid_amount), 0) as total_receivable')
            ->value('total_receivable');

        return view('reports.index', [
            ...$this->pageData('laporan.laporan-piutang'),
            'mode' => 'receivable',
            'rows' => $rows,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'statusFilter' => in_array($status, ['all', 'paid', 'unpaid'], true) ? $status : 'all',
            'stats' => [
                ['label' => 'Faktur Kredit', 'value' => number_format($invoiceCount), 'tone' => 'slate'],
                ['label' => 'Belum Lunas', 'value' => number_format($unpaidCount), 'tone' => 'amber'],
                ['label' => 'Sudah Lunas', 'value' => number_format($paidCount), 'tone' => 'sky'],
                ['label' => 'Nilai Kredit', 'value' => $this->formatCurrency((float) (clone $invoiceBaseQuery)->sum('grand_total')), 'tone' => 'violet'],
                ['label' => 'Total Piutang', 'value' => $this->formatCurrency($totalReceivable), 'tone' => 'emerald'],
            ],
        ]);
    }

    /**
     * Display the profit and loss report page.
     */
    public function profitLoss(Request $request): View
    {
        [$dateFrom, $dateTo] = $this->resolveMonthRange($request);
        $search = trim((string) $request->query('search', ''));
        $query = $this->profitLossQuery($search, $dateFrom, $dateTo);
        $rows = $query->paginate(20)->withQueryString();
        $summary = $this->profitLossSummary($search, $dateFrom, $dateTo);

        return view('reports.index', [
            ...$this->pageData('laporan.laporan-laba-rugi'),
            'mode' => 'profit_loss',
            'rows' => $rows,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'stats' => [
                ['label' => 'Penjualan Kotor', 'value' => $this->formatCurrency((float) $summary['gross_sales']), 'tone' => 'emerald'],
                ['label' => 'Retur Penjualan', 'value' => $this->formatCurrency((float) $summary['sales_returns']), 'tone' => 'rose'],
                ['label' => 'Penjualan Bersih', 'value' => $this->formatCurrency((float) $summary['net_sales']), 'tone' => 'sky'],
                ['label' => 'HPP Bersih', 'value' => $this->formatCurrency((float) $summary['net_cogs']), 'tone' => 'amber'],
                ['label' => 'Laba Kotor', 'value' => $this->formatCurrency((float) $summary['gross_profit']), 'tone' => 'violet'],
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Build the purchase report query.
     */
    private function purchaseReportQuery(string $search, string $dateFrom, string $dateTo): Builder
    {
        return PurchaseInvoice::query()
            ->with(['supplier:id,name'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn (Builder $supplierQuery) => $supplierQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->whereDate('invoice_date', '>=', $dateFrom)
            ->whereDate('invoice_date', '<=', $dateTo)
            ->latest('invoice_date')
            ->latest('id');
    }

    /**
     * Build the purchase return base query.
     */
    private function purchaseReturnBaseQuery(string $search, string $dateFrom, string $dateTo): Builder
    {
        return PurchaseReturn::query()
            ->with(['supplier:id,name', 'purchaseInvoice:id,invoice_number'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('return_number', 'like', "%{$search}%")
                        ->orWhereHas('purchaseInvoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('invoice_number', 'like', "%{$search}%"))
                        ->orWhereHas('supplier', fn (Builder $supplierQuery) => $supplierQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->whereDate('return_date', '>=', $dateFrom)
            ->whereDate('return_date', '<=', $dateTo);
    }

    /**
     * Build the sale report query.
     */
    private function saleReportQuery(string $search, string $dateFrom, string $dateTo): Builder
    {
        return Sale::query()
            ->with([
                'customer:id,name',
                'customerGroup:id,name',
                'customerPayments:id,sale_id,payment_date',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('sale_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhereHas('customerGroup', fn (Builder $groupQuery) => $groupQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->whereDate('sale_date', '>=', $dateFrom)
            ->whereDate('sale_date', '<=', $dateTo)
            ->latest('sale_date')
            ->latest('id');
    }

    /**
     * Build the sale return base query.
     */
    private function saleReturnBaseQuery(string $search, string $dateFrom, string $dateTo): Builder
    {
        return SaleReturn::query()
            ->with(['sale:id,sale_number,customer_name'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('return_number', 'like', "%{$search}%")
                        ->orWhereHas('sale', function (Builder $saleQuery) use ($search) {
                            $saleQuery
                                ->where('sale_number', 'like', "%{$search}%")
                                ->orWhere('customer_name', 'like', "%{$search}%");
                        });
                });
            })
            ->whereDate('return_date', '>=', $dateFrom)
            ->whereDate('return_date', '<=', $dateTo);
    }

    /**
     * Build the base query for direct sale cash receipts.
     */
    private function cashReceiptSaleBaseQuery(string $search, string $dateFrom, string $dateTo): Builder
    {
        return Sale::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $saleQuery) use ($search) {
                    $saleQuery
                        ->where('sale_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%");
                });
            })
            ->where('payment_method', '!=', 'credit')
            ->whereRaw('paid_amount - change_amount > 0.001')
            ->whereDate('sale_date', '>=', $dateFrom)
            ->whereDate('sale_date', '<=', $dateTo);
    }

    /**
     * Build the base query for receivable payment cash receipts.
     */
    private function cashReceiptPaymentBaseQuery(string $search, string $dateFrom, string $dateTo): Builder
    {
        return CustomerPayment::query()
            ->with([
                'customer:id,name',
                'sale:id,sale_number,customer_name',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $paymentQuery) use ($search) {
                    $paymentQuery
                        ->where('payment_number', 'like', "%{$search}%")
                        ->orWhereHas('sale', function (Builder $saleQuery) use ($search) {
                            $saleQuery
                                ->where('sale_number', 'like', "%{$search}%")
                                ->orWhere('customer_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->whereDate('payment_date', '>=', $dateFrom)
            ->whereDate('payment_date', '<=', $dateTo);
    }

    /**
     * Build the base query for employee reimbursement cash receipts.
     */
    private function cashReceiptAdjustmentRecoveryBaseQuery(string $search, string $dateFrom, string $dateTo): Builder
    {
        return StockAdjustmentRecoveryPayment::query()
            ->with([
                'recovery:id,stock_movement_id,stock_adjustment_follow_up_id,employee_name',
                'recovery.stockMovement:id,medicine_id,stock_batch_id',
                'recovery.stockMovement.medicine:id,name',
                'recovery.stockMovement.stockBatch:id,batch_number',
                'recovery.followUp:id,stock_opname_item_id,adjustment_number',
                'recovery.followUp.opnameItem:id,medicine_id',
                'recovery.followUp.opnameItem.medicine:id,name',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $paymentQuery) use ($search) {
                    $paymentQuery
                        ->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('reference_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('recovery', fn (Builder $recoveryQuery) => $recoveryQuery->where('employee_name', 'like', "%{$search}%"))
                        ->orWhereHas('recovery.stockMovement.medicine', fn (Builder $medicineQuery) => $medicineQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('recovery.stockMovement.stockBatch', fn (Builder $batchQuery) => $batchQuery->where('batch_number', 'like', "%{$search}%"))
                        ->orWhereHas('recovery.followUp.opnameItem.medicine', fn (Builder $medicineQuery) => $medicineQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('recovery.followUp', fn (Builder $followUpQuery) => $followUpQuery->where('adjustment_number', 'like', "%{$search}%"));
                });
            })
            ->where('amount_paid', '>', 0.001)
            ->whereDate('payment_date', '>=', $dateFrom)
            ->whereDate('payment_date', '<=', $dateTo);
    }

    /**
     * Build the stock report query grouped by medicine.
     */
    private function stockReportQuery(string $search): Builder
    {
        return Medicine::query()
            ->leftJoin('stock_batches', 'stock_batches.medicine_id', '=', 'medicines.id')
            ->leftJoin('principals', 'medicines.principal_id', '=', 'principals.id')
            ->where('medicines.is_active', true)
            ->selectRaw('
                medicines.id as medicine_id,
                medicines.code,
                medicines.name,
                medicines.small_unit,
                principals.name as principal_name,
                COUNT(CASE WHEN stock_batches.quantity_balance > 0 THEN 1 END) as batch_count,
                COALESCE(SUM(CASE WHEN stock_batches.quantity_balance > 0 THEN stock_batches.quantity_balance ELSE 0 END), 0) as total_stock,
                COALESCE(SUM(CASE WHEN stock_batches.quantity_balance > 0 THEN stock_batches.quantity_balance * stock_batches.purchase_price ELSE 0 END), 0) as stock_value,
                MIN(CASE WHEN stock_batches.quantity_balance > 0 THEN stock_batches.expiry_date END) as nearest_expiry
            ')
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('medicines.code', 'like', "%{$search}%")
                        ->orWhere('medicines.name', 'like', "%{$search}%")
                        ->orWhere('principals.name', 'like', "%{$search}%");
                });
            })
            ->groupBy(
                'medicines.id',
                'medicines.code',
                'medicines.name',
                'medicines.small_unit',
                'principals.name',
            )
            ->orderBy('medicines.name');
    }

    /**
     * Build aggregate stock totals for the report cards.
     *
     * @return array<string, float|int>
     */
    private function stockSummary(string $search): array
    {
        $baseQuery = StockBatch::query()
            ->join('medicines', 'medicines.id', '=', 'stock_batches.medicine_id')
            ->leftJoin('principals', 'principals.id', '=', 'medicines.principal_id')
            ->where('medicines.is_active', true)
            ->where('stock_batches.quantity_balance', '>', 0)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('medicines.code', 'like', "%{$search}%")
                        ->orWhere('medicines.name', 'like', "%{$search}%")
                        ->orWhere('principals.name', 'like', "%{$search}%");
                });
            });

        return [
            'medicine_count' => (clone $baseQuery)->distinct('stock_batches.medicine_id')->count('stock_batches.medicine_id'),
            'total_stock' => (float) (clone $baseQuery)->sum('stock_batches.quantity_balance'),
            'stock_value' => (float) (clone $baseQuery)->selectRaw('COALESCE(SUM(stock_batches.quantity_balance * stock_batches.purchase_price), 0) as stock_value')->value('stock_value'),
        ];
    }

    /**
     * Build the expiry report query.
     */
    private function expiredReportQuery(string $search, string $dateFrom, string $dateTo): Builder
    {
        return StockBatch::query()
            ->with([
                'medicine:id,code,name,small_unit,principal_id',
                'medicine.principal:id,name',
            ])
            ->where('quantity_balance', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $dateFrom)
            ->whereDate('expiry_date', '<=', $dateTo)
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('batch_number', 'like', "%{$search}%")
                        ->orWhereHas('medicine', function (Builder $medicineQuery) use ($search) {
                            $medicineQuery
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhereHas('principal', fn (Builder $principalQuery) => $principalQuery->where('name', 'like', "%{$search}%"));
                        });
                });
            })
            ->orderBy('expiry_date')
            ->orderBy('batch_number');
    }

    /**
     * Build the payable report query.
     */
    private function payableReportQuery(string $search, string $dateFrom, string $dateTo): Builder
    {
        return PurchaseInvoice::query()
            ->with(['supplier:id,name'])
            ->where('outstanding_amount', '>', 0.001)
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn (Builder $supplierQuery) => $supplierQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->whereDate('invoice_date', '>=', $dateFrom)
            ->whereDate('invoice_date', '<=', $dateTo)
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderByDesc('id');
    }

    /**
     * Build grouped customer receivable report query.
     */
    private function receivableReportQuery(string $search, string $dateFrom, string $dateTo, string $status = 'all'): Builder
    {
        return $this->receivableInvoiceBaseQuery($search, $dateFrom, $dateTo)
            ->when($status === 'unpaid', fn (Builder $query) => $query->whereRaw('grand_total - paid_amount > 0.001'))
            ->when($status === 'paid', fn (Builder $query) => $query->whereRaw('grand_total - paid_amount <= 0.001'))
            ->latest('sale_date')
            ->latest('id');
    }

    /**
     * Build the base query for receivable invoices.
     */
    private function receivableInvoiceBaseQuery(string $search, string $dateFrom, string $dateTo): Builder
    {
        return Sale::query()
            ->with(['customerPayments:id,sale_id,payment_date'])
            ->whereNotNull('customer_id')
            ->where('payment_method', 'credit')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $saleQuery) use ($search) {
                    $saleQuery
                        ->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('sale_number', 'like', "%{$search}%");
                });
            })
            ->whereDate('sale_date', '>=', $dateFrom)
            ->whereDate('sale_date', '<=', $dateTo);
    }

    /**
     * Resolve the display payment method label for reports.
     */
    private function salePaymentMethodLabel(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'credit' => 'Kredit',
            'transfer' => 'Transfer',
            'qris' => 'QRIS',
            'debit' => 'Debit',
            default => 'Tunai',
        };
    }

    /**
     * Resolve the settlement status label for the sales report.
     */
    private function saleSettlementStatus(Sale $sale): string
    {
        if ((float) $sale->social_amount > 0.001) {
            return 'Sosial';
        }

        return $this->saleOutstandingAmount($sale) > 0.001
            ? 'Belum Lunas'
            : 'Lunas';
    }

    /**
     * Resolve the settlement date label for the sales report.
     */
    private function saleSettlementDateLabel(Sale $sale): string
    {
        if ((float) $sale->social_amount > 0.001 || $sale->payment_method !== 'credit') {
            return $sale->sale_date?->format('d/m/Y H:i') ?? '-';
        }

        if ($this->saleOutstandingAmount($sale) > 0.001) {
            return '-';
        }

        $settlementDate = $sale->customerPayments
            ->sortByDesc('payment_date')
            ->first()?->payment_date;

        return $settlementDate?->format('d/m/Y H:i') ?? '-';
    }

    /**
     * Calculate remaining unpaid balance for a sale.
     */
    private function saleOutstandingAmount(Sale $sale): float
    {
        if ($sale->payment_method !== 'credit') {
            return 0;
        }

        return max(round((float) $sale->grand_total - (float) $sale->paid_amount, 2), 0);
    }

    /**
     * Calculate the actual amount retained from a direct sale.
     */
    private function saleReceivedAmount(Sale $sale): float
    {
        return max(round((float) $sale->paid_amount - (float) $sale->change_amount, 2), 0);
    }

    /**
     * Build the profit and loss query grouped by sale.
     */
    private function profitLossQuery(string $search, string $dateFrom, string $dateTo): Builder
    {
        $saleReturnSubquery = DB::table('sale_returns')
            ->join('sale_return_items', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->selectRaw('
                sale_returns.sale_id,
                COALESCE(SUM(sale_return_items.line_total), 0) as return_total,
                COALESCE(SUM(sale_return_items.quantity * sale_items.unit_cost), 0) as return_cogs
            ')
            ->whereDate('sale_returns.return_date', '>=', $dateFrom)
            ->whereDate('sale_returns.return_date', '<=', $dateTo)
            ->groupBy('sale_returns.sale_id');

        return Sale::query()
            ->leftJoinSub($saleReturnSubquery, 'sale_return_summary', function ($join) {
                $join->on('sale_return_summary.sale_id', '=', 'sales.id');
            })
            ->selectRaw('
                sales.id,
                sales.sale_number,
                sales.sale_date,
                sales.customer_name,
                sales.payment_method,
                sales.social_amount,
                sales.grand_total as gross_sales,
                COALESCE(sale_return_summary.return_total, 0) as sales_returns,
                (sales.grand_total - sales.social_amount - COALESCE(sale_return_summary.return_total, 0)) as net_sales,
                COALESCE((
                    SELECT SUM(sale_items.quantity * sale_items.unit_cost)
                    FROM sale_items
                    WHERE sale_items.sale_id = sales.id
                ), 0) as gross_cogs,
                (COALESCE((
                    SELECT SUM(sale_items.quantity * sale_items.unit_cost)
                    FROM sale_items
                    WHERE sale_items.sale_id = sales.id
                ), 0) - COALESCE(sale_return_summary.return_cogs, 0)) as net_cogs,
                (
                    (sales.grand_total - sales.social_amount - COALESCE(sale_return_summary.return_total, 0))
                    - (COALESCE((
                        SELECT SUM(sale_items.quantity * sale_items.unit_cost)
                        FROM sale_items
                        WHERE sale_items.sale_id = sales.id
                    ), 0) - COALESCE(sale_return_summary.return_cogs, 0))
                ) as gross_profit
            ')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $saleQuery) use ($search) {
                    $saleQuery
                        ->where('sales.sale_number', 'like', "%{$search}%")
                        ->orWhere('sales.customer_name', 'like', "%{$search}%");
                });
            })
            ->whereDate('sales.sale_date', '>=', $dateFrom)
            ->whereDate('sales.sale_date', '<=', $dateTo)
            ->orderByDesc('sales.sale_date')
            ->orderByDesc('sales.id');
    }

    /**
     * Build summary totals for the profit and loss report.
     *
     * @return array<string, float>
     */
    private function profitLossSummary(string $search, string $dateFrom, string $dateTo): array
    {
        $saleQuery = $this->saleReportQuery($search, $dateFrom, $dateTo);
        $grossSales = (float) (clone $saleQuery)->sum('grand_total');
        $socialTotal = (float) (clone $saleQuery)->sum('social_amount');
        $salesReturns = (float) $this->saleReturnBaseQuery($search, $dateFrom, $dateTo)->sum('total_amount');
        $grossCogs = (float) DB::table('sales')
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($saleQuery) use ($search) {
                    $saleQuery
                        ->where('sales.sale_number', 'like', "%{$search}%")
                        ->orWhere('sales.customer_name', 'like', "%{$search}%");
                });
            })
            ->whereDate('sales.sale_date', '>=', $dateFrom)
            ->whereDate('sales.sale_date', '<=', $dateTo)
            ->selectRaw('COALESCE(SUM(sale_items.quantity * sale_items.unit_cost), 0) as gross_cogs')
            ->value('gross_cogs');
        $returnCogs = (float) DB::table('sale_returns')
            ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->join('sale_return_items', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($saleQuery) use ($search) {
                    $saleQuery
                        ->where('sales.sale_number', 'like', "%{$search}%")
                        ->orWhere('sales.customer_name', 'like', "%{$search}%");
                });
            })
            ->whereDate('sale_returns.return_date', '>=', $dateFrom)
            ->whereDate('sale_returns.return_date', '<=', $dateTo)
            ->selectRaw('COALESCE(SUM(sale_return_items.quantity * sale_items.unit_cost), 0) as return_cogs')
            ->value('return_cogs');

        $netSales = $grossSales - $socialTotal - $salesReturns;
        $netCogs = $grossCogs - $returnCogs;
        $grossProfit = $netSales - $netCogs;

        return [
            'gross_sales' => $grossSales,
            'sales_returns' => $salesReturns,
            'net_sales' => $netSales,
            'net_cogs' => $netCogs,
            'gross_profit' => $grossProfit,
        ];
    }

    /**
     * Resolve the default start/end dates for monthly reports.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveMonthRange(Request $request): array
    {
        $today = now();

        return [
            trim((string) $request->query('date_from', $today->copy()->startOfMonth()->toDateString())),
            trim((string) $request->query('date_to', $today->toDateString())),
        ];
    }

    /**
     * Paginate an in-memory collection while preserving the current query string.
     */
    private function paginateCollection(iterable $items, Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $collection = collect($items)->values();
        $page = max((int) $request->query('page', 1), 1);

        return new LengthAwarePaginator(
            $collection->slice(($page - 1) * $perPage, $perPage)->values(),
            $collection->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    /**
     * Resolve the default start/end dates for expiry reports.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveExpiryRange(Request $request): array
    {
        $today = now();

        return [
            trim((string) $request->query('date_from', $today->toDateString())),
            trim((string) $request->query('date_to', $today->copy()->addMonths(6)->toDateString())),
        ];
    }

    /**
     * Build the base query for processed writeoff-loss movements.
     */
    private function writeoffLossBaseQuery(string $search, string $dateFrom, string $dateTo): Builder
    {
        return StockMovement::query()
            ->select([
                'stock_movements.id',
                'stock_movements.movement_date',
                'stock_movements.quantity_out',
                'stock_movements.unit_cost',
                'stock_movements.notes',
                'stock_adjustment_follow_ups.adjustment_number',
                'stock_opnames.opname_number',
                'medicines.code as medicine_code',
                'medicines.name as medicine_name',
                'medicines.small_unit',
                'stock_batches.batch_number',
                'storage_locations.name as location_name',
                'processors.name as processed_by_name',
            ])
            ->join('stock_opname_items', function ($join): void {
                $join
                    ->on('stock_opname_items.id', '=', 'stock_movements.reference_id')
                    ->where('stock_movements.reference_table', '=', 'stock_opname_items');
            })
            ->join('stock_adjustment_follow_ups', 'stock_adjustment_follow_ups.stock_opname_item_id', '=', 'stock_opname_items.id')
            ->join('stock_opnames', 'stock_opnames.id', '=', 'stock_opname_items.stock_opname_id')
            ->leftJoin('medicines', 'medicines.id', '=', 'stock_movements.medicine_id')
            ->leftJoin('stock_batches', 'stock_batches.id', '=', 'stock_movements.stock_batch_id')
            ->leftJoin('storage_locations', 'storage_locations.id', '=', 'stock_movements.storage_location_id')
            ->leftJoin('users as processors', 'processors.id', '=', 'stock_adjustment_follow_ups.processed_by')
            ->where('stock_movements.movement_type', 'stock_opname_loss')
            ->where('stock_adjustment_follow_ups.status', 'applied')
            ->where('stock_adjustment_follow_ups.settlement_type', 'writeoff')
            ->whereDate('stock_movements.movement_date', '>=', $dateFrom)
            ->whereDate('stock_movements.movement_date', '<=', $dateTo)
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('stock_adjustment_follow_ups.adjustment_number', 'like', "%{$search}%")
                        ->orWhere('stock_opnames.opname_number', 'like', "%{$search}%")
                        ->orWhere('medicines.code', 'like', "%{$search}%")
                        ->orWhere('medicines.name', 'like', "%{$search}%")
                        ->orWhere('stock_batches.batch_number', 'like', "%{$search}%")
                        ->orWhere('storage_locations.name', 'like', "%{$search}%")
                        ->orWhere('processors.name', 'like', "%{$search}%");
                });
            })
            ->latest('stock_movements.movement_date')
            ->latest('stock_movements.id');
    }

    /**
     * Build the stats query for processed writeoff-loss movements.
     */
    private function writeoffLossStatsQuery(string $search, string $dateFrom, string $dateTo): Builder
    {
        return StockMovement::query()
            ->join('stock_opname_items', function ($join): void {
                $join
                    ->on('stock_opname_items.id', '=', 'stock_movements.reference_id')
                    ->where('stock_movements.reference_table', '=', 'stock_opname_items');
            })
            ->join('stock_adjustment_follow_ups', 'stock_adjustment_follow_ups.stock_opname_item_id', '=', 'stock_opname_items.id')
            ->join('stock_opnames', 'stock_opnames.id', '=', 'stock_opname_items.stock_opname_id')
            ->leftJoin('medicines', 'medicines.id', '=', 'stock_movements.medicine_id')
            ->leftJoin('stock_batches', 'stock_batches.id', '=', 'stock_movements.stock_batch_id')
            ->leftJoin('storage_locations', 'storage_locations.id', '=', 'stock_movements.storage_location_id')
            ->leftJoin('users as processors', 'processors.id', '=', 'stock_adjustment_follow_ups.processed_by')
            ->where('stock_movements.movement_type', 'stock_opname_loss')
            ->where('stock_adjustment_follow_ups.status', 'applied')
            ->where('stock_adjustment_follow_ups.settlement_type', 'writeoff')
            ->whereDate('stock_movements.movement_date', '>=', $dateFrom)
            ->whereDate('stock_movements.movement_date', '<=', $dateTo)
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('stock_adjustment_follow_ups.adjustment_number', 'like', "%{$search}%")
                        ->orWhere('stock_opnames.opname_number', 'like', "%{$search}%")
                        ->orWhere('medicines.code', 'like', "%{$search}%")
                        ->orWhere('medicines.name', 'like', "%{$search}%")
                        ->orWhere('stock_batches.batch_number', 'like', "%{$search}%")
                        ->orWhere('storage_locations.name', 'like', "%{$search}%")
                        ->orWhere('processors.name', 'like', "%{$search}%");
                });
            });
    }

    /**
     * Build page metadata for report pages.
     *
     * @return array<string, mixed>
     */
    private function pageData(string $routeName): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $item): bool => $item['label'] === 'Laporan');

        $siblings = $section['children'] ?? [];
        $page = collect($siblings)->firstWhere('route', $routeName);

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Laporan',
            'siblings' => $siblings,
        ];
    }

    /**
     * Format currency values.
     */
    private function formatCurrency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    /**
     * Format quantity values without decimals.
     */
    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 0, ',', '.');
    }

    /**
     * Calculate remaining days until expiry.
     */
    private function expiryCountdown(?string $date): string
    {
        if ($date === null || $date === '') {
            return '-';
        }

        $days = Carbon::parse($date)->startOfDay()->diffInDays(now()->startOfDay(), false);

        if ($days > 0) {
            return 'Lewat '.$days.' hari';
        }

        if ($days === 0) {
            return 'Hari ini';
        }

        return abs($days).' hari lagi';
    }
}
