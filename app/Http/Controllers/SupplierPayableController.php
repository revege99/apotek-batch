<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class SupplierPayableController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $suppliers = $this->supplierPayableQuery($search)
            ->paginate(12)
            ->withQueryString();

        return view('supplier-payables.index', [
            ...$this->pageData('keuangan.pembayaran-hutang'),
            'suppliers' => $suppliers,
            'search' => $search,
            'stats' => [
                'invoice_count' => $this->outstandingInvoiceQuery()->count(),
                'total_payable' => (float) $this->outstandingInvoiceQuery()->sum('outstanding_amount'),
            ],
        ]);
    }

    public function show(Supplier $supplier): View
    {
        $invoices = PurchaseInvoice::query()
            ->with([
                'items:id,purchase_invoice_id,medicine_id,medicine_code_snapshot,medicine_name_snapshot,medicine_unit_snapshot,batch_number,quantity,unit_price,line_total',
                'items.medicine:id,name,small_unit',
            ])
            ->where('supplier_id', $supplier->id)
            ->where('payment_method', 'credit')
            ->where('outstanding_amount', '>', 0.001)
            ->orderBy('due_date')
            ->orderBy('invoice_date')
            ->get()
            ->map(fn (PurchaseInvoice $invoice): array => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date?->translatedFormat('d M Y') ?? '-',
                'due_date' => $invoice->due_date?->translatedFormat('d M Y') ?? '-',
                'grand_total' => $this->formatCurrency((float) $invoice->grand_total),
                'paid_amount' => $this->formatCurrency((float) $invoice->paid_amount),
                'outstanding_amount' => $this->formatCurrency((float) $invoice->outstanding_amount),
                'outstanding_value' => (float) $invoice->outstanding_amount,
                'action' => route('keuangan.pembayaran-hutang.bayar', $invoice),
                'items' => $invoice->items->map(fn ($item): array => [
                    'medicine_name' => $item->medicine?->name ?: '-',
                    'batch_number' => $item->batch_number ?: 'Tanpa batch',
                    'quantity' => number_format((float) $item->quantity, 0, ',', '.'),
                    'unit' => $item->medicine?->small_unit ?: '-',
                    'unit_price' => $this->formatCurrency((float) $item->unit_price),
                    'line_total' => $this->formatCurrency((float) $item->line_total),
                ])->values()->all(),
            ])
            ->values();

        return view('supplier-payables.show', [
            ...$this->pageData('keuangan.pembayaran-hutang'),
            'supplier' => $supplier,
            'detail' => [
                'supplier_name' => $supplier->name,
                'supplier_address' => $supplier->address ?: '-',
                'invoice_count' => number_format($invoices->count()),
                'total_payable' => $this->formatCurrency((float) $invoices->sum('outstanding_value')),
                'invoices' => $invoices->all(),
            ],
            'paymentMethods' => $this->paymentMethods(),
            'todayDate' => now()->toDateString(),
        ]);
    }

    public function storePayment(Request $request, PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,transfer,qris,debit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('toast', ['type' => 'error', 'message' => 'Periksa kembali pembayaran hutang supplier.']);
        }

        $validated = $validator->validated();

        try {
            $payment = DB::transaction(function () use ($request, $purchaseInvoice, $validated): SupplierPayment {
                $invoice = PurchaseInvoice::query()
                    ->lockForUpdate()
                    ->findOrFail($purchaseInvoice->id);

                if ($invoice->payment_method !== 'credit') {
                    throw new RuntimeException('Faktur pembelian ini bukan transaksi kredit.');
                }

                $outstanding = round((float) $invoice->outstanding_amount, 2);

                if ($outstanding <= 0.001) {
                    throw new RuntimeException('Hutang faktur ini sudah lunas.');
                }

                $amount = round((float) $validated['amount'], 2);

                if (abs($amount - $outstanding) > 0.001) {
                    throw new RuntimeException('Nominal pelunasan harus sama dengan sisa hutang faktur.');
                }

                $payment = SupplierPayment::query()->create([
                    'payment_number' => $this->nextPaymentNumber(),
                    'supplier_id' => $invoice->supplier_id,
                    'payment_date' => $validated['payment_date'],
                    'payment_method' => $validated['payment_method'],
                    'reference_number' => ($validated['reference_number'] ?? null) ?: null,
                    'total_amount' => $amount,
                    'notes' => ($validated['notes'] ?? null) ?: null,
                    'created_by' => $request->user()?->id,
                ]);

                $payment->allocations()->create([
                    'purchase_invoice_id' => $invoice->id,
                    'amount_paid' => $amount,
                ]);

                $invoice->update([
                    'paid_amount' => round((float) $invoice->paid_amount + $amount, 2),
                    'outstanding_amount' => 0,
                    'payment_status' => 'paid',
                ]);

                return $payment;
            });
        } catch (RuntimeException $exception) {
            return back()->with('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return redirect()
            ->route('keuangan.pembayaran-hutang.show', $purchaseInvoice->supplier_id)
            ->with('toast', [
                'type' => 'success',
                'message' => 'Pembayaran hutang '.$payment->payment_number.' berhasil disimpan.',
            ]);
    }

    public function history(Request $request): View
    {
        $today = now()->toDateString();
        $search = trim((string) $request->query('search', ''));
        $dateFrom = trim((string) $request->query('date_from', $today));
        $dateTo = trim((string) $request->query('date_to', $today));

        $baseQuery = $this->supplierPaymentBaseQuery($search, $dateFrom, $dateTo);
        $suppliers = (clone $baseQuery)
            ->join('suppliers', 'supplier_payments.supplier_id', '=', 'suppliers.id')
            ->leftJoin('supplier_payment_allocations', 'supplier_payments.id', '=', 'supplier_payment_allocations.supplier_payment_id')
            ->selectRaw('
                supplier_payments.supplier_id,
                suppliers.name as supplier_name,
                COUNT(DISTINCT supplier_payment_allocations.purchase_invoice_id) as invoice_count,
                COALESCE(SUM(supplier_payments.total_amount), 0) as total_amount
            ')
            ->groupBy('supplier_payments.supplier_id', 'suppliers.name')
            ->orderBy('suppliers.name')
            ->paginate(12)
            ->withQueryString();

        return view('supplier-payables.history', [
            ...$this->pageData('keuangan.riwayat-pembayaran-hutang'),
            'suppliers' => $suppliers,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'stats' => [
                'invoice_count' => (clone $baseQuery)
                    ->join('supplier_payment_allocations', 'supplier_payments.id', '=', 'supplier_payment_allocations.supplier_payment_id')
                    ->distinct('supplier_payment_allocations.purchase_invoice_id')
                    ->count('supplier_payment_allocations.purchase_invoice_id'),
                'total_amount' => (float) (clone $baseQuery)->sum('total_amount'),
            ],
        ]);
    }

    public function historyShow(Supplier $supplier, Request $request): View
    {
        $today = now()->toDateString();
        $dateFrom = trim((string) $request->query('date_from', $today));
        $dateTo = trim((string) $request->query('date_to', $today));

        $paymentRows = $this->supplierPaymentBaseQuery('', $dateFrom, $dateTo)
            ->with([
                'allocations:id,supplier_payment_id,purchase_invoice_id,amount_paid',
                'allocations.purchaseInvoice:id,invoice_number,invoice_date,grand_total',
                'allocations.purchaseInvoice.items:id,purchase_invoice_id,medicine_id,medicine_code_snapshot,medicine_name_snapshot,medicine_unit_snapshot,batch_number,quantity,unit_price,line_total',
                'allocations.purchaseInvoice.items.medicine:id,name,small_unit',
            ])
            ->where('supplier_id', $supplier->id)
            ->latest('payment_date')
            ->latest('id')
            ->get();

        $invoices = $paymentRows->map(function (SupplierPayment $payment): array {
            $allocation = $payment->allocations->first();
            $invoice = $allocation?->purchaseInvoice;

            return [
                'id' => $invoice?->id ?? $payment->id,
                'invoice_number' => $invoice?->invoice_number ?: '-',
                'invoice_date' => $invoice?->invoice_date?->translatedFormat('d M Y') ?? '-',
                'payment_number' => $payment->payment_number,
                'payment_date' => $payment->payment_date?->translatedFormat('d M Y') ?? '-',
                'payment_method' => match ($payment->payment_method) {
                    'transfer' => 'Transfer',
                    'qris' => 'QRIS',
                    'debit' => 'Debit',
                    default => 'Tunai',
                },
                'reference_number' => $payment->reference_number ?: '-',
                'amount_paid' => $this->formatCurrency((float) ($allocation?->amount_paid ?? $payment->total_amount)),
                'delete_url' => route('keuangan.riwayat-pembayaran-hutang.destroy', $payment),
                'items' => collect($invoice?->items ?? [])->map(fn ($item): array => [
                    'medicine_name' => $item->medicine?->name ?: '-',
                    'batch_number' => $item->batch_number ?: 'Tanpa batch',
                    'quantity' => number_format((float) $item->quantity, 0, ',', '.'),
                    'unit' => $item->medicine?->small_unit ?: '-',
                    'unit_price' => $this->formatCurrency((float) $item->unit_price),
                    'line_total' => $this->formatCurrency((float) $item->line_total),
                ])->values()->all(),
            ];
        })->values();

        return view('supplier-payables.history-show', [
            ...$this->pageData('keuangan.riwayat-pembayaran-hutang'),
            'supplier' => $supplier,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'detail' => [
                'supplier_name' => $supplier->name ?: '-',
                'invoice_count' => number_format($invoices->pluck('invoice_number')->filter(fn ($number) => $number !== '-')->unique()->count()),
                'total_amount' => $this->formatCurrency((float) $paymentRows->sum('total_amount')),
                'invoices' => $invoices->all(),
            ],
        ]);
    }

    public function destroyPayment(SupplierPayment $supplierPayment): RedirectResponse
    {
        $paymentNumber = $supplierPayment->payment_number;

        try {
            DB::transaction(function () use ($supplierPayment): void {
                $payment = SupplierPayment::query()
                    ->with('allocations:id,supplier_payment_id,purchase_invoice_id,amount_paid')
                    ->lockForUpdate()
                    ->findOrFail($supplierPayment->id);

                foreach ($payment->allocations->sortBy('purchase_invoice_id') as $allocation) {
                    $invoice = PurchaseInvoice::query()
                        ->lockForUpdate()
                        ->findOrFail($allocation->purchase_invoice_id);

                    $newPaidAmount = round((float) $invoice->paid_amount - (float) $allocation->amount_paid, 2);

                    if ($newPaidAmount < -0.001) {
                        throw new RuntimeException('Saldo pembayaran faktur '.$invoice->invoice_number.' tidak sinkron sehingga pembayaran belum bisa dihapus.');
                    }

                    $newPaidAmount = max($newPaidAmount, 0);
                    $newOutstanding = max(round((float) $invoice->grand_total - $newPaidAmount, 2), 0);

                    $invoice->update([
                        'paid_amount' => $newPaidAmount,
                        'outstanding_amount' => $newOutstanding,
                        'payment_status' => $newOutstanding <= 0.001
                            ? 'paid'
                            : ($newPaidAmount > 0.001 ? 'partial' : 'unpaid'),
                    ]);
                }

                $payment->delete();
            });
        } catch (RuntimeException $exception) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Pembayaran hutang '.$paymentNumber.' berhasil dihapus dan saldo faktur telah dikembalikan.',
        ]);
    }

    private function supplierPaymentBaseQuery(string $search = '', string $dateFrom = '', string $dateTo = ''): Builder
    {
        return SupplierPayment::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('reference_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn (Builder $supplierQuery) => $supplierQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('allocations.purchaseInvoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('invoice_number', 'like', "%{$search}%"));
                });
            })
            ->when($dateFrom !== '', fn (Builder $query) => $query->whereDate('payment_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn (Builder $query) => $query->whereDate('payment_date', '<=', $dateTo));
    }

    private function outstandingInvoiceQuery(string $search = ''): Builder
    {
        return PurchaseInvoice::query()
            ->leftJoin('suppliers', 'purchase_invoices.supplier_id', '=', 'suppliers.id')
            ->where('purchase_invoices.payment_method', 'credit')
            ->where('purchase_invoices.outstanding_amount', '>', 0.001)
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search) {
                $inner
                    ->where('suppliers.name', 'like', "%{$search}%")
                    ->orWhere('purchase_invoices.invoice_number', 'like', "%{$search}%");
            }));
    }

    private function supplierPayableQuery(string $search = ''): Builder
    {
        return $this->outstandingInvoiceQuery($search)
            ->selectRaw('
                purchase_invoices.supplier_id,
                suppliers.name as supplier_name,
                COUNT(purchase_invoices.id) as invoice_count,
                COALESCE(SUM(purchase_invoices.outstanding_amount), 0) as total_payable
            ')
            ->groupBy('purchase_invoices.supplier_id', 'suppliers.name')
            ->orderBy('suppliers.name');
    }

    private function nextPaymentNumber(): string
    {
        $latest = SupplierPayment::query()
            ->where('payment_number', 'like', 'BYH-%')
            ->orderByDesc('id')
            ->value('payment_number');

        if (! is_string($latest) || ! preg_match('/(\d+)$/', $latest, $matches)) {
            return 'BYH-0001';
        }

        return 'BYH-'.str_pad((string) ((int) $matches[1] + 1), 4, '0', STR_PAD_LEFT);
    }

    private function paymentMethods(): array
    {
        return [
            'cash' => 'Tunai',
            'transfer' => 'Transfer',
            'qris' => 'QRIS',
            'debit' => 'Debit',
        ];
    }

    private function pageData(string $routeName): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $item): bool => $item['label'] === 'Keuangan');
        $siblings = $section['children'] ?? [];

        return [
            'page' => collect($siblings)->firstWhere('route', $routeName),
            'section' => $section['label'] ?? 'Keuangan',
            'siblings' => $siblings,
        ];
    }

    private function formatCurrency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
