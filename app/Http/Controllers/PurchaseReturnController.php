<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseReturnRequest;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\PurchaseReturnReplacementItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use Carbon\Carbon;
use RuntimeException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseReturnController extends Controller
{
    /**
     * Display the purchase return page.
     */
    public function index(Request $request): View
    {
        $moduleConfig = $this->moduleConfig();
        $selectedInvoiceId = $this->selectedInvoiceId($request);
        $selectedInvoice = $selectedInvoiceId !== null
            ? $this->selectedInvoiceQuery()->find($selectedInvoiceId)
            : null;

        return view('purchase-returns.index', [
            ...$this->pageData($moduleConfig['form_route'], $moduleConfig['page_label']),
            'moduleConfig' => $moduleConfig,
            'invoiceOptions' => $this->invoiceOptions($selectedInvoiceId),
            'selectedInvoice' => $selectedInvoice,
            'selectedInvoiceId' => $selectedInvoiceId,
            'initialForm' => [
                'return_number' => $this->previewReturnNumber(),
                'return_date' => (string) $request->session()->getOldInput('return_date', now()->format('Y-m-d')),
                'tax_percentage' => (string) $request->session()->getOldInput('tax_percentage', $selectedInvoice?->tax_percentage ?? 0),
                'rows' => $this->initialRows($request, $selectedInvoice),
            ],
            'showFormSection' => true,
            'showHistorySection' => false,
        ]);
    }

    /**
     * Display the purchase return history page.
     */
    public function history(Request $request): View
    {
        $moduleConfig = $this->moduleConfig();
        $selectedInvoiceId = $this->selectedInvoiceId($request);
        $historySearch = trim((string) $request->query('history_search', ''));
        $returns = $this->returnHistory($historySearch, $selectedInvoiceId);

        return view('purchase-returns.index', [
            ...$this->pageData($moduleConfig['history_route'], $moduleConfig['history_page_label']),
            'moduleConfig' => $moduleConfig,
            'invoiceOptions' => collect(),
            'selectedInvoice' => null,
            'selectedInvoiceId' => $selectedInvoiceId,
            'historySearch' => $historySearch,
            'returns' => $returns,
            'detailPayloads' => $this->detailPayloads($returns),
            'showFormSection' => false,
            'showHistorySection' => true,
        ]);
    }

    /**
     * Store a newly created purchase return.
     */
    public function store(PurchaseReturnRequest $request): RedirectResponse
    {
        $moduleConfig = $this->moduleConfig();
        $validated = $request->validated();
        $purchaseInvoice = PurchaseInvoice::query()
            ->with('supplier:id,name')
            ->findOrFail((int) $validated['purchase_invoice_id']);
        $returnDate = Carbon::parse($validated['return_date']);
        $normalizedItems = $this->normalizeReturnItems($validated['items'], $purchaseInvoice->id);
        $purchaseReturn = DB::transaction(function () use ($request, $purchaseInvoice, $returnDate, $normalizedItems) {
            $subtotal = round(collect($normalizedItems)->sum('line_total'), 2);
            $taxAmount = round(collect($normalizedItems)->sum('tax_amount'), 2);
            $totalAmount = round($subtotal + $taxAmount, 2);

            $purchaseReturn = PurchaseReturn::query()->create([
                'return_number' => $this->nextReturnNumber(),
                'purchase_invoice_id' => $purchaseInvoice->id,
                'supplier_id' => $purchaseInvoice->supplier_id,
                'return_date' => $returnDate->toDateString(),
                'status' => 'posted',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            foreach ($normalizedItems as $item) {
                $returnItem = $purchaseReturn->items()->create([
                    'purchase_invoice_item_id' => $item['purchase_invoice_item']->id,
                    'medicine_id' => $item['medicine']->id,
                    'batch_number' => $item['stock_batch']->batch_number,
                    'expiry_date' => $item['stock_batch']->expiry_date,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'reason' => $item['reason'],
                ]);

                $newQuantityOut = round((float) $item['stock_batch']->quantity_out + $item['quantity'], 2);
                $newBalance = round((float) $item['stock_batch']->quantity_balance - $item['quantity'], 2);

                $item['stock_batch']->update([
                    'quantity_out' => $newQuantityOut,
                    'quantity_balance' => $newBalance,
                    'status' => $newBalance > 0 ? $item['stock_batch']->status : 'returned',
                    'notes' => trim((string) $item['stock_batch']->notes.' | Retur pembelian '.$purchaseReturn->return_number),
                ]);

                StockMovement::query()->create([
                    'movement_date' => now(),
                    'movement_type' => 'purchase_return',
                    'reference_table' => 'purchase_return_items',
                    'reference_id' => $returnItem->id,
                    'medicine_id' => $item['medicine']->id,
                    'stock_batch_id' => $item['stock_batch']->id,
                    'storage_location_id' => $item['stock_batch']->storage_location_id,
                    'quantity_in' => 0,
                    'quantity_out' => $item['quantity'],
                    'balance_after' => $newBalance,
                    'unit_cost' => $item['stock_unit_cost'],
                    'notes' => 'Retur pembelian '.$purchaseReturn->return_number,
                    'created_by' => $request->user()?->id,
                ]);
            }

            return $purchaseReturn;
        });

        return redirect()
            ->route($moduleConfig['form_route'], ['purchase_invoice_id' => $purchaseInvoice->id])
            ->with('toast', [
                'type' => 'success',
                'message' => str_replace(':number', $purchaseReturn->return_number, $moduleConfig['store_success_message']),
            ]);
    }

    /**
     * Delete a purchase return and restore its stock balance.
     */
    public function destroy(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $moduleConfig = $this->moduleConfig();
        $purchaseReturn->load('items');

        $invoiceId = $purchaseReturn->purchase_invoice_id;
        $returnNumber = $purchaseReturn->return_number;

        try {
            DB::transaction(function () use ($purchaseReturn, $moduleConfig): void {
                $lockedReturn = PurchaseReturn::query()
                    ->with('items')
                    ->lockForUpdate()
                    ->findOrFail($purchaseReturn->id);

                $hasReplacementRealization = PurchaseReturnReplacementItem::query()
                    ->whereIn('purchase_return_item_id', $lockedReturn->items->pluck('id'))
                    ->exists();

                if ($hasReplacementRealization) {
                    throw new RuntimeException($moduleConfig['delete_blocked_message']);
                }

                foreach ($lockedReturn->items as $returnItem) {
                    $stockBatch = StockBatch::query()
                        ->where('purchase_invoice_item_id', $returnItem->purchase_invoice_item_id)
                        ->lockForUpdate()
                        ->first();

                    if ($stockBatch === null) {
                        throw new RuntimeException('Stok batch untuk retur ini tidak ditemukan, jadi retur belum bisa dihapus.');
                    }

                    $newQuantityOut = round((float) $stockBatch->quantity_out - (float) $returnItem->quantity, 2);

                    if ($newQuantityOut < -0.001) {
                        throw new RuntimeException('Saldo mutasi batch sudah tidak sinkron, jadi retur belum bisa dihapus.');
                    }

                    $newBalance = round((float) $stockBatch->quantity_balance + (float) $returnItem->quantity, 2);

                    $stockBatch->update([
                        'quantity_out' => max($newQuantityOut, 0),
                        'quantity_balance' => $newBalance,
                        'status' => $newBalance > 0 ? 'active' : $stockBatch->status,
                        'notes' => $this->removeReturnNote((string) $stockBatch->notes, $lockedReturn->return_number),
                    ]);
                }

                StockMovement::query()
                    ->where('reference_table', 'purchase_return_items')
                    ->whereIn('reference_id', $lockedReturn->items->pluck('id'))
                    ->delete();

                $lockedReturn->items()->delete();
                $lockedReturn->delete();
            });
        } catch (RuntimeException $exception) {
            return redirect()
                ->route($moduleConfig['history_route'], $invoiceId ? ['purchase_invoice_id' => $invoiceId] : [])
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route($moduleConfig['history_route'], $invoiceId ? ['purchase_invoice_id' => $invoiceId] : [])
            ->with('toast', [
                'type' => 'success',
                'message' => str_replace(':number', $returnNumber, $moduleConfig['destroy_success_message']),
            ]);
    }

    /**
     * Build the purchase return history result set.
     */
    private function returnHistory(string $historySearch, ?int $selectedInvoiceId): LengthAwarePaginator
    {
        return PurchaseReturn::query()
            ->with([
                'supplier:id,name',
                'purchaseInvoice:id,invoice_number,tax_percentage',
                'items' => fn ($query) => $query
                    ->with([
                        'medicine:id,code,name,small_unit',
                        'purchaseInvoiceItem:id,quantity,unit_content,line_total,tax_amount',
                    ])
                    ->orderBy('id'),
            ])
            ->when($selectedInvoiceId !== null, fn ($query) => $query->where('purchase_invoice_id', $selectedInvoiceId))
            ->when($historySearch !== '', function ($query) use ($historySearch) {
                $query->where(function ($returnQuery) use ($historySearch) {
                    $returnQuery
                        ->where('return_number', 'like', "%{$historySearch}%")
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'like', "%{$historySearch}%"))
                        ->orWhereHas('purchaseInvoice', fn ($invoiceQuery) => $invoiceQuery->where('invoice_number', 'like', "%{$historySearch}%"))
                        ->orWhereHas('items', function ($itemQuery) use ($historySearch) {
                            $itemQuery
                                ->where('batch_number', 'like', "%{$historySearch}%")
                                ->orWhereHas('medicine', function ($medicineQuery) use ($historySearch) {
                                    $medicineQuery
                                        ->where('code', 'like', "%{$historySearch}%")
                                        ->orWhere('name', 'like', "%{$historySearch}%");
                                });
                        });
                });
            })
            ->latest('return_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * Build the selected invoice query with returnable batches.
     */
    private function selectedInvoiceQuery()
    {
        return PurchaseInvoice::query()
            ->with([
                'supplier:id,name',
                'items' => fn ($query) => $query
                    ->with([
                        'medicine:id,code,name,small_unit,large_unit,principal_id',
                        'medicine.principal:id,name',
                        'stockBatch',
                    ])
                    ->whereHas('stockBatch', fn ($batchQuery) => $batchQuery->where('quantity_balance', '>', 0))
                    ->orderBy('id'),
            ]);
    }

    /**
     * Get invoice options that still have returnable stock.
     */
    private function invoiceOptions(?int $selectedInvoiceId): Collection
    {
        return PurchaseInvoice::query()
            ->select(['id', 'invoice_number', 'supplier_id', 'invoice_date', 'tax_percentage'])
            ->with('supplier:id,name')
            ->where(function ($query) use ($selectedInvoiceId) {
                $query->whereHas('items.stockBatch', fn ($batchQuery) => $batchQuery->where('quantity_balance', '>', 0));

                if ($selectedInvoiceId !== null) {
                    $query->orWhere('id', $selectedInvoiceId);
                }
            })
            ->latest('invoice_date')
            ->latest('id')
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * Determine the currently selected invoice ID.
     */
    private function selectedInvoiceId(Request $request): ?int
    {
        $oldInputId = $request->session()->getOldInput('purchase_invoice_id');
        $queryId = $request->query('purchase_invoice_id');
        $rawValue = $oldInputId !== null ? $oldInputId : $queryId;

        if (! filled($rawValue)) {
            return null;
        }

        return (int) $rawValue;
    }

    /**
     * Build initial returnable rows for the selected invoice.
     *
     * @return array<int, array<string, mixed>>
     */
    private function initialRows(Request $request, ?PurchaseInvoice $purchaseInvoice): array
    {
        if ($purchaseInvoice === null) {
            return [];
        }

        $oldItems = collect($request->session()->getOldInput('items', []))
            ->filter(fn ($item) => is_array($item) && isset($item['purchase_invoice_item_id']))
            ->keyBy(fn (array $item): int => (int) $item['purchase_invoice_item_id']);

        return $purchaseInvoice->items
            ->filter(fn ($item): bool => $item->stockBatch !== null && (float) $item->stockBatch->quantity_balance > 0)
            ->map(function ($item) use ($oldItems): array {
                $oldItem = $oldItems->get($item->id, []);
                $stockBatch = $item->stockBatch;

                return [
                    'key' => 'return-item-'.$item->id,
                    'purchase_invoice_item_id' => $item->id,
                    'medicine_code' => $item->medicine?->code ?: '-',
                    'medicine_name' => $item->medicine?->name ?: '-',
                    'principal_name' => $item->medicine?->principal?->name ?: '-',
                    'small_unit' => $item->medicine?->small_unit ?: 'unit',
                    'batch_number' => $stockBatch?->batch_number ?: ($item->batch_number ?: '-'),
                    'expiry_date' => $stockBatch?->expiry_date?->format('Y-m-d') ?? $item->expiry_date?->format('Y-m-d'),
                    'expiry_label' => $stockBatch?->expiry_date?->translatedFormat('d M Y') ?? $item->expiry_date?->translatedFormat('d M Y') ?? '-',
                    'available_quantity' => (string) round((float) $stockBatch?->quantity_balance, 2),
                    'available_quantity_label' => $this->formatQuantity((float) $stockBatch?->quantity_balance),
                    'unit_price' => (string) $this->purchaseReturnBaseUnitPrice($item, $stockBatch),
                    'unit_price_display' => (string) $this->purchaseReturnDisplayUnitPrice($item, $stockBatch),
                    'tax_unit_amount' => (string) $this->purchaseReturnTaxUnitAmount($item, $stockBatch),
                    'quantity' => (string) ($oldItem['quantity'] ?? ''),
                    'reason' => (string) ($oldItem['reason'] ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Get display unit price for return rows, following batch purchase price.
     */
    private function purchaseReturnDisplayUnitPrice(?PurchaseInvoiceItem $purchaseInvoiceItem, ?StockBatch $stockBatch): float
    {
        return round((float) ($stockBatch?->purchase_price ?? $this->purchaseReturnBaseUnitPrice($purchaseInvoiceItem, $stockBatch)), 2);
    }

    /**
     * Derive the tax amount per small unit for return rows.
     */
    private function purchaseReturnTaxUnitAmount(?PurchaseInvoiceItem $purchaseInvoiceItem, ?StockBatch $stockBatch): float
    {
        $receivedQuantity = max((float) ($stockBatch?->initial_quantity ?? 0), 0);

        if ($purchaseInvoiceItem === null || $receivedQuantity <= 0) {
            return 0;
        }

        return round((float) $purchaseInvoiceItem->tax_amount / $receivedQuantity, 6);
    }

    /**
     * Normalize return items and lock their source batch pricing.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeReturnItems(array $items, int $purchaseInvoiceId): array
    {
        $batches = StockBatch::query()
            ->with([
                'medicine',
                'purchaseInvoiceItem:id,purchase_invoice_id,line_total,tax_amount',
            ])
            ->whereIn('purchase_invoice_item_id', collect($items)->pluck('purchase_invoice_item_id')->map(fn ($id) => (int) $id)->all())
            ->get()
            ->keyBy('purchase_invoice_item_id');

        return collect($items)
            ->values()
            ->map(function (array $item) use ($batches, $purchaseInvoiceId): array {
                $purchaseInvoiceItemId = (int) $item['purchase_invoice_item_id'];
                /** @var StockBatch $stockBatch */
                $stockBatch = $batches->get($purchaseInvoiceItemId);

                if ($stockBatch === null || (int) $stockBatch->purchaseInvoiceItem?->purchase_invoice_id !== $purchaseInvoiceId) {
                    abort(422, 'Batch retur pembelian tidak valid.');
                }

                $quantity = round((float) $item['quantity'], 2);
                $unitPrice = $this->purchaseReturnBaseUnitPrice($stockBatch->purchaseInvoiceItem, $stockBatch);

                if ($quantity > (float) $stockBatch->quantity_balance) {
                    abort(422, 'Qty retur melebihi saldo stok batch.');
                }

                $lineTotal = round($quantity * $unitPrice, 2);
                $taxAmount = $this->purchaseReturnTaxAmount($stockBatch->purchaseInvoiceItem, $stockBatch, $quantity);

                return [
                    'purchase_invoice_item' => $stockBatch->purchaseInvoiceItem,
                    'stock_batch' => $stockBatch,
                    'medicine' => $stockBatch->medicine,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'tax_amount' => $taxAmount,
                    'stock_unit_cost' => round((float) $stockBatch->purchase_price, 2),
                    'reason' => trim((string) ($item['reason'] ?? '')) ?: null,
                ];
            })
            ->all();
    }

    /**
     * Derive the base return unit price from the original invoice item.
     */
    private function purchaseReturnBaseUnitPrice(?PurchaseInvoiceItem $purchaseInvoiceItem, ?StockBatch $stockBatch): float
    {
        $receivedQuantity = max((float) ($stockBatch?->initial_quantity ?? 0), 0);

        if ($purchaseInvoiceItem === null || $receivedQuantity <= 0) {
            return round((float) ($stockBatch?->purchase_price ?? 0), 2);
        }

        return round((float) $purchaseInvoiceItem->line_total / $receivedQuantity, 2);
    }

    /**
     * Derive the return tax amount from the original invoice item allocation.
     */
    private function purchaseReturnTaxAmount(?PurchaseInvoiceItem $purchaseInvoiceItem, ?StockBatch $stockBatch, float $quantity): float
    {
        $receivedQuantity = max((float) ($stockBatch?->initial_quantity ?? 0), 0);

        if ($purchaseInvoiceItem === null || $receivedQuantity <= 0 || $quantity <= 0) {
            return 0;
        }

        $unitTaxAmount = (float) $purchaseInvoiceItem->tax_amount / $receivedQuantity;

        return round($unitTaxAmount * $quantity, 2);
    }

    /**
     * Build detail payloads for the return history.
     *
     * @param  LengthAwarePaginator<int, PurchaseReturn>  $returns
     * @return array<int, array<string, mixed>>
     */
    private function detailPayloads(LengthAwarePaginator $returns): array
    {
        return $returns->getCollection()
            ->mapWithKeys(function (PurchaseReturn $purchaseReturn): array {
                return [
                    $purchaseReturn->id => [
                        'return_number' => $purchaseReturn->return_number,
                        'return_date' => $purchaseReturn->return_date?->translatedFormat('d M Y') ?? '-',
                        'invoice_number' => $purchaseReturn->purchaseInvoice?->invoice_number ?: '-',
                        'supplier' => $purchaseReturn->supplier?->name ?: '-',
                        'subtotal' => $this->formatCurrency((float) $purchaseReturn->subtotal),
                        'tax_percentage' => number_format((float) ($purchaseReturn->purchaseInvoice?->tax_percentage ?? 0), 2, ',', '.'),
                        'tax_amount' => $this->formatCurrency((float) $purchaseReturn->tax_amount),
                        'total_amount' => $this->formatCurrency((float) $purchaseReturn->total_amount),
                        'items' => $purchaseReturn->items->map(function (PurchaseReturnItem $item): array {
                            return [
                                'id' => $item->id,
                                'medicine' => $item->medicine?->name ?: '-',
                                'medicine_code' => $item->medicine?->code ?: '-',
                                'batch_number' => $item->batch_number ?: '-',
                                'expiry_date' => $item->expiry_date?->translatedFormat('d M Y') ?? '-',
                                'quantity' => $this->formatQuantity((float) $item->quantity).' '.($item->medicine?->small_unit ?: 'unit'),
                                'unit_price' => $this->formatCurrency($this->detailDisplayUnitPrice($item)),
                                'line_total' => $this->formatCurrency($this->detailDisplayLineTotal($item)),
                                'reason' => $item->reason ?: '-',
                            ];
                        })->values()->all(),
                    ],
                ];
            })
            ->all();
    }

    /**
     * Build the landed unit cost shown in return detail history.
     */
    private function detailDisplayUnitPrice(PurchaseReturnItem $item): float
    {
        $invoiceItem = $item->purchaseInvoiceItem;

        if ($invoiceItem === null) {
            return round((float) $item->unit_price, 2);
        }

        $stockQuantity = round((float) $invoiceItem->quantity * max((float) $invoiceItem->unit_content, 1), 2);

        if ($stockQuantity <= 0) {
            return round((float) $item->unit_price, 2);
        }

        return round(((float) $invoiceItem->line_total + (float) $invoiceItem->tax_amount) / $stockQuantity, 2);
    }

    /**
     * Build the landed line total shown in return detail history.
     */
    private function detailDisplayLineTotal(PurchaseReturnItem $item): float
    {
        $invoiceItem = $item->purchaseInvoiceItem;

        if ($invoiceItem === null) {
            return round((float) $item->line_total, 2);
        }

        $stockQuantity = round((float) $invoiceItem->quantity * max((float) $invoiceItem->unit_content, 1), 2);

        if ($stockQuantity <= 0) {
            return round((float) $item->line_total, 2);
        }

        $taxPerUnit = (float) $invoiceItem->tax_amount / $stockQuantity;

        return round((float) $item->line_total + ($taxPerUnit * (float) $item->quantity), 2);
    }

    /**
     * Remove the appended return note from a stock batch.
     */
    private function removeReturnNote(string $notes, string $returnNumber): ?string
    {
        $cleaned = str_replace(' | Retur pembelian '.$returnNumber, '', $notes);
        $cleaned = str_replace('Retur pembelian '.$returnNumber.' | ', '', $cleaned);
        $cleaned = trim($cleaned, " |\t\n\r\0\x0B");

        return $cleaned !== '' ? $cleaned : null;
    }

    /**
     * Preview the next return number for the form.
     */
    private function previewReturnNumber(): string
    {
        return $this->nextReturnNumber();
    }

    /**
     * Generate the next purchase return number.
     */
    private function nextReturnNumber(): string
    {
        $latestCode = PurchaseReturn::query()
            ->where('return_number', 'like', 'RTB-%')
            ->orderByDesc('id')
            ->value('return_number');

        if (! is_string($latestCode) || ! preg_match('/(\d+)$/', $latestCode, $matches)) {
            return 'RTB-0001';
        }

        return 'RTB-'.str_pad((string) ((int) $matches[1] + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Build the page metadata for the purchase-return module.
     *
     * @return array<string, mixed>
     */
    private function pageData(string $routeName = 'pembelian.retur-pembelian', ?string $fallbackLabel = null): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $item): bool => $item['label'] === 'Pembelian');

        $siblings = $section['children'] ?? [];
        $page = collect($siblings)->firstWhere('route', $routeName);

        if ($page === null) {
            $page = [
                'label' => $fallbackLabel ?? 'Retur Pembelian',
                'route' => $routeName,
                'path' => '',
            ];
        }

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Pembelian',
            'siblings' => $siblings,
        ];
    }

    /**
     * Resolve the active module configuration for return-like pages.
     *
     * @return array<string, string>
     */
    private function moduleConfig(): array
    {
        if (request()->routeIs('pembelian.tukar-barang*') || request()->routeIs('pembelian.riwayat-tukar-barang*')) {
            return [
                'form_route' => 'pembelian.tukar-barang',
                'history_route' => 'pembelian.riwayat-tukar-barang',
                'store_route' => 'pembelian.tukar-barang.store',
                'destroy_route' => 'pembelian.tukar-barang.destroy',
                'page_label' => 'Tukar Barang',
                'history_page_label' => 'Riwayat Tukar Barang',
                'switch_to_form_label' => 'Form Tukar Barang',
                'switch_to_history_label' => 'Riwayat Tukar Barang',
                'form_title' => 'Form Tukar barang',
                'history_title' => 'Riwayat tukar barang',
                'detail_title' => 'Detail Tukar Barang',
                'entry_lower' => 'tukar barang',
                'history_search_placeholder' => 'Cari tukar barang',
                'submit_label' => 'Simpan tukar barang',
                'delete_title' => 'Hapus tukar barang ini?',
                'store_success_message' => 'Tukar barang :number berhasil disimpan.',
                'destroy_success_message' => 'Tukar barang :number berhasil dihapus dan stok dikembalikan.',
                'delete_blocked_message' => 'Tukar barang ini sudah punya realisasi, jadi tidak bisa dihapus.',
                'show_invoice_tax' => false,
                'show_value_summary' => false,
                'detail_line_total_label' => 'Qty Tukar',
                'document_number_label' => 'Nomor TKG',
                'document_date_label' => 'Tanggal',
                'notes_label' => 'Catatan TKG',
                'form_description' => null,
            ];
        }

        return [
            'form_route' => 'pembelian.retur-pembelian',
            'history_route' => 'pembelian.riwayat-retur-pembelian',
            'store_route' => 'pembelian.retur-pembelian.store',
            'destroy_route' => 'pembelian.retur-pembelian.destroy',
            'page_label' => 'Retur Pembelian',
            'history_page_label' => 'Riwayat Retur Pembelian',
            'switch_to_form_label' => 'Form Retur',
            'switch_to_history_label' => 'Riwayat Retur',
            'form_title' => 'Form retur pembelian',
            'history_title' => 'Riwayat retur pembelian',
            'detail_title' => 'Detail Retur',
            'entry_lower' => 'retur pembelian',
            'history_search_placeholder' => 'Cari retur',
            'submit_label' => 'Simpan retur pembelian',
            'delete_title' => 'Hapus retur pembelian ini?',
            'store_success_message' => 'Retur pembelian :number berhasil disimpan.',
            'destroy_success_message' => 'Retur pembelian :number berhasil dihapus dan stok dikembalikan.',
            'delete_blocked_message' => 'Retur ini sudah punya realisasi pengganti, jadi tidak bisa dihapus.',
            'show_invoice_tax' => true,
            'show_value_summary' => true,
            'detail_line_total_label' => 'Total Retur',
            'document_number_label' => 'No retur',
            'document_date_label' => 'Tanggal faktur',
            'notes_label' => 'Catatan retur',
            'form_description' => 'Retur dicatat dalam satuan kecil, harga otomatis mengikuti harga masuk batch, dan PPN dihitung dari faktur pembelian asal.',
        ];
    }

    /**
     * Format quantity values.
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
}
