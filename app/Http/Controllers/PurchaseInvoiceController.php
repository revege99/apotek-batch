<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseInvoiceRequest;
use App\Models\Medicine;
use App\Models\PurchaseInvoice;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceController extends Controller
{
    /**
     * Display the purchase invoice history.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $today = now()->toDateString();
        $dateFrom = trim((string) $request->query('date_from', $today));
        $dateTo = trim((string) $request->query('date_to', $today));

        $invoices = PurchaseInvoice::query()
            ->with([
                'supplier:id,name',
                'items' => fn ($query) => $query
                    ->with([
                        'medicine:id,code,name',
                        'stockBatch:id,purchase_invoice_item_id,purchase_price',
                    ])
                    ->orderBy('id'),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($invoiceQuery) use ($search) {
                    $invoiceQuery
                        ->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('items', function ($itemQuery) use ($search) {
                            $itemQuery
                                ->where('batch_number', 'like', "%{$search}%")
                                ->orWhereHas('medicine', function ($medicineQuery) use ($search) {
                                    $medicineQuery
                                        ->where('code', 'like', "%{$search}%")
                                        ->orWhere('name', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('invoice_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('invoice_date', '<=', $dateTo))
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('purchases.index', [
            ...$this->pageData('pembelian.data-pembelian'),
            'invoices' => $invoices,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'stats' => [
                'total' => PurchaseInvoice::query()->count(),
                'today' => PurchaseInvoice::query()->whereDate('invoice_date', now()->toDateString())->count(),
                'grand_total' => (float) PurchaseInvoice::query()->sum('grand_total'),
            ],
        ]);
    }

    /**
     * Display the purchase invoice input page.
     */
    public function create(Request $request): View
    {
        return view('purchases.create', [
            ...$this->pageData('pembelian.input-faktur-pembelian'),
            'supplierOptions' => $this->supplierOptions(($request->session()->getOldInput('supplier_id') !== null)
                ? (int) $request->session()->getOldInput('supplier_id')
                : null),
            'locationOptions' => $this->locationOptions($request),
            'initialForm' => [
                'invoice_number' => (string) $request->session()->getOldInput('invoice_number', ''),
                'invoice_date' => (string) $request->session()->getOldInput('invoice_date', now()->format('Y-m-d')),
                'supplier_id' => (string) $request->session()->getOldInput('supplier_id', ''),
                'payment_method' => (string) $request->session()->getOldInput('payment_method', 'credit'),
                'tax_percentage' => (string) $request->session()->getOldInput('tax_percentage', '11'),
                'items' => $this->initialItems($request),
            ],
            'formAction' => route('pembelian.input-faktur-pembelian.store'),
            'formMethod' => null,
        ]);
    }

    /**
     * Display the form for editing a purchase invoice.
     */
    public function edit(Request $request, PurchaseInvoice $purchaseInvoice): View|RedirectResponse
    {
        if ($message = $this->invoiceModificationBlockReason($purchaseInvoice)) {
            return redirect()
                ->route('pembelian.data-pembelian')
                ->with('toast', ['type' => 'error', 'message' => $message]);
        }

        return view('purchases.create', [
            ...$this->pageData('pembelian.input-faktur-pembelian'),
            'supplierOptions' => $this->supplierOptions($purchaseInvoice->supplier_id),
            'locationOptions' => $this->locationOptions($request, $purchaseInvoice),
            'initialForm' => [
                'invoice_number' => (string) $request->session()->getOldInput('invoice_number', $purchaseInvoice->invoice_number),
                'invoice_date' => (string) $request->session()->getOldInput('invoice_date', $purchaseInvoice->invoice_date?->format('Y-m-d')),
                'supplier_id' => (string) $request->session()->getOldInput('supplier_id', $purchaseInvoice->supplier_id),
                'payment_method' => (string) $request->session()->getOldInput('payment_method', $this->resolveInitialPaymentMethod($purchaseInvoice)),
                'tax_percentage' => (string) $request->session()->getOldInput('tax_percentage', $purchaseInvoice->tax_percentage),
                'items' => $this->initialItems($request, $purchaseInvoice),
            ],
            'formAction' => route('pembelian.data-pembelian.update', $purchaseInvoice),
            'formMethod' => 'PATCH',
        ]);
    }

    /**
     * Store a newly created purchase invoice.
     */
    public function store(PurchaseInvoiceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $supplier = Supplier::query()->findOrFail($validated['supplier_id']);
        $invoiceDate = Carbon::parse($validated['invoice_date']);
        $paymentMethod = (string) $validated['payment_method'];
        $taxPercentage = round((float) $validated['tax_percentage'], 2);
        $normalizedItems = $this->normalizeItems($validated['items'], $taxPercentage);

        $invoice = DB::transaction(function () use ($request, $validated, $supplier, $invoiceDate, $paymentMethod, $normalizedItems, $taxPercentage) {
            $subtotal = round(collect($normalizedItems)->sum('gross_subtotal'), 2);
            $discountAmount = round(collect($normalizedItems)->sum('discount_amount'), 0);
            $taxAmount = round(collect($normalizedItems)->sum('tax_amount'), 2);
            $grandTotal = round(collect($normalizedItems)->sum('landed_total'), 2);
            $paymentState = $this->resolvePaymentState($paymentMethod, $supplier, $invoiceDate, $grandTotal);

            $invoice = PurchaseInvoice::query()->create([
                'invoice_number' => $validated['invoice_number'],
                'supplier_id' => $supplier->id,
                'invoice_date' => $invoiceDate->toDateString(),
                'received_date' => $invoiceDate->toDateString(),
                'due_date' => $paymentState['due_date'],
                'status' => 'posted',
                'payment_status' => $paymentState['payment_status'],
                'payment_method' => $paymentState['payment_method'],
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_percentage' => $taxPercentage,
                'tax_amount' => $taxAmount,
                'other_cost_amount' => 0,
                'grand_total' => $grandTotal,
                'paid_amount' => $paymentState['paid_amount'],
                'outstanding_amount' => $paymentState['outstanding_amount'],
                'notes' => null,
                'created_by' => $request->user()?->id,
            ]);

            $this->createInvoiceItems($invoice, $normalizedItems, $invoiceDate, $request->user()?->id);

            return $invoice;
        });

        return redirect()
            ->route('pembelian.input-faktur-pembelian')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Faktur pembelian '.$invoice->invoice_number.' berhasil disimpan.',
            ]);
    }

    /**
     * Update a purchase invoice and rebuild its stock receipt.
     */
    public function update(PurchaseInvoiceRequest $request, PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        if ($message = $this->invoiceModificationBlockReason($purchaseInvoice)) {
            return redirect()
                ->route('pembelian.data-pembelian')
                ->with('toast', ['type' => 'error', 'message' => $message]);
        }

        $validated = $request->validated();
        $supplier = Supplier::query()->findOrFail($validated['supplier_id']);
        $invoiceDate = Carbon::parse($validated['invoice_date']);
        $paymentMethod = (string) $validated['payment_method'];
        $taxPercentage = round((float) $validated['tax_percentage'], 2);
        $normalizedItems = $this->normalizeItems($validated['items'], $taxPercentage);

        DB::transaction(function () use ($request, $validated, $supplier, $invoiceDate, $paymentMethod, $normalizedItems, $purchaseInvoice, $taxPercentage) {
            $subtotal = round(collect($normalizedItems)->sum('gross_subtotal'), 2);
            $discountAmount = round(collect($normalizedItems)->sum('discount_amount'), 0);
            $taxAmount = round(collect($normalizedItems)->sum('tax_amount'), 2);
            $grandTotal = round(collect($normalizedItems)->sum('landed_total'), 2);
            $paymentState = $this->resolvePaymentState($paymentMethod, $supplier, $invoiceDate, $grandTotal);

            $this->deleteInvoiceInventory($purchaseInvoice);

            $purchaseInvoice->update([
                'invoice_number' => $validated['invoice_number'],
                'supplier_id' => $supplier->id,
                'invoice_date' => $invoiceDate->toDateString(),
                'received_date' => $invoiceDate->toDateString(),
                'due_date' => $paymentState['due_date'],
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'payment_status' => $paymentState['payment_status'],
                'payment_method' => $paymentState['payment_method'],
                'tax_percentage' => $taxPercentage,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
                'paid_amount' => $paymentState['paid_amount'],
                'outstanding_amount' => $paymentState['outstanding_amount'],
            ]);

            $this->createInvoiceItems($purchaseInvoice, $normalizedItems, $invoiceDate, $request->user()?->id);
        });

        return redirect()
            ->route('pembelian.data-pembelian')
            ->with('toast', ['type' => 'success', 'message' => 'Faktur pembelian berhasil diperbarui.']);
    }

    /**
     * Delete a purchase invoice and its unused stock receipt.
     */
    public function destroy(Request $request, PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        $redirectQuery = $this->historyQueryParams($request);

        if ($message = $this->invoiceModificationBlockReason($purchaseInvoice)) {
            return redirect()
                ->route('pembelian.data-pembelian', $redirectQuery)
                ->with('toast', ['type' => 'error', 'message' => $message]);
        }

        $invoiceNumber = $purchaseInvoice->invoice_number;

        DB::transaction(function () use ($purchaseInvoice) {
            $this->deleteInvoiceInventory($purchaseInvoice);
            $purchaseInvoice->delete();
        });

        return redirect()
            ->route('pembelian.data-pembelian', $redirectQuery)
            ->with('toast', ['type' => 'success', 'message' => "Faktur pembelian {$invoiceNumber} berhasil dihapus."]);
    }

    /**
     * Create invoice items and their stock receipt records.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function createInvoiceItems(PurchaseInvoice $invoice, array $items, Carbon $invoiceDate, ?int $userId): void
    {
        foreach ($items as $item) {
            $invoiceItem = $invoice->items()->create([
                'medicine_id' => $item['medicine']->id,
                'storage_location_id' => $item['storage_location_id'],
                'purchase_unit' => $item['purchase_unit'],
                'unit_content' => $item['unit_content'],
                'batch_number' => $item['batch_number'],
                'expiry_date' => $item['expiry_date'],
                'quantity' => $item['quantity'],
                'bonus_quantity' => 0,
                'unit_price' => $item['unit_price'],
                'discount_percentage' => $item['discount_percentage'],
                'discount_amount' => $item['discount_amount'],
                'tax_amount' => $item['tax_amount'],
                'line_total' => $item['line_total'],
            ]);

            $unitCost = $item['landed_unit_cost'];

            $stockBatch = StockBatch::query()->create([
                'medicine_id' => $item['medicine']->id,
                'purchase_invoice_item_id' => $invoiceItem->id,
                'storage_location_id' => $item['storage_location_id'],
                'batch_number' => $item['batch_number'],
                'expiry_date' => $item['expiry_date'],
                'received_at' => $invoiceDate->toDateString(),
                'purchase_price' => $unitCost,
                'selling_price' => 0,
                'initial_quantity' => $item['stock_quantity'],
                'quantity_in' => $item['stock_quantity'],
                'quantity_out' => 0,
                'quantity_balance' => $item['stock_quantity'],
                'status' => 'active',
                'notes' => 'Penerimaan dari faktur '.$invoice->invoice_number,
            ]);

            StockMovement::query()->create([
                'movement_date' => now(),
                'movement_type' => 'purchase_receipt',
                'reference_table' => 'purchase_invoice_items',
                'reference_id' => $invoiceItem->id,
                'medicine_id' => $item['medicine']->id,
                'stock_batch_id' => $stockBatch->id,
                'storage_location_id' => $item['storage_location_id'],
                'quantity_in' => $item['stock_quantity'],
                'quantity_out' => 0,
                'balance_after' => $item['stock_quantity'],
                'unit_cost' => $unitCost,
                'notes' => 'Input faktur pembelian '.$invoice->invoice_number,
                'created_by' => $userId,
            ]);

            if ($item['update_master_purchase_price']) {
                $item['medicine']->update(['purchase_price' => $item['landed_unit_cost']]);
            }
        }
    }

    /**
     * Remove stock receipt records generated by an invoice.
     */
    private function deleteInvoiceInventory(PurchaseInvoice $invoice): void
    {
        $itemIds = $invoice->items()->pluck('id');
        $batchIds = StockBatch::query()
            ->whereIn('purchase_invoice_item_id', $itemIds)
            ->pluck('id');

        StockMovement::query()
            ->where(function ($query) use ($itemIds, $batchIds) {
                $query
                    ->whereIn('stock_batch_id', $batchIds)
                    ->orWhere(function ($movementQuery) use ($itemIds) {
                        $movementQuery
                            ->where('reference_table', 'purchase_invoice_items')
                            ->whereIn('reference_id', $itemIds);
                    });
            })
            ->delete();

        StockBatch::query()->whereIn('id', $batchIds)->delete();
        $invoice->items()->delete();
    }

    /**
     * Explain why a posted invoice can no longer be edited or deleted.
     */
    private function invoiceModificationBlockReason(PurchaseInvoice $invoice): ?string
    {
        $hasSupplierPaymentAllocation = DB::table('supplier_payment_allocations')
            ->where('purchase_invoice_id', $invoice->id)
            ->exists();
        $hasCreditPaymentProgress = trim((string) $invoice->payment_method) === 'credit'
            && (float) $invoice->paid_amount > 0.001;

        if ($hasSupplierPaymentAllocation || $hasCreditPaymentProgress) {
            return 'Faktur tidak dapat diubah karena sudah memiliki pembayaran supplier.';
        }

        if (DB::table('purchase_returns')->where('purchase_invoice_id', $invoice->id)->exists()) {
            return 'Faktur tidak dapat diubah karena sudah memiliki retur pembelian.';
        }

        $itemIds = $invoice->items()->pluck('id');
        $batches = StockBatch::query()
            ->whereIn('purchase_invoice_item_id', $itemIds)
            ->get(['id', 'quantity_in', 'quantity_out', 'quantity_balance']);
        $batchIds = $batches->pluck('id');

        if ($batches->contains(fn (StockBatch $batch): bool => (float) $batch->quantity_out > 0 || abs((float) $batch->quantity_balance - (float) $batch->quantity_in) > 0.001)) {
            return 'Faktur tidak dapat diubah karena stok dari faktur ini sudah dipakai atau disesuaikan.';
        }

        if (DB::table('stock_opname_items')->whereIn('stock_batch_id', $batchIds)->exists()
            || DB::table('sale_items')->whereIn('stock_batch_id', $batchIds)->exists()
            || DB::table('sale_return_items')->whereIn('stock_batch_id', $batchIds)->exists()) {
            return 'Faktur tidak dapat diubah karena batch sudah terhubung dengan transaksi stok lain.';
        }

        $hasAdditionalMovement = StockMovement::query()
            ->whereIn('stock_batch_id', $batchIds)
            ->where(function ($query) use ($itemIds) {
                $query
                    ->where('movement_type', '!=', 'purchase_receipt')
                    ->orWhere('reference_table', '!=', 'purchase_invoice_items')
                    ->orWhereNotIn('reference_id', $itemIds);
            })
            ->exists();

        return $hasAdditionalMovement
            ? 'Faktur tidak dapat diubah karena batch sudah memiliki mutasi stok lanjutan.'
            : null;
    }

    /**
     * Resolve the initial payment method value for the form.
     */
    private function resolveInitialPaymentMethod(PurchaseInvoice $invoice): string
    {
        $paymentMethod = trim((string) $invoice->payment_method);

        if (in_array($paymentMethod, ['cash', 'transfer', 'qris', 'debit', 'credit'], true)) {
            return $paymentMethod;
        }

        return $invoice->payment_status === 'paid'
            ? 'cash'
            : 'credit';
    }

    /**
     * Derive the payment fields for a purchase invoice from the chosen method.
     *
     * @return array{due_date:?string,payment_status:string,payment_method:string,paid_amount:float,outstanding_amount:float}
     */
    private function resolvePaymentState(string $paymentMethod, Supplier $supplier, Carbon $invoiceDate, float $grandTotal): array
    {
        if ($paymentMethod === 'credit') {
            return [
                'due_date' => $supplier->payment_term_days > 0 ? $invoiceDate->copy()->addDays($supplier->payment_term_days)->toDateString() : null,
                'payment_status' => 'unpaid',
                'payment_method' => 'credit',
                'paid_amount' => 0.0,
                'outstanding_amount' => $grandTotal,
            ];
        }

        return [
            'due_date' => null,
            'payment_status' => 'paid',
            'payment_method' => $paymentMethod,
            'paid_amount' => $grandTotal,
            'outstanding_amount' => 0.0,
        ];
    }

    /**
     * Preserve the active purchase history filter when redirecting back.
     *
     * @return array<string, string>
     */
    private function historyQueryParams(Request $request): array
    {
        return collect([
            'search' => trim((string) $request->input('search', '')),
            'date_from' => trim((string) $request->input('date_from', '')),
            'date_to' => trim((string) $request->input('date_to', '')),
        ])
            ->filter(fn (string $value): bool => $value !== '')
            ->all();
    }

    /**
     * Build the page metadata for the purchase module.
     *
     * @return array<string, mixed>
     */
    private function pageData(string $routeName): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $item): bool => $item['label'] === 'Pembelian');

        $siblings = $section['children'] ?? [];
        $page = collect($siblings)->firstWhere('route', $routeName);

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Pembelian',
            'siblings' => $siblings,
        ];
    }

    /**
     * Get active supplier options for the invoice form.
     */
    private function supplierOptions(?int $selectedSupplierId = null): Collection
    {
        return Supplier::query()
            ->select(['id', 'name', 'city', 'is_active'])
            ->where(function ($query) use ($selectedSupplierId) {
                $query->where('is_active', true);

                if ($selectedSupplierId !== null) {
                    $query->orWhere('id', $selectedSupplierId);
                }
            })
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * Get active storage location options for the invoice form.
     */
    private function locationOptions(Request $request, ?PurchaseInvoice $purchaseInvoice = null): Collection
    {
        $selectedLocationIds = collect($request->session()->getOldInput('items', []))
            ->filter(fn ($item): bool => is_array($item))
            ->pluck('storage_location_id')
            ->filter()
            ->map(fn ($id): int => (int) $id);

        if ($purchaseInvoice !== null) {
            $selectedLocationIds = $selectedLocationIds->merge(
                $purchaseInvoice->items()
                    ->whereNotNull('storage_location_id')
                    ->pluck('storage_location_id')
                    ->map(fn ($id): int => (int) $id)
            );
        }

        $selectedLocationIds = $selectedLocationIds
            ->unique()
            ->values();

        return StorageLocation::query()
            ->select(['id', 'name', 'is_active'])
            ->where(function ($query) use ($selectedLocationIds) {
                $query->where('is_active', true);

                if ($selectedLocationIds->isNotEmpty()) {
                    $query->orWhereIn('id', $selectedLocationIds);
                }
            })
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * Build the initial detail rows for the form.
     *
     * @return array<int, array<string, mixed>>
     */
    private function initialItems(Request $request, ?PurchaseInvoice $purchaseInvoice = null): array
    {
        $defaultLocationId = $this->defaultPurchaseLocationId();
        $itemInput = $request->session()->getOldInput('items');

        if ($itemInput === null && $purchaseInvoice !== null) {
            $itemInput = $purchaseInvoice->items()
                ->get()
                ->map(fn ($item): array => [
                    'medicine_id' => $item->medicine_id,
                    'storage_location_id' => $item->storage_location_id ?: $defaultLocationId,
                    'unit_content' => $item->unit_content,
                    'batch_number' => $item->batch_number,
                    'expiry_date' => $item->expiry_date?->format('Y-m-d'),
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_percentage' => $item->discount_percentage,
                    'discount_amount' => $item->discount_amount,
                    'discount_mode' => (float) $item->discount_amount > 0 ? 'amount' : 'percent',
                    'update_master_purchase_price' => '0',
                ])
                ->all();
        }

        $rawItems = collect($itemInput ?? [])
            ->filter(fn ($item) => is_array($item))
            ->filter(fn (array $item): bool => isset($item['medicine_id']))
            ->values();

        $oldMedicineIds = $rawItems
            ->pluck('medicine_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $medicineRows = Medicine::query()
            ->where(function ($query) use ($oldMedicineIds) {
                $query->where('is_active', true);

                if ($oldMedicineIds->isNotEmpty()) {
                    $query->orWhereIn('id', $oldMedicineIds);
                }
            })
            ->orderBy('name')
            ->get()
            ->values()
            ->map(function (Medicine $medicine, int $groupOrder) use ($defaultLocationId): array {
                return [
                    'key' => 'medicine-'.$medicine->id.'-base',
                    'group_order' => $groupOrder,
                    'medicine_id' => $medicine->id,
                    'medicine_code' => $medicine->code,
                    'medicine_name' => $medicine->name,
                    'medicine_label' => trim($medicine->code.' - '.$medicine->name),
                    'composition' => $medicine->composition ?: '',
                    'purchase_unit' => $medicine->small_unit ?: '-',
                    'unit_content' => $medicine->small_unit_per_large_unit ?: 1,
                    'storage_location_id' => $defaultLocationId,
                    'batch_number' => '',
                    'expiry_date' => '',
                    'quantity' => '',
                    'unit_price' => '',
                    'discount_percentage' => '',
                    'discount_amount' => '',
                    'discount_mode' => 'percent',
                ];
            })
            ->keyBy(fn (array $row): int => (int) $row['medicine_id']);

        if ($rawItems->isEmpty()) {
            return $medicineRows->values()->all();
        }

        $rows = [];
        $usedMedicineIds = [];

        foreach ($rawItems as $index => $item) {
            $medicineId = (int) ($item['medicine_id'] ?? 0);
            $baseRow = $medicineRows->get($medicineId);

            if ($baseRow === null) {
                continue;
            }

            $rows[] = [
                ...$baseRow,
                'key' => 'medicine-'.$medicineId.'-old-'.$index,
                'storage_location_id' => (string) (($item['storage_location_id'] ?? '') !== '' ? $item['storage_location_id'] : $defaultLocationId),
                'unit_content' => (string) ($item['unit_content'] ?? $baseRow['unit_content']),
                'batch_number' => (string) ($item['batch_number'] ?? ''),
                'expiry_date' => (string) ($item['expiry_date'] ?? ''),
                'quantity' => (string) ($item['quantity'] ?? ''),
                'unit_price' => (string) ($item['unit_price'] ?? ''),
                'discount_percentage' => (string) ($item['discount_percentage'] ?? ''),
                'discount_amount' => (string) ($item['discount_amount'] ?? ''),
                'discount_mode' => in_array($item['discount_mode'] ?? null, ['percent', 'amount'], true) ? $item['discount_mode'] : 'percent',
                'update_master_purchase_price' => array_key_exists('update_master_purchase_price', $item)
                    ? (string) $item['update_master_purchase_price']
                    : '0',
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
     * Resolve the default purchase location code used by the form.
     */
    private function defaultPurchaseLocationId(): string
    {
        $defaultLocation = StorageLocation::query()
            ->select(['id'])
            ->where('code', 'LOC-0001')
            ->first();

        return $defaultLocation?->id ? (string) $defaultLocation->id : '';
    }

    /**
     * Normalize the item payload and derive all totals server-side.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $items, float $taxPercentage): array
    {
        $medicines = Medicine::query()
            ->whereIn('id', collect($items)->pluck('medicine_id')->map(fn ($id) => (int) $id)->unique())
            ->get()
            ->keyBy('id');

        $normalizedItems = collect($items)
            ->values()
            ->map(function (array $item) use ($medicines): array {
                /** @var Medicine $medicine */
                $medicine = $medicines->get((int) $item['medicine_id']);
                $quantity = round(max((float) $item['quantity'], 0), 2);
                $unitPrice = round(max((float) $item['unit_price'], 0), 2);
                $grossSubtotal = round($quantity * $unitPrice, 2);
                $discountMode = in_array($item['discount_mode'] ?? null, ['percent', 'amount'], true) ? $item['discount_mode'] : 'percent';
                $discountPercentage = round(max((float) ($item['discount_percentage'] ?? 0), 0), 2);
                $discountAmount = round(max((float) ($item['discount_amount'] ?? 0), 0), 0);

                if ($discountMode === 'amount') {
                    $discountAmount = min($discountAmount, $grossSubtotal);
                    $discountPercentage = $grossSubtotal > 0 ? round(($discountAmount / $grossSubtotal) * 100, 2) : 0;
                } else {
                    $discountPercentage = min($discountPercentage, 100);
                    $discountAmount = round($grossSubtotal * $discountPercentage / 100, 0);
                }

                $lineTotal = round(max($grossSubtotal - $discountAmount, 0), 2);
                $unitContent = round(max((float) ($item['unit_content'] ?? ($medicine->small_unit_per_large_unit ?: 1)), 1), 2);
                $stockQuantity = round($quantity * $unitContent, 2);
                $storageLocationId = filled($item['storage_location_id'] ?? null) ? (int) $item['storage_location_id'] : null;

                return [
                    'medicine' => $medicine,
                    'storage_location_id' => $storageLocationId,
                    'purchase_unit' => $medicine->small_unit ?: '-',
                    'unit_content' => $unitContent,
                    'batch_number' => strtoupper(trim((string) $item['batch_number'])),
                    'expiry_date' => $item['expiry_date'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percentage' => $discountPercentage,
                    'discount_amount' => $discountAmount,
                    'gross_subtotal' => $grossSubtotal,
                    'line_total' => $lineTotal,
                    'stock_quantity' => $stockQuantity,
                    'update_master_purchase_price' => array_key_exists('update_master_purchase_price', $item)
                        ? filter_var($item['update_master_purchase_price'], FILTER_VALIDATE_BOOL)
                        : true,
                ];
            })
            ->groupBy(function (array $item): string {
                return implode('|', [
                    (int) $item['medicine']->id,
                    (int) ($item['storage_location_id'] ?? 0),
                    strtoupper(trim((string) $item['batch_number'])),
                    (string) $item['expiry_date'],
                    number_format((float) $item['unit_content'], 2, '.', ''),
                ]);
            })
            ->map(function (Collection $groupedItems): array {
                $firstItem = $groupedItems->first();
                $quantity = round((float) $groupedItems->sum('quantity'), 2);
                $grossSubtotal = round((float) $groupedItems->sum('gross_subtotal'), 2);
                $discountAmount = round((float) $groupedItems->sum('discount_amount'), 0);
                $lineTotal = round((float) $groupedItems->sum('line_total'), 2);
                $stockQuantity = round((float) $groupedItems->sum('stock_quantity'), 2);
                $discountPercentage = $grossSubtotal > 0
                    ? round(($discountAmount / $grossSubtotal) * 100, 2)
                    : 0;
                $unitPrice = $quantity > 0
                    ? round($grossSubtotal / $quantity, 2)
                    : 0;

                return [
                    ...$firstItem,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percentage' => $discountPercentage,
                    'discount_amount' => $discountAmount,
                    'gross_subtotal' => $grossSubtotal,
                    'line_total' => $lineTotal,
                    'stock_quantity' => $stockQuantity,
                    'update_master_purchase_price' => $groupedItems->contains(
                        fn (array $groupedItem): bool => (bool) ($groupedItem['update_master_purchase_price'] ?? false)
                    ),
                ];
            })
            ->values();

        $subtotalAfterDiscount = round((float) $normalizedItems->sum('line_total'), 2);
        $invoiceTaxAmount = round($subtotalAfterDiscount * $taxPercentage / 100, 2);
        $remainingTax = $invoiceTaxAmount;
        $lastIndex = max($normalizedItems->count() - 1, 0);

        return $normalizedItems
            ->map(function (array $item, int $index) use ($subtotalAfterDiscount, $invoiceTaxAmount, $lastIndex, &$remainingTax): array {
                $itemTaxAmount = 0.0;

                if ($invoiceTaxAmount > 0 && $subtotalAfterDiscount > 0) {
                    if ($index === $lastIndex) {
                        $itemTaxAmount = $remainingTax;
                    } else {
                        $itemTaxAmount = round($invoiceTaxAmount * ($item['line_total'] / $subtotalAfterDiscount), 2);
                        $itemTaxAmount = min($itemTaxAmount, $remainingTax);
                    }
                }

                $itemTaxAmount = round(max($itemTaxAmount, 0), 2);
                $remainingTax = round($remainingTax - $itemTaxAmount, 2);
                $landedTotal = round($item['line_total'] + $itemTaxAmount, 2);
                $netUnitCost = $item['stock_quantity'] > 0 ? round($item['line_total'] / $item['stock_quantity'], 2) : 0;
                $landedUnitCost = $item['stock_quantity'] > 0 ? round($landedTotal / $item['stock_quantity'], 2) : 0;

                return [
                    ...$item,
                    'tax_amount' => $itemTaxAmount,
                    'landed_total' => $landedTotal,
                    'net_unit_cost' => $netUnitCost,
                    'landed_unit_cost' => $landedUnitCost,
                ];
            })
            ->all();
    }
}
