<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseReturnReplacementRequest;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\PurchaseReturnReplacement;
use App\Models\PurchaseReturnReplacementItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseReturnReplacementController extends Controller
{
    /**
     * Display the purchase return replacement page.
     */
    public function index(Request $request): View
    {
        $moduleConfig = $this->moduleConfig();
        $selectedReturnId = $this->selectedReturnId($request);
        $selectedReturn = $selectedReturnId !== null
            ? $this->selectedReturnQuery()->find($selectedReturnId)
            : null;

        return view('purchase-return-replacements.index', [
            ...$this->pageData($moduleConfig['form_route'], $moduleConfig['page_label']),
            'moduleConfig' => $moduleConfig,
            'returnOptions' => $this->returnOptions($selectedReturnId),
            'selectedReturn' => $selectedReturn,
            'selectedReturnId' => $selectedReturnId,
            'initialForm' => [
                'return_number' => $this->previewReplacementNumber(),
                'return_date' => (string) $request->session()->getOldInput('replacement_date', now()->format('Y-m-d')),
                'tax_percentage' => (string) $request->session()->getOldInput('tax_percentage', $selectedReturn?->purchaseInvoice?->tax_percentage ?? 0),
                'rows' => $this->initialRows($request, $selectedReturn),
            ],
            'showFormSection' => true,
            'showHistorySection' => false,
        ]);
    }

    /**
     * Display the replacement realization history page.
     */
    public function history(Request $request): View
    {
        $moduleConfig = $this->moduleConfig();
        $selectedReturnId = $this->selectedReturnId($request);
        $historySearch = trim((string) $request->query('history_search', ''));
        $replacements = $this->replacementHistory($historySearch, $selectedReturnId);

        return view('purchase-return-replacements.index', [
            ...$this->pageData($moduleConfig['history_route'], $moduleConfig['history_page_label']),
            'moduleConfig' => $moduleConfig,
            'returnOptions' => collect(),
            'selectedReturn' => null,
            'selectedReturnId' => $selectedReturnId,
            'historySearch' => $historySearch,
            'replacements' => $replacements,
            'detailPayloads' => $this->detailPayloads($replacements),
            'showFormSection' => false,
            'showHistorySection' => true,
        ]);
    }

    /**
     * Store a newly created replacement realization.
     */
    public function store(PurchaseReturnReplacementRequest $request): RedirectResponse
    {
        $moduleConfig = $this->moduleConfig();
        $validated = $request->validated();
        $purchaseReturn = PurchaseReturn::query()
            ->with([
                'supplier:id,name',
                'purchaseInvoice:id,invoice_number,tax_percentage',
            ])
            ->findOrFail((int) $validated['purchase_return_id']);
        $replacementDate = Carbon::parse($validated['replacement_date']);

        $replacement = DB::transaction(function () use ($request, $purchaseReturn, $replacementDate, $validated) {
            $normalizedItems = $this->normalizeReplacementItems($validated['items'], $purchaseReturn->id);
            $subtotal = round(collect($normalizedItems)->sum('line_total'), 2);
            $taxAmount = round(collect($normalizedItems)->sum('tax_amount'), 2);
            $totalAmount = round($subtotal + $taxAmount, 2);

            $replacement = PurchaseReturnReplacement::query()->create([
                'replacement_number' => $this->nextReplacementNumber(),
                'purchase_return_id' => $purchaseReturn->id,
                'purchase_invoice_id' => $purchaseReturn->purchase_invoice_id,
                'supplier_id' => $purchaseReturn->supplier_id,
                'replacement_date' => $replacementDate->toDateString(),
                'status' => 'posted',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            foreach ($normalizedItems as $item) {
                $replacementItem = $replacement->items()->create([
                    'purchase_return_item_id' => $item['purchase_return_item']->id,
                    'purchase_invoice_item_id' => $item['purchase_invoice_item']?->id,
                    'medicine_id' => $item['medicine']->id,
                    'batch_number' => $item['stock_batch']->batch_number,
                    'expiry_date' => $item['stock_batch']->expiry_date,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'notes' => $item['notes'],
                ]);

                $newQuantityIn = round((float) $item['stock_batch']->quantity_in + $item['quantity'], 2);
                $newBalance = round((float) $item['stock_batch']->quantity_balance + $item['quantity'], 2);

                $item['stock_batch']->update([
                    'quantity_in' => $newQuantityIn,
                    'quantity_balance' => $newBalance,
                    'status' => 'active',
                    'notes' => trim((string) $item['stock_batch']->notes.' | Pengganti retur '.$replacement->replacement_number),
                ]);

                StockMovement::query()->create([
                    'movement_date' => now(),
                    'movement_type' => 'purchase_return_replacement',
                    'reference_table' => 'purchase_return_replacement_items',
                    'reference_id' => $replacementItem->id,
                    'medicine_id' => $item['medicine']->id,
                    'stock_batch_id' => $item['stock_batch']->id,
                    'storage_location_id' => $item['stock_batch']->storage_location_id,
                    'quantity_in' => $item['quantity'],
                    'quantity_out' => 0,
                    'balance_after' => $newBalance,
                    'unit_cost' => $item['stock_unit_cost'],
                    'notes' => 'Realisasi pengganti retur '.$replacement->replacement_number,
                    'created_by' => $request->user()?->id,
                ]);
            }

            return $replacement;
        });

        return redirect()
            ->route($moduleConfig['form_route'], ['purchase_return_id' => $purchaseReturn->id])
            ->with('toast', [
                'type' => 'success',
                'message' => str_replace(':number', $replacement->replacement_number, $moduleConfig['store_success_message']),
            ]);
    }

    /**
     * Delete a replacement realization and withdraw its restored stock again.
     */
    public function destroy(PurchaseReturnReplacement $purchaseReturnReplacement): RedirectResponse
    {
        $moduleConfig = $this->moduleConfig();
        $purchaseReturnId = $purchaseReturnReplacement->purchase_return_id;
        $replacementNumber = $purchaseReturnReplacement->replacement_number;

        try {
            DB::transaction(function () use ($purchaseReturnReplacement): void {
                $lockedReplacement = PurchaseReturnReplacement::query()
                    ->with('items')
                    ->lockForUpdate()
                    ->findOrFail($purchaseReturnReplacement->id);

                foreach ($lockedReplacement->items as $replacementItem) {
                    $stockBatch = StockBatch::query()
                        ->where('purchase_invoice_item_id', $replacementItem->purchase_invoice_item_id)
                        ->lockForUpdate()
                        ->first();

                    if ($stockBatch === null) {
                        throw new RuntimeException('Stok batch asal untuk realisasi ini tidak ditemukan, jadi realisasi belum bisa dihapus.');
                    }

                    $quantity = (float) $replacementItem->quantity;

                    if ((float) $stockBatch->quantity_balance + 0.001 < $quantity) {
                        throw new RuntimeException('Stok batch pengganti sudah terpakai, jadi realisasi ini belum bisa dihapus.');
                    }

                    $newQuantityIn = round((float) $stockBatch->quantity_in - $quantity, 2);
                    $newBalance = round((float) $stockBatch->quantity_balance - $quantity, 2);

                    if ($newQuantityIn < -0.001 || $newBalance < -0.001) {
                        throw new RuntimeException('Saldo stok batch sudah tidak sinkron, jadi realisasi belum bisa dihapus.');
                    }

                    $stockBatch->update([
                        'quantity_in' => max($newQuantityIn, 0),
                        'quantity_balance' => max($newBalance, 0),
                        'status' => $newBalance > 0 ? 'active' : 'returned',
                        'notes' => $this->removeReplacementNote((string) $stockBatch->notes, $lockedReplacement->replacement_number),
                    ]);
                }

                StockMovement::query()
                    ->where('reference_table', 'purchase_return_replacement_items')
                    ->whereIn('reference_id', $lockedReplacement->items->pluck('id'))
                    ->delete();

                $lockedReplacement->items()->delete();
                $lockedReplacement->delete();
            });
        } catch (RuntimeException $exception) {
            return redirect()
                ->route($moduleConfig['history_route'], $purchaseReturnId ? ['purchase_return_id' => $purchaseReturnId] : [])
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route($moduleConfig['history_route'], $purchaseReturnId ? ['purchase_return_id' => $purchaseReturnId] : [])
            ->with('toast', [
                'type' => 'success',
                'message' => str_replace(':number', $replacementNumber, $moduleConfig['destroy_success_message']),
            ]);
    }

    /**
     * Build the replacement realization history result set.
     */
    private function replacementHistory(string $historySearch, ?int $selectedReturnId): LengthAwarePaginator
    {
        return PurchaseReturnReplacement::query()
            ->with([
                'supplier:id,name',
                'purchaseReturn:id,return_number,purchase_invoice_id',
                'purchaseReturn.purchaseInvoice:id,invoice_number,tax_percentage',
                'items' => fn ($query) => $query
                    ->with([
                        'medicine:id,code,name,small_unit',
                        'purchaseReturnItem:id,purchase_invoice_item_id',
                        'purchaseInvoiceItem:id,quantity,unit_content,line_total,tax_amount',
                    ])
                    ->orderBy('id'),
            ])
            ->when($selectedReturnId !== null, fn ($query) => $query->where('purchase_return_id', $selectedReturnId))
            ->when($historySearch !== '', function ($query) use ($historySearch) {
                $query->where(function ($replacementQuery) use ($historySearch) {
                    $replacementQuery
                        ->where('replacement_number', 'like', "%{$historySearch}%")
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'like', "%{$historySearch}%"))
                        ->orWhereHas('purchaseReturn', fn ($returnQuery) => $returnQuery->where('return_number', 'like', "%{$historySearch}%"))
                        ->orWhereHas('purchaseReturn.purchaseInvoice', fn ($invoiceQuery) => $invoiceQuery->where('invoice_number', 'like', "%{$historySearch}%"))
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
            ->latest('replacement_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * Build the selected return query with replacementable items.
     */
    private function selectedReturnQuery()
    {
        return PurchaseReturn::query()
            ->with([
                'supplier:id,name',
                'purchaseInvoice:id,invoice_number,invoice_date,tax_percentage',
                'items' => fn ($query) => $query
                    ->with([
                        'medicine:id,code,name,small_unit,large_unit,principal_id',
                        'medicine.principal:id,name',
                        'purchaseInvoiceItem:id,purchase_invoice_id,quantity,unit_content,line_total,tax_amount',
                        'purchaseInvoiceItem.stockBatch',
                        'replacementItems:id,purchase_return_item_id,quantity',
                    ])
                    ->orderBy('id'),
            ]);
    }

    /**
     * Get return options that still have unreplaced quantity.
     */
    private function returnOptions(?int $selectedReturnId): Collection
    {
        return PurchaseReturn::query()
            ->select(['id', 'return_number', 'supplier_id', 'purchase_invoice_id', 'return_date'])
            ->with([
                'supplier:id,name',
                'purchaseInvoice:id,invoice_number',
                'items:id,purchase_return_id,quantity',
                'items.replacementItems:id,purchase_return_item_id,quantity',
            ])
            ->latest('return_date')
            ->latest('id')
            ->get()
            ->filter(function (PurchaseReturn $purchaseReturn) use ($selectedReturnId): bool {
                if ($selectedReturnId !== null && $purchaseReturn->id === $selectedReturnId) {
                    return true;
                }

                return $purchaseReturn->items->contains(
                    fn (PurchaseReturnItem $item): bool => $this->remainingReplacementQuantity($item) > 0
                );
            })
            ->values();
    }

    /**
     * Determine the currently selected return ID.
     */
    private function selectedReturnId(Request $request): ?int
    {
        $oldInputId = $request->session()->getOldInput('purchase_return_id');
        $queryId = $request->query('purchase_return_id');
        $rawValue = $oldInputId !== null ? $oldInputId : $queryId;

        if (! filled($rawValue)) {
            return null;
        }

        return (int) $rawValue;
    }

    /**
     * Build initial replacementable rows for the selected return.
     *
     * @return array<int, array<string, mixed>>
     */
    private function initialRows(Request $request, ?PurchaseReturn $purchaseReturn): array
    {
        if ($purchaseReturn === null) {
            return [];
        }

        $oldItems = collect($request->session()->getOldInput('items', []))
            ->filter(fn ($item) => is_array($item) && isset($item['purchase_return_item_id']))
            ->keyBy(fn (array $item): int => (int) $item['purchase_return_item_id']);

        return $purchaseReturn->items
            ->map(function (PurchaseReturnItem $item) use ($oldItems): ?array {
                $stockBatch = $item->purchaseInvoiceItem?->stockBatch;

                if ($stockBatch === null) {
                    return null;
                }

                $remainingQuantity = $this->remainingReplacementQuantity($item);

                if ($remainingQuantity <= 0) {
                    return null;
                }

                $oldItem = $oldItems->get($item->id, []);

                return [
                    'key' => 'replacement-item-'.$item->id,
                    'purchase_return_item_id' => $item->id,
                    'medicine_code' => $item->medicine?->code ?: '-',
                    'medicine_name' => $item->medicine?->name ?: '-',
                    'principal_name' => $item->medicine?->principal?->name ?: '-',
                    'small_unit' => $item->medicine?->small_unit ?: 'unit',
                    'batch_number' => $item->batch_number ?: ($stockBatch->batch_number ?: '-'),
                    'expiry_date' => $item->expiry_date?->format('Y-m-d') ?? $stockBatch->expiry_date?->format('Y-m-d'),
                    'expiry_label' => $item->expiry_date?->translatedFormat('d M Y') ?? $stockBatch->expiry_date?->translatedFormat('d M Y') ?? '-',
                    'returned_quantity' => (string) round((float) $item->quantity, 2),
                    'returned_quantity_label' => $this->formatQuantity((float) $item->quantity),
                    'available_quantity' => (string) $remainingQuantity,
                    'available_quantity_label' => $this->formatQuantity($remainingQuantity),
                    'unit_price' => (string) $this->replacementBaseUnitPrice($item->purchaseInvoiceItem, $stockBatch),
                    'unit_price_display' => (string) $this->replacementDisplayUnitPrice($item->purchaseInvoiceItem, $stockBatch),
                    'tax_unit_amount' => (string) $this->replacementTaxUnitAmount($item->purchaseInvoiceItem, $stockBatch),
                    'quantity' => (string) ($oldItem['quantity'] ?? ''),
                    'reason' => '',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Build detail payloads for replacement history.
     *
     * @param  LengthAwarePaginator<int, PurchaseReturnReplacement>  $replacements
     * @return array<int, array<string, mixed>>
     */
    private function detailPayloads(LengthAwarePaginator $replacements): array
    {
        return $replacements->getCollection()
            ->mapWithKeys(function (PurchaseReturnReplacement $replacement): array {
                return [
                    $replacement->id => [
                        'replacement_number' => $replacement->replacement_number,
                        'replacement_date' => $replacement->replacement_date?->translatedFormat('d M Y') ?? '-',
                        'return_number' => $replacement->purchaseReturn?->return_number ?: '-',
                        'invoice_number' => $replacement->purchaseReturn?->purchaseInvoice?->invoice_number ?: '-',
                        'supplier' => $replacement->supplier?->name ?: '-',
                        'subtotal' => $this->formatCurrency((float) $replacement->subtotal),
                        'tax_percentage' => number_format((float) ($replacement->purchaseReturn?->purchaseInvoice?->tax_percentage ?? 0), 2, ',', '.'),
                        'tax_amount' => $this->formatCurrency((float) $replacement->tax_amount),
                        'total_amount' => $this->formatCurrency((float) $replacement->total_amount),
                        'items' => $replacement->items->map(function (PurchaseReturnReplacementItem $item): array {
                            return [
                                'id' => $item->id,
                                'medicine' => $item->medicine?->name ?: '-',
                                'medicine_code' => $item->medicine?->code ?: '-',
                                'batch_number' => $item->batch_number ?: '-',
                                'expiry_date' => $item->expiry_date?->translatedFormat('d M Y') ?? '-',
                                'quantity' => $this->formatQuantity((float) $item->quantity).' '.($item->medicine?->small_unit ?: 'unit'),
                                'unit_price' => $this->formatCurrency($this->detailDisplayUnitPrice($item)),
                                'line_total' => $this->formatCurrency($this->detailDisplayLineTotal($item)),
                            ];
                        })->values()->all(),
                    ],
                ];
            })
            ->all();
    }

    /**
     * Normalize replacement items and lock their batch pricing.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeReplacementItems(array $items, int $purchaseReturnId): array
    {
        $returnItems = PurchaseReturnItem::query()
            ->with([
                'medicine',
                'purchaseInvoiceItem:id,purchase_invoice_id,quantity,unit_content,line_total,tax_amount',
                'purchaseInvoiceItem.stockBatch',
                'replacementItems:id,purchase_return_item_id,quantity',
            ])
            ->whereIn('id', collect($items)->pluck('purchase_return_item_id')->map(fn ($id) => (int) $id)->all())
            ->get()
            ->keyBy('id');

        return collect($items)
            ->values()
            ->map(function (array $item) use ($returnItems, $purchaseReturnId): array {
                $purchaseReturnItemId = (int) $item['purchase_return_item_id'];
                /** @var PurchaseReturnItem|null $returnItem */
                $returnItem = $returnItems->get($purchaseReturnItemId);

                if ($returnItem === null || (int) $returnItem->purchase_return_id !== $purchaseReturnId) {
                    abort(422, 'Item pengganti retur tidak valid.');
                }

                $stockBatch = $returnItem->purchaseInvoiceItem?->stockBatch;

                if ($stockBatch === null) {
                    abort(422, 'Batch stok asal untuk item pengganti ini tidak ditemukan.');
                }

                $quantity = round((float) $item['quantity'], 2);
                $remainingQuantity = $this->remainingReplacementQuantity($returnItem);

                if ($quantity > $remainingQuantity) {
                    abort(422, 'Qty pengganti melebihi sisa retur yang belum direalisasikan.');
                }

                $unitPrice = $this->replacementBaseUnitPrice($returnItem->purchaseInvoiceItem, $stockBatch);
                $lineTotal = round($quantity * $unitPrice, 2);
                $taxAmount = $this->replacementTaxAmount($returnItem->purchaseInvoiceItem, $stockBatch, $quantity);

                return [
                    'purchase_return_item' => $returnItem,
                    'purchase_invoice_item' => $returnItem->purchaseInvoiceItem,
                    'stock_batch' => $stockBatch,
                    'medicine' => $returnItem->medicine,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'tax_amount' => $taxAmount,
                    'stock_unit_cost' => round((float) $stockBatch->purchase_price, 2),
                    'notes' => null,
                ];
            })
            ->all();
    }

    /**
     * Resolve the remaining quantity that can still be replaced for a return item.
     */
    private function remainingReplacementQuantity(PurchaseReturnItem $item): float
    {
        $realizedQuantity = (float) $item->replacementItems->sum(fn ($replacementItem) => (float) $replacementItem->quantity);

        return max(round((float) $item->quantity - $realizedQuantity, 2), 0);
    }

    /**
     * Get display unit price for replacement rows, following batch purchase price.
     */
    private function replacementDisplayUnitPrice(?PurchaseInvoiceItem $purchaseInvoiceItem, ?StockBatch $stockBatch): float
    {
        return round((float) ($stockBatch?->purchase_price ?? $this->replacementBaseUnitPrice($purchaseInvoiceItem, $stockBatch)), 2);
    }

    /**
     * Derive the tax amount per small unit for replacement rows.
     */
    private function replacementTaxUnitAmount(?PurchaseInvoiceItem $purchaseInvoiceItem, ?StockBatch $stockBatch): float
    {
        $receivedQuantity = max((float) ($stockBatch?->initial_quantity ?? 0), 0);

        if ($purchaseInvoiceItem === null || $receivedQuantity <= 0) {
            return 0;
        }

        return round((float) $purchaseInvoiceItem->tax_amount / $receivedQuantity, 6);
    }

    /**
     * Derive the base replacement unit price from the original invoice item.
     */
    private function replacementBaseUnitPrice(?PurchaseInvoiceItem $purchaseInvoiceItem, ?StockBatch $stockBatch): float
    {
        $receivedQuantity = max((float) ($stockBatch?->initial_quantity ?? 0), 0);

        if ($purchaseInvoiceItem === null || $receivedQuantity <= 0) {
            return round((float) ($stockBatch?->purchase_price ?? 0), 2);
        }

        return round((float) $purchaseInvoiceItem->line_total / $receivedQuantity, 2);
    }

    /**
     * Derive the replacement tax amount from the original invoice item allocation.
     */
    private function replacementTaxAmount(?PurchaseInvoiceItem $purchaseInvoiceItem, ?StockBatch $stockBatch, float $quantity): float
    {
        $receivedQuantity = max((float) ($stockBatch?->initial_quantity ?? 0), 0);

        if ($purchaseInvoiceItem === null || $receivedQuantity <= 0 || $quantity <= 0) {
            return 0;
        }

        $unitTaxAmount = (float) $purchaseInvoiceItem->tax_amount / $receivedQuantity;

        return round($unitTaxAmount * $quantity, 2);
    }

    /**
     * Build the landed unit cost shown in replacement detail history.
     */
    private function detailDisplayUnitPrice(PurchaseReturnReplacementItem $item): float
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
     * Build the landed line total shown in replacement detail history.
     */
    private function detailDisplayLineTotal(PurchaseReturnReplacementItem $item): float
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
     * Remove the appended replacement note from a stock batch.
     */
    private function removeReplacementNote(string $notes, string $replacementNumber): ?string
    {
        $cleaned = str_replace(' | Pengganti retur '.$replacementNumber, '', $notes);
        $cleaned = str_replace('Pengganti retur '.$replacementNumber.' | ', '', $cleaned);
        $cleaned = trim($cleaned, " |\t\n\r\0\x0B");

        return $cleaned !== '' ? $cleaned : null;
    }

    /**
     * Preview the next replacement number for the form.
     */
    private function previewReplacementNumber(): string
    {
        return $this->nextReplacementNumber();
    }

    /**
     * Generate the next purchase return replacement number.
     */
    private function nextReplacementNumber(): string
    {
        $latestCode = PurchaseReturnReplacement::query()
            ->where('replacement_number', 'like', 'RPR-%')
            ->orderByDesc('id')
            ->value('replacement_number');

        if (! is_string($latestCode) || ! preg_match('/(\d+)$/', $latestCode, $matches)) {
            return 'RPR-0001';
        }

        return 'RPR-'.str_pad((string) ((int) $matches[1] + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Build the page metadata for the purchase return replacement module.
     *
     * @return array<string, mixed>
     */
    private function pageData(string $routeName = 'pembelian.realisasi-pengganti-retur', ?string $fallbackLabel = null): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $item): bool => $item['label'] === 'Pembelian');

        $siblings = $section['children'] ?? [];
        $page = collect($siblings)->firstWhere('route', $routeName);

        if ($page === null) {
            $page = [
                'label' => $fallbackLabel ?? 'Realisasi Pengganti Retur',
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
     * Resolve the active module configuration for replacement-like pages.
     *
     * @return array<string, string>
     */
    private function moduleConfig(): array
    {
        if (request()->routeIs('pembelian.realisasi-tukar-barang*') || request()->routeIs('pembelian.riwayat-realisasi-tukar-barang*')) {
            return [
                'form_route' => 'pembelian.realisasi-tukar-barang',
                'history_route' => 'pembelian.riwayat-realisasi-tukar-barang',
                'store_route' => 'pembelian.realisasi-tukar-barang.store',
                'destroy_route' => 'pembelian.realisasi-tukar-barang.destroy',
                'page_label' => 'Realisasi Tukar Barang',
                'history_page_label' => 'Riwayat Realisasi Tukar Barang',
                'switch_to_form_label' => 'Form Realisasi Tukar Barang',
                'switch_to_history_label' => 'Riwayat Realisasi Tukar Barang',
                'form_title' => 'Form realisasi tukar barang',
                'history_title' => 'Riwayat realisasi tukar barang',
                'detail_title' => 'Detail Realisasi Tukar Barang',
                'entry_lower' => 'realisasi tukar barang',
                'submit_label' => 'Simpan realisasi tukar barang',
                'delete_title' => 'Hapus realisasi tukar barang ini?',
                'store_success_message' => 'Realisasi tukar barang :number berhasil disimpan.',
                'destroy_success_message' => 'Realisasi tukar barang :number berhasil dihapus dan stok ditarik kembali.',
            ];
        }

        return [
            'form_route' => 'pembelian.realisasi-pengganti-retur',
            'history_route' => 'pembelian.riwayat-realisasi-pengganti-retur',
            'store_route' => 'pembelian.realisasi-pengganti-retur.store',
            'destroy_route' => 'pembelian.realisasi-pengganti-retur.destroy',
            'page_label' => 'Realisasi Pengganti Retur',
            'history_page_label' => 'Riwayat Realisasi Pengganti Retur',
            'switch_to_form_label' => 'Form Realisasi',
            'switch_to_history_label' => 'Riwayat Realisasi',
            'form_title' => 'Form realisasi pengganti retur',
            'history_title' => 'Riwayat realisasi pengganti retur',
            'detail_title' => 'Detail Realisasi',
            'entry_lower' => 'realisasi pengganti retur',
            'submit_label' => 'Simpan realisasi pengganti',
            'delete_title' => 'Hapus realisasi pengganti retur ini?',
            'store_success_message' => 'Realisasi pengganti retur :number berhasil disimpan.',
            'destroy_success_message' => 'Realisasi pengganti retur :number berhasil dihapus dan stok ditarik kembali.',
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
