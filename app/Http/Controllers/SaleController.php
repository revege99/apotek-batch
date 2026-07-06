<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleRequest;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\PharmacyProfile;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SaleController extends Controller
{
    /**
     * Display the cashier sale page.
     */
    public function create(Request $request): View
    {
        return view('sales.create', [
            ...$this->pageData('penjualan.kasir-penjualan'),
            'editingSale' => null,
            'customerOptions' => $this->customerOptions(($request->session()->getOldInput('customer_id') !== null)
                ? (int) $request->session()->getOldInput('customer_id')
                : null),
            'initialForm' => $this->buildCreateInitialForm($request),
        ]);
    }

    /**
     * Show the cashier form for editing a sale.
     */
    public function edit(Request $request, Sale $sale): View|RedirectResponse
    {
        $sale->load([
            'customer:id,name,phone,customer_group_id',
            'customer.customerGroup:id,name,markup_percentage,is_active',
            'items' => fn ($query) => $query
                ->with(['medicine:id,code,name,composition,small_unit,principal_id,purchase_price', 'medicine.principal:id,name'])
                ->orderBy('id'),
        ]);

        if (DB::table('sale_returns')->where('sale_id', $sale->id)->exists()) {
            return redirect()
                ->route('penjualan.data-penjualan')
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Penjualan yang sudah punya retur tidak bisa diedit.',
                ]);
        }

        if ($sale->customerPayments()->exists()) {
            return redirect()
                ->route('penjualan.data-penjualan')
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Penjualan yang sudah punya pembayaran piutang tidak bisa diedit.',
                ]);
        }

        return view('sales.create', [
            ...$this->pageData('penjualan.kasir-penjualan'),
            'editingSale' => $sale,
            'customerOptions' => $this->customerOptions(($request->session()->getOldInput('customer_id') !== null)
                ? (int) $request->session()->getOldInput('customer_id')
                : ($sale->customer_id !== null ? (int) $sale->customer_id : null)),
            'initialForm' => $this->buildEditInitialForm($request, $sale),
        ]);
    }

    /**
     * Display the sales history page.
     */
    public function index(Request $request): View
    {
        $today = now()->toDateString();
        $search = trim((string) $request->query('search', ''));
        $dateFrom = trim((string) $request->query('date_from', $today));
        $dateTo = trim((string) $request->query('date_to', $today));

        $sales = Sale::query()
            ->with([
                'customer:id,name',
                'customerGroup:id,name,markup_percentage',
                'customerPayments:id,sale_id,payment_date,amount_paid',
                'items' => fn ($query) => $query
                    ->with(['medicine:id,code,name,small_unit'])
                    ->orderBy('id'),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($saleQuery) use ($search) {
                    $saleQuery
                        ->where('sale_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('items', function ($itemQuery) use ($search) {
                            $itemQuery
                                ->where('batch_number_snapshot', 'like', "%{$search}%")
                                ->orWhereHas('medicine', function ($medicineQuery) use ($search) {
                                    $medicineQuery
                                        ->where('code', 'like', "%{$search}%")
                                        ->orWhere('name', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('sale_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('sale_date', '<=', $dateTo))
            ->latest('sale_date')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $sales->getCollection()->transform(function (Sale $sale): Sale {
            $sale->payment_status_label = $this->paymentStatusLabel($sale);
            $sale->payment_status_tone = $this->paymentStatusTone($sale);

            return $sale;
        });

        return view('sales.index', [
            ...$this->pageData('penjualan.data-penjualan'),
            'sales' => $sales,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'detailPayloads' => $this->detailPayloads($sales),
            'stats' => [
                'total' => Sale::query()->count(),
                'today' => Sale::query()->whereDate('sale_date', now()->toDateString())->count(),
                'grand_total' => (float) Sale::query()->sum('grand_total'),
            ],
        ]);
    }

    /**
     * Download the sale transaction as a PDF document.
     */
    public function print(Sale $sale)
    {
        $sale->load([
            'customer:id,name,address',
            'customerGroup:id,name,markup_percentage',
            'items' => fn ($query) => $query
                ->with(['medicine:id,code,name,small_unit'])
                ->orderBy('id'),
        ]);
        $groupedItems = $this->groupedSaleDisplayItems($sale->items);

        $profile = PharmacyProfile::query()->active()->latest('id')->first()
            ?? PharmacyProfile::query()->latest('id')->first()
            ?? PharmacyProfile::query()->create([
                'name' => 'Apotik',
                'invoice_footer' => 'Terima kasih telah berbelanja.',
                'is_active' => true,
            ]);

        $pdf = Pdf::loadView('sales.print', [
            'sale' => $sale,
            'groupedItems' => $groupedItems,
            'profile' => $profile,
            'paymentStatus' => $this->paymentStatusLabel($sale),
            'paymentMethodLabel' => $this->paymentMethodLabel($sale->payment_method),
            'printedAt' => now(),
            'customerAddress' => $sale->customer?->address ?: '-',
            'grandTotalWords' => $this->rupiahInWords((float) $sale->grand_total),
            'pharmacyAddressLine' => $this->pharmacyAddressLine($profile),
        ])->setPaper('a4');

        return $pdf->download('penjualan-'.$sale->sale_number.'.pdf');
    }

    /**
     * Store a newly created sale transaction.
     */
    public function store(SaleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $customer = Customer::query()
            ->with('customerGroup:id,name,markup_percentage')
            ->findOrFail((int) $validated['customer_id']);
        $saleDate = Carbon::parse($validated['sale_date']);
        $defaultMarkupPercentage = round((float) ($customer->customerGroup?->markup_percentage ?? 0), 2);

        try {
            $sale = DB::transaction(function () use ($request, $validated, $customer, $saleDate, $defaultMarkupPercentage) {
                $normalizedItems = $this->normalizeItems($validated['items'], $customer);
                $subtotal = round(collect($normalizedItems)->sum('line_total'), 2);
                $paymentKind = (string) $validated['payment_kind'];
                $paymentMethod = (string) $validated['payment_method'];
                $paidAmountInput = round((float) $validated['paid_amount'], 2);
                $otherCostAmount = round((float) $validated['other_cost_amount'], 2);
                $salePaymentMethod = $paymentMethod;
                $discountAmount = 0.0;
                $socialAmount = 0.0;
                $grandTotal = round($subtotal + $otherCostAmount, 2);
                $paidAmount = 0.0;
                $changeAmount = 0.0;

                if ($paymentKind === 'credit') {
                    $salePaymentMethod = 'credit';
                } elseif ($paymentKind === 'social') {
                    $paidAmount = min($paidAmountInput, $grandTotal);

                    if ($paidAmount <= 0.001) {
                        throw new RuntimeException('Nominal pembayaran sosial harus lebih besar dari nol.');
                    }

                    if ($paidAmount - $grandTotal > 0.001) {
                        throw new RuntimeException('Nominal pembayaran sosial tidak boleh melebihi total penjualan.');
                    }

                    $socialAmount = round(max($grandTotal - $paidAmount, 0), 2);

                    if ($socialAmount <= 0.001) {
                        throw new RuntimeException('Gunakan pembayaran biasa jika pelanggan membayar penuh tanpa sosial.');
                    }
                } else {
                    $paidAmount = $paymentMethod === 'cash' ? $paidAmountInput : $grandTotal;
                    $changeAmount = $paymentMethod === 'cash'
                        ? round(max($paidAmount - $grandTotal, 0), 2)
                        : 0.0;

                    if ($paidAmount + 0.001 < $grandTotal) {
                        throw new RuntimeException('Nominal bayar masih kurang dari total penjualan.');
                    }
                }

                $sale = Sale::query()->create([
                    'sale_number' => $validated['sale_number'],
                    'sale_date' => $saleDate,
                    'status' => 'posted',
                    'payment_method' => $salePaymentMethod,
                    'customer_id' => $customer->id,
                    'customer_group_id' => $customer->customer_group_id,
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone,
                    'customer_group_markup_percentage' => $defaultMarkupPercentage,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'social_amount' => $socialAmount,
                    'tax_amount' => 0,
                    'other_cost_amount' => $otherCostAmount,
                    'grand_total' => $grandTotal,
                    'paid_amount' => $paidAmount,
                    'change_amount' => $changeAmount,
                    'notes' => $this->saleNotesWithSocialAmount(
                        notes: $validated['notes'] ?? null,
                        paymentKind: $paymentKind,
                        paymentMethod: $paymentMethod,
                        paidAmount: $paidAmount,
                        grandTotal: $grandTotal,
                        socialAmount: $socialAmount,
                    ),
                    'created_by' => $request->user()?->id,
                ]);

                foreach ($normalizedItems as $item) {
                    $this->createSaleAllocations(
                        sale: $sale,
                        item: $item,
                        userId: $request->user()?->id,
                        saleDate: $saleDate,
                    );
                }

                return $sale;
            });
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('penjualan.kasir-penjualan')
                ->withInput()
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('penjualan.kasir-penjualan')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Transaksi penjualan '.$sale->sale_number.' berhasil disimpan.',
            ]);
    }

    /**
     * Update an existing sale transaction.
     */
    public function update(SaleRequest $request, Sale $sale): RedirectResponse
    {
        $validated = $request->validated();
        $customer = Customer::query()
            ->with('customerGroup:id,name,markup_percentage')
            ->findOrFail((int) $validated['customer_id']);
        $saleDate = Carbon::parse($validated['sale_date']);
        $defaultMarkupPercentage = round((float) ($customer->customerGroup?->markup_percentage ?? 0), 2);

        try {
            DB::transaction(function () use ($request, $validated, $customer, $saleDate, $defaultMarkupPercentage, $sale): void {
                $lockedSale = Sale::query()
                    ->with(['items'])
                    ->lockForUpdate()
                    ->findOrFail($sale->id);

                if (DB::table('sale_returns')->where('sale_id', $lockedSale->id)->exists()) {
                    throw new RuntimeException('Penjualan yang sudah punya retur tidak bisa diedit.');
                }

                if ($lockedSale->customerPayments()->exists()) {
                    throw new RuntimeException('Penjualan yang sudah punya pembayaran piutang tidak bisa diedit.');
                }

                foreach ($lockedSale->items as $saleItem) {
                    $stockBatch = StockBatch::query()
                        ->lockForUpdate()
                        ->find($saleItem->stock_batch_id);

                    if ($stockBatch === null) {
                        throw new RuntimeException('Batch stok penjualan lama tidak ditemukan, jadi transaksi belum bisa diedit.');
                    }

                    $newQuantityOut = round((float) $stockBatch->quantity_out - (float) $saleItem->quantity, 2);

                    if ($newQuantityOut < -0.001) {
                        throw new RuntimeException('Saldo mutasi batch lama sudah tidak sinkron, jadi penjualan belum bisa diedit.');
                    }

                    $newBalance = round((float) $stockBatch->quantity_balance + (float) $saleItem->quantity, 2);

                    $stockBatch->update([
                        'quantity_out' => max($newQuantityOut, 0),
                        'quantity_balance' => $newBalance,
                        'status' => $newBalance > 0 ? 'active' : 'sold_out',
                        'notes' => $this->removeSaleNote((string) $stockBatch->notes, $lockedSale->sale_number),
                    ]);
                }

                StockMovement::query()
                    ->where('reference_table', 'sale_items')
                    ->whereIn('reference_id', $lockedSale->items->pluck('id'))
                    ->delete();

                $lockedSale->items()->delete();

                $normalizedItems = $this->normalizeItems($validated['items'], $customer);
                $subtotal = round(collect($normalizedItems)->sum('line_total'), 2);
                $paymentKind = (string) $validated['payment_kind'];
                $paymentMethod = (string) $validated['payment_method'];
                $paidAmountInput = round((float) $validated['paid_amount'], 2);
                $otherCostAmount = round((float) $validated['other_cost_amount'], 2);
                $salePaymentMethod = $paymentMethod;
                $discountAmount = 0.0;
                $socialAmount = 0.0;
                $grandTotal = round($subtotal + $otherCostAmount, 2);
                $paidAmount = 0.0;
                $changeAmount = 0.0;

                if ($paymentKind === 'credit') {
                    $salePaymentMethod = 'credit';
                } elseif ($paymentKind === 'social') {
                    $paidAmount = min($paidAmountInput, $grandTotal);

                    if ($paidAmount <= 0.001) {
                        throw new RuntimeException('Nominal pembayaran sosial harus lebih besar dari nol.');
                    }

                    if ($paidAmount - $grandTotal > 0.001) {
                        throw new RuntimeException('Nominal pembayaran sosial tidak boleh melebihi total penjualan.');
                    }

                    $socialAmount = round(max($grandTotal - $paidAmount, 0), 2);

                    if ($socialAmount <= 0.001) {
                        throw new RuntimeException('Gunakan pembayaran biasa jika pelanggan membayar penuh tanpa sosial.');
                    }
                } else {
                    $paidAmount = $paymentMethod === 'cash' ? $paidAmountInput : $grandTotal;
                    $changeAmount = $paymentMethod === 'cash'
                        ? round(max($paidAmount - $grandTotal, 0), 2)
                        : 0.0;

                    if ($paidAmount + 0.001 < $grandTotal) {
                        throw new RuntimeException('Nominal bayar masih kurang dari total penjualan.');
                    }
                }

                $lockedSale->update([
                    'sale_number' => $validated['sale_number'],
                    'sale_date' => $saleDate,
                    'payment_method' => $salePaymentMethod,
                    'customer_id' => $customer->id,
                    'customer_group_id' => $customer->customer_group_id,
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone,
                    'customer_group_markup_percentage' => $defaultMarkupPercentage,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'social_amount' => $socialAmount,
                    'tax_amount' => 0,
                    'other_cost_amount' => $otherCostAmount,
                    'grand_total' => $grandTotal,
                    'paid_amount' => $paidAmount,
                    'change_amount' => $changeAmount,
                    'notes' => $this->saleNotesWithSocialAmount(
                        notes: $validated['notes'] ?? null,
                        paymentKind: $paymentKind,
                        paymentMethod: $paymentMethod,
                        paidAmount: $paidAmount,
                        grandTotal: $grandTotal,
                        socialAmount: $socialAmount,
                    ),
                ]);

                foreach ($normalizedItems as $item) {
                    $this->createSaleAllocations(
                        sale: $lockedSale,
                        item: $item,
                        userId: $request->user()?->id,
                        saleDate: $saleDate,
                    );
                }
            });
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('penjualan.data-penjualan.edit', $sale)
                ->withInput()
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('penjualan.data-penjualan')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Transaksi penjualan '.$validated['sale_number'].' berhasil diperbarui.',
            ]);
    }

    /**
     * Delete a sale and restore its stock balance.
     */
    public function destroy(Sale $sale): RedirectResponse
    {
        $saleNumber = $sale->sale_number;

        try {
            DB::transaction(function () use ($sale): void {
                $lockedSale = Sale::query()
                    ->with('items')
                    ->lockForUpdate()
                    ->findOrFail($sale->id);

                if (DB::table('sale_returns')->where('sale_id', $lockedSale->id)->exists()) {
                    throw new RuntimeException('Penjualan ini sudah punya retur, jadi tidak bisa dihapus.');
                }

                if ($lockedSale->customerPayments()->exists()) {
                    throw new RuntimeException('Penjualan ini sudah memiliki pembayaran piutang, jadi tidak bisa dihapus.');
                }

                foreach ($lockedSale->items as $saleItem) {
                    $stockBatch = StockBatch::query()
                        ->lockForUpdate()
                        ->find($saleItem->stock_batch_id);

                    if ($stockBatch === null) {
                        throw new RuntimeException('Batch stok penjualan ini tidak ditemukan, jadi transaksi belum bisa dihapus.');
                    }

                    $newQuantityOut = round((float) $stockBatch->quantity_out - (float) $saleItem->quantity, 2);

                    if ($newQuantityOut < -0.001) {
                        throw new RuntimeException('Saldo mutasi batch sudah tidak sinkron, jadi penjualan belum bisa dihapus.');
                    }

                    $newBalance = round((float) $stockBatch->quantity_balance + (float) $saleItem->quantity, 2);

                    $stockBatch->update([
                        'quantity_out' => max($newQuantityOut, 0),
                        'quantity_balance' => $newBalance,
                        'status' => $newBalance > 0 ? 'active' : 'sold_out',
                        'notes' => $this->removeSaleNote((string) $stockBatch->notes, $lockedSale->sale_number),
                    ]);
                }

                StockMovement::query()
                    ->where('reference_table', 'sale_items')
                    ->whereIn('reference_id', $lockedSale->items->pluck('id'))
                    ->delete();

                $lockedSale->items()->delete();
                $lockedSale->delete();
            });
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('penjualan.data-penjualan')
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('penjualan.data-penjualan')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Transaksi penjualan '.$saleNumber.' berhasil dihapus dan stok dikembalikan.',
            ]);
    }

    /**
     * Build initial cashier rows from medicines that still have stock.
     *
     * @return array<int, array<string, mixed>>
     */
    private function initialItems(Request $request): array
    {
        $oldItems = collect($request->session()->getOldInput('items', []))
            ->filter(fn ($item): bool => is_array($item) && isset($item['medicine_id']))
            ->values();

        $stockMap = StockBatch::query()
            ->selectRaw('medicine_id, COALESCE(SUM(quantity_balance), 0) as total_stock')
            ->where('quantity_balance', '>', 0)
            ->groupBy('medicine_id')
            ->pluck('total_stock', 'medicine_id');

        $batchMap = StockBatch::query()
            ->where('quantity_balance', '>', 0)
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('received_at')
            ->orderBy('id')
            ->get([
                'id',
                'medicine_id',
                'batch_number',
                'expiry_date',
                'quantity_balance',
                'purchase_price',
            ])
            ->groupBy('medicine_id');

        $medicineRows = Medicine::query()
            ->with('principal:id,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (Medicine $medicine): bool => (float) ($stockMap[$medicine->id] ?? 0) > 0 && ($batchMap[$medicine->id] ?? collect())->isNotEmpty())
            ->values()
            ->map(function (Medicine $medicine, int $groupOrder) use ($stockMap, $batchMap): array {
                /** @var Collection<int, StockBatch> $stockBatches */
                $stockBatches = $batchMap[$medicine->id] ?? collect();
                $batches = $this->groupedSaleBatchOptions($stockBatches, $medicine->small_unit ?: 'unit');
                $defaultBatch = $batches->first();
                $baseUnitCost = round($this->baseUnitCost($medicine), 2);

                return [
                    'key' => 'sale-item-'.$medicine->id.'-base',
                    'group_order' => $groupOrder,
                    'medicine_id' => $medicine->id,
                    'medicine_code' => $medicine->code,
                    'medicine_name' => $medicine->name,
                    'principal_name' => $medicine->principal?->name ?: '-',
                    'composition' => $medicine->composition ?: '-',
                    'small_unit' => $medicine->small_unit ?: 'unit',
                    'stock_batch_id' => (string) ($defaultBatch['id'] ?? ''),
                    'base_unit_cost' => (string) $baseUnitCost,
                    'stock_quantity' => (string) round((float) ($defaultBatch['stock_quantity'] ?? ($stockMap[$medicine->id] ?? 0)), 2),
                    'quantity' => '',
                    'batches' => $batches->values()->all(),
                ];
            })
            ->keyBy(fn (array $row): int => (int) $row['medicine_id']);

        if ($oldItems->isEmpty()) {
            return $medicineRows->values()->all();
        }

        $rows = [];
        $usedMedicineIds = [];

        foreach ($oldItems as $index => $oldItem) {
            $medicineId = (int) ($oldItem['medicine_id'] ?? 0);
            $baseRow = $medicineRows->get($medicineId);

            if ($baseRow === null) {
                continue;
            }

            $selectedBatch = $this->resolveSaleBatchOption(
                collect($baseRow['batches']),
                $oldItem['stock_batch_id'] ?? null,
            ) ?? collect($baseRow['batches'])->first();

            $rows[] = [
                ...$baseRow,
                'key' => 'sale-item-'.$medicineId.'-old-'.$index,
                'stock_batch_id' => (string) ($selectedBatch['id'] ?? $baseRow['stock_batch_id']),
                'base_unit_cost' => (string) $baseRow['base_unit_cost'],
                'stock_quantity' => (string) ($selectedBatch['stock_quantity'] ?? $baseRow['stock_quantity']),
                'quantity' => (string) ($oldItem['quantity'] ?? ''),
                'markup_percentage' => array_key_exists('markup_percentage', $oldItem)
                    ? (string) $oldItem['markup_percentage']
                    : null,
                'unit_price' => array_key_exists('unit_price', $oldItem)
                    ? (string) $oldItem['unit_price']
                    : null,
            ];

            $usedMedicineIds[$medicineId] = true;
        }

        foreach ($medicineRows as $medicineId => $baseRow) {
            if (isset($usedMedicineIds[$medicineId])) {
                continue;
            }

            $rows[] = $baseRow;
        }

        return array_values($rows);
    }

    /**
     * Build the default cashier payload for create mode.
     *
     * @return array<string, mixed>
     */
    private function buildCreateInitialForm(Request $request): array
    {
        return [
            'sale_number' => (string) $request->session()->getOldInput('sale_number', $this->nextSaleNumber()),
            'sale_date' => (string) $request->session()->getOldInput('sale_date', now()->format('Y-m-d\TH:i')),
            'customer_id' => (string) $request->session()->getOldInput('customer_id', ''),
            'payment_kind' => (string) $request->session()->getOldInput(
                'payment_kind',
                ((string) $request->session()->getOldInput('payment_method', 'cash')) === 'credit' ? 'credit' : 'cash'
            ),
            'payment_method' => (string) $request->session()->getOldInput('payment_method', 'cash'),
            'paid_amount' => (string) $request->session()->getOldInput('paid_amount', ''),
            'other_cost_amount' => (string) $request->session()->getOldInput('other_cost_amount', ''),
            'notes' => (string) $request->session()->getOldInput('notes', ''),
            'items' => $this->initialItems($request),
        ];
    }

    /**
     * Build the cashier payload for edit mode.
     *
     * @return array<string, mixed>
     */
    private function buildEditInitialForm(Request $request, Sale $sale): array
    {
        $oldItems = collect($request->session()->getOldInput('items', []))
            ->filter(fn ($item): bool => is_array($item) && isset($item['medicine_id']))
            ->values();

        $baseRows = $this->editRowsFromSale($sale);
        $rowsByKey = $baseRows->keyBy(fn (array $row): string => (string) $row['key']);

        $items = $oldItems->isNotEmpty()
            ? $oldItems->map(function (array $item, int $index) use ($rowsByKey): ?array {
                $rowKey = 'sale-edit-row-'.$index;
                $baseRow = $rowsByKey->get($rowKey);

                if ($baseRow === null) {
                    return null;
                }

                return [
                    ...$baseRow,
                    'quantity' => (string) ($item['quantity'] ?? $baseRow['quantity']),
                    'markup_percentage' => array_key_exists('markup_percentage', $item)
                        ? (string) $item['markup_percentage']
                        : $baseRow['markup_percentage'],
                    'unit_price' => array_key_exists('unit_price', $item)
                        ? (string) $item['unit_price']
                        : $baseRow['unit_price'],
                ];
            })->filter()->values()->all()
            : $baseRows->values()->all();

        return [
            'sale_number' => (string) $request->session()->getOldInput('sale_number', $sale->sale_number),
            'sale_date' => (string) $request->session()->getOldInput('sale_date', $sale->sale_date?->format('Y-m-d\TH:i')),
            'customer_id' => (string) $request->session()->getOldInput('customer_id', (string) $sale->customer_id),
            'payment_kind' => (string) $request->session()->getOldInput(
                'payment_kind',
                (float) $sale->social_amount > 0.001
                    ? 'social'
                    : ($sale->payment_method === 'credit' ? 'credit' : 'cash')
            ),
            'payment_method' => (string) $request->session()->getOldInput(
                'payment_method',
                $sale->payment_method === 'credit' ? 'cash' : $sale->payment_method
            ),
            'paid_amount' => (string) $request->session()->getOldInput(
                'paid_amount',
                $sale->payment_method === 'credit' ? '' : ($sale->paid_amount > 0 ? $sale->paid_amount : '')
            ),
            'other_cost_amount' => (string) $request->session()->getOldInput(
                'other_cost_amount',
                (float) $sale->other_cost_amount > 0.001 ? $sale->other_cost_amount : ''
            ),
            'notes' => (string) $request->session()->getOldInput('notes', $sale->notes ?? ''),
            'items' => $items,
        ];
    }

    /**
     * Build sale edit rows from the current sale allocations.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function editRowsFromSale(Sale $sale): Collection
    {
        return $sale->items
            ->values()
            ->map(function (SaleItem $item, int $index): array {
                $medicine = $item->medicine;
                $smallUnit = $medicine?->small_unit ?: 'unit';
                $batchNumber = trim((string) $item->batch_number_snapshot);
                $groupKey = implode('|', [
                    (int) $item->medicine_id,
                    Str::upper($batchNumber),
                ]);

                return [
                    'key' => 'sale-edit-row-'.$index,
                    'group_order' => $index,
                    'medicine_id' => $item->medicine_id,
                    'medicine_code' => $medicine?->code ?: '-',
                    'medicine_name' => $medicine?->name ?: '-',
                    'principal_name' => $medicine?->principal?->name ?: '-',
                    'composition' => $medicine?->composition ?: '-',
                    'small_unit' => $smallUnit,
                    'stock_batch_id' => $groupKey,
                    'base_unit_cost' => (string) round((float) $item->unit_cost, 2),
                    'stock_quantity' => (string) round((float) $item->quantity, 2),
                    'quantity' => (string) round((float) $item->quantity, 2),
                    'markup_percentage' => (string) round((float) $item->markup_percentage, 2),
                    'unit_price' => (string) round((float) $item->unit_price, 2),
                    'batches' => [[
                        'id' => $groupKey,
                        'batch_number' => $batchNumber !== '' ? $batchNumber : '-',
                        'expiry_date' => $item->expiry_date_snapshot?->format('Y-m-d') ?? '',
                        'expiry_label' => $item->expiry_date_snapshot?->translatedFormat('d M Y') ?? '-',
                        'stock_quantity' => (string) round((float) $item->quantity, 2),
                        'stock_batch_ids' => [(string) $item->stock_batch_id],
                        'label' => trim(($batchNumber !== '' ? $batchNumber : 'Tanpa batch').' / '.$this->formatQuantity((float) $item->quantity).' '.$smallUnit),
                        'sort_expiry' => $item->expiry_date_snapshot?->toDateString() ?? '9999-12-31',
                        'sort_batch' => Str::lower($batchNumber),
                    ]],
                ];
            });
    }

    /**
     * Get active customer options for cashier pricing.
     */
    private function customerOptions(?int $selectedId = null): Collection
    {
        return Customer::query()
            ->with('customerGroup:id,name,markup_percentage,is_active')
            ->where(function ($query) use ($selectedId) {
                $query->where('is_active', true);

                if ($selectedId !== null) {
                    $query->orWhere('id', $selectedId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Normalize submitted sale rows using authoritative pricing and stock.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $items, Customer $customer): array
    {
        $customer->loadMissing('customerGroup:id,name,markup_percentage');
        $defaultMarkupPercentage = round((float) ($customer->customerGroup?->markup_percentage ?? 0), 2);
        $medicines = Medicine::query()
            ->whereIn('id', collect($items)->pluck('medicine_id')->map(fn ($id) => (int) $id)->all())
            ->get()
            ->keyBy('id');
        $stockBatches = StockBatch::query()
            ->whereIn('medicine_id', $medicines->keys())
            ->where('quantity_balance', '>', 0)
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('received_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (StockBatch $batch): string => $this->saleBatchGroupKey($batch));
        $batchGroupByBatchId = $stockBatches
            ->flatten(1)
            ->mapWithKeys(fn (StockBatch $batch): array => [(string) $batch->id => $this->saleBatchGroupKey($batch)]);
        $remainingQuantities = $stockBatches
            ->flatten(1)
            ->mapWithKeys(fn (StockBatch $batch): array => [$batch->id => round((float) $batch->quantity_balance, 2)]);

        return collect($items)
            ->values()
            ->flatMap(function (array $item) use ($medicines, $stockBatches, $batchGroupByBatchId, $remainingQuantities, $defaultMarkupPercentage): array {
                /** @var Medicine|null $medicine */
                $medicine = $medicines->get((int) $item['medicine_id']);

                if ($medicine === null) {
                    throw new RuntimeException('Salah satu obat penjualan tidak ditemukan.');
                }

                $selectedBatchGroup = $this->resolveSelectedSaleBatchGroup(
                    selectedBatchId: $item['stock_batch_id'] ?? null,
                    medicine: $medicine,
                    groupedBatches: $stockBatches,
                    batchGroupByBatchId: $batchGroupByBatchId,
                );

                if ($selectedBatchGroup === null) {
                    throw new RuntimeException('Batch obat '.$medicine->name.' tidak valid untuk transaksi penjualan ini.');
                }

                $quantity = round((float) $item['quantity'], 2);

                if ($quantity <= 0) {
                    throw new RuntimeException('Qty jual harus lebih besar dari nol.');
                }

                $availableStock = round((float) $selectedBatchGroup->sum(
                    fn (StockBatch $stockBatch): float => (float) ($remainingQuantities[$stockBatch->id] ?? 0)
                ), 2);

                if ($quantity > $availableStock + 0.001) {
                    $batchNumber = trim((string) ($selectedBatchGroup->first()?->batch_number ?? ''));

                    throw new RuntimeException(
                        'Stok batch '.($batchNumber !== '' ? $batchNumber : 'tanpa batch').' untuk obat '.$medicine->name.' tidak mencukupi.'
                    );
                }

                $pricingBase = round($this->baseUnitCost($medicine), 2);
                $markupPercentage = round(max((float) ($item['markup_percentage'] ?? $defaultMarkupPercentage), 0), 2);
                $unitPrice = round($pricingBase + ($pricingBase * $markupPercentage / 100), 2);
                $remainingQuantity = $quantity;
                $allocations = [];

                foreach ($selectedBatchGroup as $stockBatch) {
                    $batchId = (int) $stockBatch->id;
                    $availableInBatch = round((float) ($remainingQuantities[$batchId] ?? 0), 2);

                    if ($availableInBatch <= 0) {
                        continue;
                    }

                    $allocatedQuantity = round(min($remainingQuantity, $availableInBatch), 2);

                    if ($allocatedQuantity <= 0) {
                        continue;
                    }

                    $remainingQuantities[$batchId] = round($availableInBatch - $allocatedQuantity, 2);
                    $remainingQuantity = round($remainingQuantity - $allocatedQuantity, 2);
                    $lineTotal = round($allocatedQuantity * $unitPrice, 2);
                    $unitCost = round((float) $stockBatch->purchase_price, 2);

                    $allocations[] = [
                        'medicine' => $medicine,
                        'stock_batch' => $stockBatch,
                        'quantity' => $allocatedQuantity,
                        'unit_cost' => $unitCost,
                        'markup_percentage' => $markupPercentage,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ];

                    if ($remainingQuantity <= 0.001) {
                        break;
                    }
                }

                if ($remainingQuantity > 0.001) {
                    throw new RuntimeException('Saldo batch obat '.$medicine->name.' berubah. Silakan cek ulang qty penjualan.');
                }

                return $allocations;
            })
            ->all();
    }

    /**
     * Create a sale item from the selected stock batch.
     *
     * @param  array{medicine: Medicine, stock_batch: StockBatch, quantity: float, unit_cost: float, markup_percentage: float, unit_price: float, line_total: float}  $item
     */
    private function createSaleAllocations(Sale $sale, array $item, ?int $userId, Carbon $saleDate): void
    {
        /** @var StockBatch|null $stockBatch */
        $stockBatch = StockBatch::query()
            ->lockForUpdate()
            ->find($item['stock_batch']->id);

        if ($stockBatch === null || (int) $stockBatch->medicine_id !== (int) $item['medicine']->id) {
            throw new RuntimeException('Batch yang dipilih untuk obat '.$item['medicine']->name.' tidak ditemukan.');
        }

        $available = round((float) $stockBatch->quantity_balance, 2);

        if ($item['quantity'] > $available + 0.001) {
            throw new RuntimeException('Stok batch '.$stockBatch->batch_number.' untuk obat '.$item['medicine']->name.' berubah. Silakan cek ulang qty penjualan.');
        }

        $lineTotal = round($item['quantity'] * $item['unit_price'], 2);
        $newQuantityOut = round((float) $stockBatch->quantity_out + $item['quantity'], 2);
        $newBalance = round($available - $item['quantity'], 2);

        $saleItem = $sale->items()->create([
            'medicine_id' => $item['medicine']->id,
            'stock_batch_id' => $stockBatch->id,
            'batch_number_snapshot' => $stockBatch->batch_number,
            'expiry_date_snapshot' => $stockBatch->expiry_date,
            'quantity' => $item['quantity'],
            'unit_cost' => $item['unit_cost'],
            'markup_percentage' => $item['markup_percentage'],
            'unit_price' => $item['unit_price'],
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => $lineTotal,
        ]);

        $stockBatch->update([
            'quantity_out' => $newQuantityOut,
            'quantity_balance' => $newBalance,
            'status' => $newBalance > 0 ? 'active' : 'sold_out',
            'notes' => trim((string) $stockBatch->notes.' | Penjualan '.$sale->sale_number),
        ]);

        StockMovement::query()->create([
            'movement_date' => $saleDate,
            'movement_type' => 'sale',
            'reference_table' => 'sale_items',
            'reference_id' => $saleItem->id,
            'medicine_id' => $item['medicine']->id,
            'stock_batch_id' => $stockBatch->id,
            'storage_location_id' => $stockBatch->storage_location_id,
            'quantity_in' => 0,
            'quantity_out' => $item['quantity'],
            'balance_after' => $newBalance,
            'unit_cost' => $stockBatch->purchase_price,
            'notes' => 'Penjualan '.$sale->sale_number,
            'created_by' => $userId,
        ]);
    }

    /**
     * Build detail payloads for sale history.
     *
     * @param  LengthAwarePaginator<int, Sale>  $sales
     * @return array<int, array<string, mixed>>
     */
    private function detailPayloads(LengthAwarePaginator $sales): array
    {
        return $sales->getCollection()
            ->mapWithKeys(function (Sale $sale): array {
                $groupedItems = $this->groupedSaleDisplayItems($sale->items);

                return [
                    $sale->id => [
                        'sale_number' => $sale->sale_number,
                        'sale_date' => $sale->sale_date?->translatedFormat('d M Y H:i') ?? '-',
                        'customer' => $sale->customer_name ?: '-',
                        'payment_status' => $this->paymentStatusLabel($sale),
                        'group_name' => $sale->customerGroup?->name ?: '-',
                        'item_count' => number_format($groupedItems->count()),
                        'subtotal' => $this->formatCurrency((float) $sale->subtotal),
                        'other_cost_amount' => $this->formatCurrency((float) $sale->other_cost_amount),
                        'social_amount' => $this->formatCurrency((float) $sale->social_amount),
                        'paid_amount' => $this->formatCurrency((float) $sale->paid_amount),
                        'change_amount' => $this->formatCurrency((float) $sale->change_amount),
                        'total_amount' => $this->formatCurrency((float) $sale->grand_total),
                        'items' => $groupedItems->map(function (array $item): array {
                            return [
                                'id' => $item['id'],
                                'medicine' => $item['medicine_name'],
                                'medicine_code' => $item['medicine_code'],
                                'batch_number' => $item['batch_number'],
                                'expiry_date' => $item['expiry_date'],
                                'quantity' => $this->formatQuantity((float) $item['quantity']).' '.$item['unit_name'],
                                'unit_price' => $this->formatCurrency((float) $item['unit_price']),
                                'line_total' => $this->formatCurrency((float) $item['line_total']),
                            ];
                        })->values()->all(),
                    ],
                ];
            })
            ->all();
    }

    /**
     * Collapse sale allocation rows into customer-facing display rows.
     *
     * @param  Collection<int, SaleItem>  $items
     * @return Collection<int, array{id: string, medicine_name: string, medicine_code: string, batch_number: string, expiry_date: string, quantity: float, unit_name: string, unit_price: float, discount_amount: float, line_total: float}>
     */
    private function groupedSaleDisplayItems(Collection $items): Collection
    {
        $groups = [];

        foreach ($items as $item) {
            $batchNumber = trim((string) $item->batch_number_snapshot);
            $unitPrice = round((float) $item->unit_price, 2);
            $discountAmount = round((float) $item->discount_amount, 2);
            $groupKey = implode('|', [
                (int) $item->medicine_id,
                Str::upper($batchNumber),
                number_format($unitPrice, 2, '.', ''),
                number_format($discountAmount, 2, '.', ''),
            ]);

            if (! array_key_exists($groupKey, $groups)) {
                $groups[$groupKey] = [
                    'id' => 'sale-display-'.$groupKey,
                    'medicine_name' => $item->medicine?->name ?: '-',
                    'medicine_code' => $item->medicine?->code ?: '-',
                    'batch_number' => $batchNumber !== '' ? $batchNumber : '-',
                    'expiry_dates' => collect(),
                    'quantity' => 0.0,
                    'unit_name' => $item->medicine?->small_unit ?: 'unit',
                    'unit_price' => $unitPrice,
                    'discount_amount' => 0.0,
                    'line_total' => 0.0,
                ];
            }

            $groups[$groupKey]['expiry_dates']->push($item->expiry_date_snapshot);
            $groups[$groupKey]['quantity'] = round($groups[$groupKey]['quantity'] + (float) $item->quantity, 2);
            $groups[$groupKey]['discount_amount'] = round($groups[$groupKey]['discount_amount'] + (float) $item->discount_amount, 2);
            $groups[$groupKey]['line_total'] = round($groups[$groupKey]['line_total'] + (float) $item->line_total, 2);
        }

        return collect($groups)
            ->values()
            ->map(function (array $group): array {
                $group['expiry_date'] = $this->collapseSaleBatchDates($group['expiry_dates']);
                unset($group['expiry_dates']);

                return $group;
            });
    }

    /**
     * Resolve the display payment status for a sale.
     */
    private function paymentStatusLabel(Sale $sale): string
    {
        if ((float) $sale->social_amount > 0.001) {
            return 'Sosial';
        }

        return $sale->payment_method === 'credit' && $this->outstandingAmount($sale) > 0.001
            ? 'Kredit'
            : 'Lunas';
    }

    /**
     * Resolve the display badge tone for a sale payment status.
     */
    private function paymentStatusTone(Sale $sale): string
    {
        if ((float) $sale->social_amount > 0.001) {
            return 'border-sky-200 bg-sky-50 text-sky-700';
        }

        return $sale->payment_method === 'credit' && $this->outstandingAmount($sale) > 0.001
            ? 'border-amber-200 bg-amber-50 text-amber-700'
            : 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    /**
     * Resolve the display payment method label for print output.
     */
    private function paymentMethodLabel(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'credit' => 'KREDIT',
            'transfer' => 'TRANSFER',
            'qris' => 'QRIS',
            'debit' => 'DEBIT',
            default => 'CASH',
        };
    }

    /**
     * Append a social-sale note when the customer pays below the item subtotal.
     */
    private function saleNotesWithSocialAmount(
        ?string $notes,
        string $paymentKind,
        string $paymentMethod,
        float $paidAmount,
        float $grandTotal,
        float $socialAmount,
    ): ?string {
        $cleanNotes = filled($notes) ? trim((string) $notes) : null;

        if ($paymentKind !== 'social') {
            return $cleanNotes;
        }

        $paymentMethodLabel = match ($paymentMethod) {
            'qris' => 'QRIS',
            'transfer' => 'transfer',
            'debit' => 'debit',
            default => 'tunai',
        };
        $socialNote = sprintf(
            'Penjualan sosial. Nominal dibayar %s %s, nilai sosial %s dari total penjualan %s.',
            $paymentMethodLabel,
            $this->formatCurrency($paidAmount),
            $this->formatCurrency($socialAmount),
            $this->formatCurrency($grandTotal),
        );

        return $cleanNotes !== null && $cleanNotes !== ''
            ? $cleanNotes.' | '.$socialNote
            : $socialNote;
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
     * Determine the pricing base from the latest known unit cost.
     */
    private function baseUnitCost(Medicine $medicine): float
    {
        $purchasePrice = round((float) $medicine->purchase_price, 2);

        if ($purchasePrice > 0) {
            return $purchasePrice;
        }

        $batchPrice = StockBatch::query()
            ->where('medicine_id', $medicine->id)
            ->where('quantity_balance', '>', 0)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->value('purchase_price');

        return round((float) $batchPrice, 2);
    }

    /**
     * Group stock batches for cashier dropdowns using the same medicine+batch rule as stock pages.
     *
     * @param  Collection<int, StockBatch>  $batches
     * @return Collection<int, array{id: string, batch_number: string, expiry_date: string, expiry_label: string, stock_quantity: string, stock_batch_ids: array<int, string>, label: string, sort_expiry: string, sort_batch: string}>
     */
    private function groupedSaleBatchOptions(Collection $batches, string $smallUnit): Collection
    {
        return $batches
            ->groupBy(fn (StockBatch $batch): string => $this->saleBatchGroupKey($batch))
            ->map(function (Collection $groupedBatches, string $groupKey) use ($smallUnit): array {
                /** @var StockBatch|null $firstBatch */
                $firstBatch = $groupedBatches->first();
                $batchNumber = trim((string) ($firstBatch?->batch_number ?? ''));
                $quantityBalance = round((float) $groupedBatches->sum(
                    fn (StockBatch $batch): float => (float) $batch->quantity_balance
                ), 2);
                $sortExpiry = $groupedBatches
                    ->pluck('expiry_date')
                    ->filter()
                    ->map(fn ($date) => $date?->toDateString())
                    ->sort()
                    ->first() ?: '9999-12-31';

                return [
                    'id' => $groupKey,
                    'batch_number' => $batchNumber !== '' ? $batchNumber : '-',
                    'expiry_date' => $firstBatch?->expiry_date?->format('Y-m-d') ?? '',
                    'expiry_label' => $this->collapseSaleBatchDates($groupedBatches->pluck('expiry_date'), 'd/m/Y'),
                    'stock_quantity' => (string) $quantityBalance,
                    'stock_batch_ids' => $groupedBatches
                        ->pluck('id')
                        ->map(fn ($id): string => (string) $id)
                        ->values()
                        ->all(),
                    'label' => trim(($batchNumber !== '' ? $batchNumber : 'Tanpa batch').' / '.$this->formatQuantity($quantityBalance).' '.$smallUnit),
                    'sort_expiry' => $sortExpiry,
                    'sort_batch' => Str::lower($batchNumber),
                ];
            })
            ->sortBy(fn (array $batch): string => implode('|', [
                $batch['sort_expiry'],
                $batch['sort_batch'],
            ]))
            ->values();
    }

    /**
     * Match an old selected batch value to a grouped cashier batch option.
     *
     * @param  Collection<int, array{id: string, stock_batch_ids: array<int, string>}>  $batchOptions
     * @return array<string, mixed>|null
     */
    private function resolveSaleBatchOption(Collection $batchOptions, mixed $selectedValue): ?array
    {
        if ($selectedValue === null || $selectedValue === '') {
            return null;
        }

        $normalizedValue = trim((string) $selectedValue);

        return $batchOptions->first(function (array $option) use ($normalizedValue): bool {
            return $option['id'] === $normalizedValue
                || in_array($normalizedValue, $option['stock_batch_ids'] ?? [], true);
        });
    }

    /**
     * Resolve the grouped stock batches selected from the cashier batch dropdown.
     *
     * @param  Collection<string, Collection<int, StockBatch>>  $groupedBatches
     * @param  Collection<string, string>  $batchGroupByBatchId
     * @return Collection<int, StockBatch>|null
     */
    private function resolveSelectedSaleBatchGroup(
        mixed $selectedBatchId,
        Medicine $medicine,
        Collection $groupedBatches,
        Collection $batchGroupByBatchId,
    ): ?Collection {
        $normalizedValue = trim((string) $selectedBatchId);

        if ($normalizedValue === '') {
            return null;
        }

        $groupKey = $groupedBatches->has($normalizedValue)
            ? $normalizedValue
            : $batchGroupByBatchId->get($normalizedValue);

        if (! is_string($groupKey)) {
            return null;
        }

        /** @var Collection<int, StockBatch>|null $selectedGroup */
        $selectedGroup = $groupedBatches->get($groupKey);

        if ($selectedGroup === null || $selectedGroup->isEmpty()) {
            return null;
        }

        return (int) $selectedGroup->first()->medicine_id === (int) $medicine->id
            ? $selectedGroup
            : null;
    }

    /**
     * Build the grouping key for cashier batch selections.
     */
    private function saleBatchGroupKey(StockBatch $batch): string
    {
        return implode('|', [
            (int) $batch->medicine_id,
            Str::upper(trim((string) $batch->batch_number)),
        ]);
    }

    /**
     * Collapse grouped batch expiry dates into one readable label.
     *
     * @param  Collection<int, mixed>  $dates
     */
    private function collapseSaleBatchDates(Collection $dates, string $dateFormat = 'd M Y'): string
    {
        $uniqueDates = $dates
            ->filter()
            ->map(fn ($date): string => $date?->toDateString())
            ->unique()
            ->sort()
            ->values();

        if ($uniqueDates->isEmpty()) {
            return '-';
        }

        if ($uniqueDates->count() === 1) {
            return optional($dates->first())->translatedFormat($dateFormat) ?? '-';
        }

        $firstDate = $dates
            ->filter(fn ($date) => $date?->toDateString() === $uniqueDates->first())
            ->first();
        $lastDate = $dates
            ->filter(fn ($date) => $date?->toDateString() === $uniqueDates->last())
            ->first();

        return ($firstDate?->translatedFormat($dateFormat) ?? $uniqueDates->first())
            .' s.d. '
            .($lastDate?->translatedFormat($dateFormat) ?? $uniqueDates->last());
    }

    /**
     * Remove the appended sale note from a stock batch.
     */
    private function removeSaleNote(string $notes, string $saleNumber): ?string
    {
        $cleaned = str_replace(' | Penjualan '.$saleNumber, '', $notes);
        $cleaned = str_replace('Penjualan '.$saleNumber.' | ', '', $cleaned);
        $cleaned = trim($cleaned, " |\t\n\r\0\x0B");

        return $cleaned !== '' ? $cleaned : null;
    }

    /**
     * Generate the next sale number.
     */
    private function nextSaleNumber(): string
    {
        $latestCode = Sale::query()
            ->where('sale_number', 'like', 'PJL-%')
            ->orderByDesc('id')
            ->value('sale_number');

        if (! is_string($latestCode) || ! preg_match('/(\d+)$/', $latestCode, $matches)) {
            return 'PJL-0001';
        }

        return 'PJL-'.str_pad((string) ((int) $matches[1] + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Build the page metadata for the sales module.
     *
     * @return array<string, mixed>
     */
    private function pageData(string $routeName): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $item): bool => $item['label'] === 'Penjualan');

        $siblings = $section['children'] ?? [];
        $page = collect($siblings)->firstWhere('route', $routeName);

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Penjualan',
            'siblings' => $siblings,
        ];
    }

    /**
     * Format whole quantity values.
     */
    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 0, ',', '.');
    }

    /**
     * Format currency values.
     */
    private function formatCurrency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    /**
     * Calculate the remaining receivable amount for a sale.
     */
    private function outstandingAmount(Sale $sale): float
    {
        if ($sale->payment_method !== 'credit') {
            return 0.0;
        }

        return max(round((float) $sale->grand_total - (float) $sale->paid_amount, 2), 0);
    }
}
