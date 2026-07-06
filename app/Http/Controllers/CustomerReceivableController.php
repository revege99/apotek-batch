<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PharmacyProfile;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class CustomerReceivableController extends Controller
{
    /**
     * Display the customer receivable summary page.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $customers = $this->customerReceivableQuery($search)
            ->paginate(12)
            ->withQueryString();

        return view('customer-receivables.index', [
            ...$this->pageData('keuangan.piutang-pelanggan'),
            'customers' => $customers,
            'search' => $search,
            'stats' => [
                'invoice_count' => $this->outstandingSaleBaseQuery()->count(),
                'total_receivable' => (float) $this->outstandingSaleBaseQuery()
                    ->selectRaw('COALESCE(SUM(grand_total - paid_amount), 0) as total_receivable')
                    ->value('total_receivable'),
            ],
        ]);
    }

    /**
     * Display the receivable detail page for one customer.
     */
    public function show(Customer $customer): View
    {
        $detail = $this->receivableDetailData($customer);

        return view('customer-receivables.show', [
            ...$this->pageData('keuangan.piutang-pelanggan'),
            'customer' => $customer,
            'detail' => $detail,
            'paymentMethods' => $this->paymentMethods(),
            'todayDate' => now()->toDateString(),
        ]);
    }

    /**
     * Download a customer receivable statement as PDF.
     */
    public function print(Customer $customer)
    {
        $customer->loadMissing('customerGroup:id,name');

        $sales = Sale::query()
            ->where('customer_id', $customer->id)
            ->where('payment_method', 'credit')
            ->orderBy('sale_date')
            ->orderBy('sale_number')
            ->get()
            ->filter(fn (Sale $sale): bool => $this->receivableOutstandingAmount($sale) > 0.001)
            ->values();

        $profile = $this->currentProfile();
        $totalReceivable = $sales->sum(fn (Sale $sale): float => $this->receivableOutstandingAmount($sale));

        $pdf = Pdf::loadView('customer-receivables.print', [
            'customer' => $customer,
            'sales' => $sales,
            'profile' => $profile,
            'printedAt' => now(),
            'totalReceivable' => $totalReceivable,
            'totalReceivableWords' => $this->rupiahInWords($totalReceivable),
            'pharmacyAddressLine' => $this->pharmacyAddressLine($profile),
        ])->setPaper('a4');

        return $pdf->download('piutang-'.$customer->code.'.pdf');
    }

    /**
     * Store a new customer receivable payment.
     */
    public function storePayment(Request $request, Sale $sale): RedirectResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'payment_date' => ['required', 'date'],
                'payment_method' => ['required', 'in:cash,transfer,qris,debit'],
                'amount' => ['required', 'numeric', 'min:0.01'],
                'reference_number' => ['nullable', 'string', 'max:100'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            [
                'payment_date.required' => 'Tanggal pembayaran wajib diisi.',
                'payment_method.required' => 'Pilih metode pembayaran terlebih dahulu.',
                'amount.required' => 'Nominal pembayaran wajib diisi.',
                'amount.min' => 'Nominal pembayaran harus lebih besar dari nol.',
            ],
        );

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Periksa kembali pembayaran piutang. Masih ada data yang perlu diperbaiki.',
                ]);
        }

        $validated = $validator->validated();

        try {
            $payment = DB::transaction(function () use ($request, $sale, $validated): CustomerPayment {
                $lockedSale = Sale::query()
                    ->with('customer:id,name')
                    ->lockForUpdate()
                    ->findOrFail($sale->id);

                if ($lockedSale->payment_method !== 'credit') {
                    throw new RuntimeException('Faktur ini bukan transaksi kredit.');
                }

                if ($lockedSale->customer_id === null) {
                    throw new RuntimeException('Pelanggan untuk faktur ini tidak ditemukan.');
                }

                $outstandingAmount = $this->receivableOutstandingAmount($lockedSale);

                if ($outstandingAmount <= 0.001) {
                    throw new RuntimeException('Piutang untuk faktur ini sudah lunas.');
                }

                $amount = round((float) $validated['amount'], 2);

                if ($amount > $outstandingAmount + 0.001) {
                    throw new RuntimeException('Nominal pembayaran melebihi sisa piutang faktur ini.');
                }

                if (abs($amount - $outstandingAmount) > 0.001) {
                    throw new RuntimeException('Pembayaran piutang harus dilunasi penuh per faktur.');
                }

                $payment = CustomerPayment::query()->create([
                    'payment_number' => $this->nextPaymentNumber(),
                    'sale_id' => $lockedSale->id,
                    'customer_id' => $lockedSale->customer_id,
                    'payment_date' => Carbon::parse($validated['payment_date']),
                    'payment_method' => (string) $validated['payment_method'],
                    'reference_number' => filled($validated['reference_number'] ?? null) ? trim((string) $validated['reference_number']) : null,
                    'amount_paid' => $amount,
                    'notes' => filled($validated['notes'] ?? null) ? trim((string) $validated['notes']) : null,
                    'created_by' => $request->user()?->id,
                ]);

                $lockedSale->update([
                    'paid_amount' => round((float) $lockedSale->paid_amount + $amount, 2),
                ]);

                return $payment;
            });
        } catch (RuntimeException $exception) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Pembayaran piutang '.$payment->payment_number.' berhasil disimpan.',
        ]);
    }

    /**
     * Display the receivable payment history page.
     */
    public function history(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $today = now()->toDateString();
        $dateFrom = trim((string) $request->query('date_from', $today));
        $dateTo = trim((string) $request->query('date_to', $today));
        $customers = $this->customerPaymentSummaryQuery($search, $dateFrom, $dateTo)
            ->paginate(12)
            ->withQueryString();

        return view('customer-receivable-payments.index', [
            ...$this->pageData('keuangan.riwayat-pembayaran'),
            'customers' => $customers,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'stats' => [
                'total' => $this->customerPaymentBaseQuery($search, $dateFrom, $dateTo)->count(),
                'total_amount' => (float) $this->customerPaymentBaseQuery($search, $dateFrom, $dateTo)->sum('amount_paid'),
            ],
        ]);
    }

    /**
     * Display the payment history detail page for one customer.
     */
    public function historyShow(Customer $customer, Request $request): View
    {
        $today = now()->toDateString();
        $dateFrom = trim((string) $request->query('date_from', $today));
        $dateTo = trim((string) $request->query('date_to', $today));
        $detail = $this->paymentHistoryDetailData($customer, $dateFrom, $dateTo);

        return view('customer-receivable-payments.show', [
            ...$this->pageData('keuangan.riwayat-pembayaran'),
            'customer' => $customer,
            'detail' => $detail,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    /**
     * Download a customer payment history statement as PDF.
     */
    public function printPaymentHistory(Customer $customer, Request $request)
    {
        $today = now()->toDateString();
        $dateFrom = trim((string) $request->query('date_from', $today));
        $dateTo = trim((string) $request->query('date_to', $today));
        $payments = $this->customerPaymentBaseQuery('', $dateFrom, $dateTo)
            ->with([
                'sale:id,sale_number,sale_date',
            ])
            ->where('customer_id', $customer->id)
            ->latest('payment_date')
            ->latest('id')
            ->get();

        $profile = $this->currentProfile();
        $totalAmount = (float) $payments->sum('amount_paid');

        $pdf = Pdf::loadView('customer-receivable-payments.print', [
            'customer' => $customer,
            'payments' => $payments,
            'profile' => $profile,
            'printedAt' => now(),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'totalAmount' => $totalAmount,
            'pharmacyAddressLine' => $this->pharmacyAddressLine($profile),
        ])->setPaper('a4');

        return $pdf->download('pembayaran-piutang-'.$customer->code.'.pdf');
    }

    /**
     * Delete a receivable payment and restore the sale outstanding balance.
     */
    public function destroyPayment(CustomerPayment $customerPayment): RedirectResponse
    {
        $paymentNumber = $customerPayment->payment_number;

        try {
            DB::transaction(function () use ($customerPayment): void {
                $lockedPayment = CustomerPayment::query()
                    ->lockForUpdate()
                    ->findOrFail($customerPayment->id);

                $lockedSale = Sale::query()
                    ->lockForUpdate()
                    ->findOrFail($lockedPayment->sale_id);

                $newPaidAmount = round((float) $lockedSale->paid_amount - (float) $lockedPayment->amount_paid, 2);

                if ($newPaidAmount < -0.001) {
                    throw new RuntimeException('Saldo pembayaran faktur ini sudah tidak sinkron, jadi pembayaran belum bisa dihapus.');
                }

                $lockedSale->update([
                    'paid_amount' => max($newPaidAmount, 0),
                ]);

                $lockedPayment->delete();
            });
        } catch (RuntimeException $exception) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Pembayaran piutang '.$paymentNumber.' berhasil dihapus.',
        ]);
    }

    /**
     * Build the receivable customer summary query.
     */
    private function customerReceivableQuery(string $search = ''): Builder
    {
        return $this->outstandingSaleBaseQuery($search)
            ->selectRaw('
                sales.customer_id,
                customers.name as customer_name,
                COUNT(*) as invoice_count,
                COALESCE(SUM(grand_total - paid_amount), 0) as total_receivable
            ')
            ->groupBy('sales.customer_id', 'customers.name')
            ->orderBy('customer_name');
    }

    /**
     * Build the base query for customer payment history.
     */
    private function customerPaymentBaseQuery(string $search = '', string $dateFrom = '', string $dateTo = ''): Builder
    {
        return CustomerPayment::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $paymentQuery) use ($search) {
                    $paymentQuery
                        ->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('reference_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('sale', fn (Builder $saleQuery) => $saleQuery->where('sale_number', 'like', "%{$search}%"));
                });
            })
            ->when($dateFrom !== '', fn (Builder $query) => $query->whereDate('payment_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn (Builder $query) => $query->whereDate('payment_date', '<=', $dateTo));
    }

    /**
     * Build the grouped customer summary query for payment history.
     */
    private function customerPaymentSummaryQuery(string $search = '', string $dateFrom = '', string $dateTo = ''): Builder
    {
        return $this->customerPaymentBaseQuery($search, $dateFrom, $dateTo)
            ->join('customers', 'customer_payments.customer_id', '=', 'customers.id')
            ->selectRaw('
                customer_payments.customer_id,
                customers.name as customer_name,
                COUNT(customer_payments.id) as payment_count,
                COALESCE(SUM(customer_payments.amount_paid), 0) as total_amount
            ')
            ->groupBy('customer_payments.customer_id', 'customers.name')
            ->orderBy('customers.name');
    }

    /**
     * Build the base query for outstanding customer credit sales.
     */
    private function outstandingSaleBaseQuery(string $search = ''): Builder
    {
        return Sale::query()
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->whereNotNull('customer_id')
            ->where('payment_method', 'credit')
            ->whereRaw('grand_total - paid_amount > 0.001')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $saleQuery) use ($search) {
                    $saleQuery
                        ->where('sales.customer_name', 'like', "%{$search}%")
                        ->orWhere('customers.name', 'like', "%{$search}%")
                        ->orWhere('sales.sale_number', 'like', "%{$search}%");
                });
            });
    }

    /**
     * Build receivable detail data for one customer.
     *
     * @return array<string, mixed>
     */
    private function receivableDetailData(Customer $customer): array
    {
        $sales = Sale::query()
            ->with([
                'customer:id,name,phone,address',
                'customerPayments:id,sale_id,payment_date,amount_paid',
                'items:id,sale_id,medicine_id,batch_number_snapshot,quantity,unit_price,line_total',
                'items.medicine:id,name,small_unit',
            ])
            ->where('customer_id', $customer->id)
            ->where('payment_method', 'credit')
            ->orderBy('sale_date')
            ->orderBy('sale_number')
            ->get()
            ->filter(fn (Sale $sale): bool => $this->receivableOutstandingAmount($sale) > 0.001)
            ->values()
            ->map(function (Sale $sale): array {
                $outstandingAmount = $this->receivableOutstandingAmount($sale);
                $lastPaymentDate = $sale->customerPayments
                    ->sortByDesc('payment_date')
                    ->first()?->payment_date;

                return [
                    'id' => $sale->id,
                    'sale_number' => $sale->sale_number,
                    'sale_date' => $sale->sale_date?->translatedFormat('d M Y H:i') ?? '-',
                    'grand_total' => $this->formatCurrency((float) $sale->grand_total),
                    'paid_amount' => $this->formatCurrency((float) $sale->paid_amount),
                    'outstanding_amount' => $this->formatCurrency($outstandingAmount),
                    'outstanding_value' => $outstandingAmount,
                    'payment_count' => number_format($sale->customerPayments->count()),
                    'last_payment_date' => $lastPaymentDate?->translatedFormat('d M Y H:i') ?? '-',
                    'action' => route('keuangan.piutang-pelanggan.bayar', $sale),
                    'items' => $sale->items
                        ->map(function ($item): array {
                            return [
                                'medicine_name' => $item->medicine?->name ?: '-',
                                'batch_number' => $item->batch_number_snapshot ?: '-',
                                'quantity' => number_format((float) $item->quantity, 0, ',', '.'),
                                'unit' => $item->medicine?->small_unit ?: '-',
                                'unit_price' => $this->formatCurrency((float) $item->unit_price),
                                'line_total' => $this->formatCurrency((float) $item->line_total),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        return [
            'print_url' => route('keuangan.piutang-pelanggan.print', $customer),
            'customer_name' => $customer->name ?: '-',
            'customer_address' => $customer->address ?: '-',
            'invoice_count' => number_format($sales->count()),
            'total_receivable' => $this->formatCurrency((float) $sales->sum('outstanding_value')),
            'sales' => $sales->all(),
        ];
    }

    /**
     * Build payment history detail data for one customer.
     *
     * @return array<string, mixed>
     */
    private function paymentHistoryDetailData(Customer $customer, string $dateFrom, string $dateTo): array
    {
        $paymentRows = $this->customerPaymentBaseQuery('', $dateFrom, $dateTo)
            ->with([
                'sale:id,sale_number,sale_date',
                'sale.items:id,sale_id,medicine_id,batch_number_snapshot,quantity,unit_price,line_total',
                'sale.items.medicine:id,name,small_unit',
                'customer:id,name,address',
            ])
            ->where('customer_id', $customer->id)
            ->latest('payment_date')
            ->latest('id')
            ->get();

        $payments = $paymentRows
            ->map(function (CustomerPayment $payment): array {
                return [
                    'id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'sale_number' => $payment->sale?->sale_number ?: '-',
                    'sale_date' => $payment->sale?->sale_date?->translatedFormat('d M Y') ?? '-',
                    'payment_date' => $payment->payment_date?->translatedFormat('d M Y H:i') ?? '-',
                    'payment_method' => match ($payment->payment_method) {
                        'transfer' => 'Transfer',
                        'qris' => 'QRIS',
                        'debit' => 'Debit',
                        default => 'Tunai',
                    },
                    'reference_number' => $payment->reference_number ?: '-',
                    'amount_paid' => $this->formatCurrency((float) $payment->amount_paid),
                    'delete_url' => route('keuangan.riwayat-pembayaran.destroy', $payment),
                    'items' => collect($payment->sale?->items ?? [])
                        ->map(function ($item): array {
                            return [
                                'medicine_name' => $item->medicine?->name ?: '-',
                                'batch_number' => $item->batch_number_snapshot ?: '-',
                                'quantity' => number_format((float) $item->quantity, 0, ',', '.'),
                                'unit' => $item->medicine?->small_unit ?: '-',
                                'unit_price' => $this->formatCurrency((float) $item->unit_price),
                                'line_total' => $this->formatCurrency((float) $item->line_total),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        return [
            'customer_name' => $customer->name ?: '-',
            'payment_count' => number_format($payments->count()),
            'total_amount' => $this->formatCurrency((float) $paymentRows->sum('amount_paid')),
            'print_url' => route('keuangan.riwayat-pembayaran.print', [
                'customer' => $customer->id,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]),
            'period_label' => $this->formatPeriodLabel($dateFrom, $dateTo),
            'payments' => $payments->all(),
        ];
    }

    /**
     * Resolve the payment method options for receivable settlement.
     *
     * @return array<string, string>
     */
    private function paymentMethods(): array
    {
        return [
            'cash' => 'Tunai',
            'transfer' => 'Transfer',
            'qris' => 'QRIS',
            'debit' => 'Debit',
        ];
    }

    /**
     * Calculate the remaining receivable balance for a credit sale.
     */
    private function receivableOutstandingAmount(Sale $sale): float
    {
        return max(round((float) $sale->grand_total - (float) $sale->paid_amount, 2), 0);
    }

    /**
     * Build page metadata for finance pages.
     *
     * @return array<string, mixed>
     */
    private function pageData(string $routeName): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $item): bool => $item['label'] === 'Keuangan');

        $siblings = $section['children'] ?? [];
        $page = collect($siblings)->firstWhere('route', $routeName);

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Keuangan',
            'siblings' => $siblings,
        ];
    }

    /**
     * Generate the next receivable payment number.
     */
    private function nextPaymentNumber(): string
    {
        $latestCode = CustomerPayment::query()
            ->where('payment_number', 'like', 'BYR-%')
            ->orderByDesc('id')
            ->value('payment_number');

        if (! is_string($latestCode) || ! preg_match('/(\d+)$/', $latestCode, $matches)) {
            return 'BYR-0001';
        }

        return 'BYR-'.str_pad((string) ((int) $matches[1] + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Format currency values.
     */
    private function formatCurrency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    /**
     * Format the selected history period for customer payment statements.
     */
    private function formatPeriodLabel(string $dateFrom, string $dateTo): string
    {
        if ($dateFrom !== '' && $dateTo !== '') {
            return Carbon::parse($dateFrom)->translatedFormat('d/m/Y').' s/d '.Carbon::parse($dateTo)->translatedFormat('d/m/Y');
        }

        if ($dateFrom !== '') {
            return 'Mulai '.Carbon::parse($dateFrom)->translatedFormat('d/m/Y');
        }

        if ($dateTo !== '') {
            return 'Sampai '.Carbon::parse($dateTo)->translatedFormat('d/m/Y');
        }

        return 'Semua periode';
    }

    /**
     * Build a single-line address for the pharmacy invoice header.
     */
    private function pharmacyAddressLine(PharmacyProfile $profile): string
    {
        $segments = collect([
            $profile->address,
            $profile->city,
            $profile->province,
            $profile->postal_code,
        ])->filter(fn ($value): bool => filled($value));

        return $segments->isNotEmpty()
            ? $segments->implode(', ')
            : 'Alamat apotik belum diatur.';
    }

    /**
     * Convert a nominal amount into Indonesian rupiah words.
     */
    private function rupiahInWords(float $amount): string
    {
        $normalizedAmount = (int) round(abs($amount));

        if ($normalizedAmount === 0) {
            return 'Nol Rupiah';
        }

        return trim($this->spellNumber($normalizedAmount)).' Rupiah';
    }

    /**
     * Spell an integer number in Indonesian words.
     */
    private function spellNumber(int $number): string
    {
        $words = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        if ($number < 12) {
            return ' '.$words[$number];
        }

        if ($number < 20) {
            return $this->spellNumber($number - 10).' belas';
        }

        if ($number < 100) {
            return $this->spellNumber((int) floor($number / 10)).' puluh'.$this->spellNumber($number % 10);
        }

        if ($number < 200) {
            return ' seratus'.$this->spellNumber($number - 100);
        }

        if ($number < 1000) {
            return $this->spellNumber((int) floor($number / 100)).' ratus'.$this->spellNumber($number % 100);
        }

        if ($number < 2000) {
            return ' seribu'.$this->spellNumber($number - 1000);
        }

        if ($number < 1000000) {
            return $this->spellNumber((int) floor($number / 1000)).' ribu'.$this->spellNumber($number % 1000);
        }

        if ($number < 1000000000) {
            return $this->spellNumber((int) floor($number / 1000000)).' juta'.$this->spellNumber($number % 1000000);
        }

        if ($number < 1000000000000) {
            return $this->spellNumber((int) floor($number / 1000000000)).' miliar'.$this->spellNumber($number % 1000000000);
        }

        return $this->spellNumber((int) floor($number / 1000000000000)).' triliun'.$this->spellNumber($number % 1000000000000);
    }

    /**
     * Resolve the active pharmacy profile or create the default one.
     */
    private function currentProfile(): PharmacyProfile
    {
        return PharmacyProfile::query()->active()->latest('id')->first()
            ?? PharmacyProfile::query()->latest('id')->first()
            ?? PharmacyProfile::query()->create([
                'name' => 'Apotik',
                'invoice_footer' => 'Terima kasih atas kepercayaan Anda.',
                'is_active' => true,
            ]);
    }
}
