<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseExchangeReplacementRequest;
use App\Models\PurchaseExchange;
use App\Models\PurchaseExchangeItem;
use App\Models\PurchaseExchangeReplacement;
use App\Models\PurchaseExchangeReplacementItem;
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

class PurchaseExchangeReplacementController extends Controller
{
    /**
     * Display the purchase exchange replacement page.
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
     * Store a newly created exchange replacement realization.
     */
    public function store(PurchaseExchangeReplacementRequest $request): RedirectResponse
    {
        $moduleConfig = $this->moduleConfig();
        $validated = $request->validated();
        $purchaseReturn = PurchaseExchange::query()
            ->with([
                'supplier:id,name',
                'purchaseInvoice:id,invoice_number,tax_percentage',
            ])
            ->findOrFail((int) $validated['purchase_return_id']);
        $replacementDate = Carbon::parse($validated['replacement_date']);

        $replacement = DB::transaction(function () use ($request, $purchaseReturn, $replacementDate, $validated) {
            $normalizedItems = $this->normalizeReplacementItems($validated['items'], $purchaseReturn->id);

            $replacement = PurchaseExchangeReplacement::query()->create([
                'exchange_replacement_number' => $this->nextReplacementNumber(),
                'purchase_exchange_id' => $purchaseReturn->id,
                'purchase_invoice_id' => $purchaseReturn->purchase_invoice_id,
                'supplier_id' => $purchaseReturn->supplier_id,
                'exchange_replacement_date' => $replacementDate->toDateString(),
                'status' => 'posted',
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            foreach ($normalizedItems as $item) {
                $replacementItem = $replacement->items()->create([
                    'purchase_exchange_item_id' => $item['purchase_return_item']->id,
                    'purchase_invoice_item_id' => $item['purchase_invoice_item']?->id,
                    'medicine_id' => $item['medicine']->id,
                    'batch_number' => $item['stock_batch']->batch_number,
                    'expiry_date' => $item['stock_batch']->expiry_date,
                    'quantity' => $item['quantity'],
                    'notes' => $item['notes'],
                ]);

                $newQuantityIn = round((float) $item['stock_batch']->quantity_in + $item['quantity'], 2);
                $newBalance = round((float) $item['stock_batch']->quantity_balance + $item['quantity'], 2);

                $item['stock_batch']->update([
                    'quantity_in' => $newQuantityIn,
                    'quantity_balance' => $newBalance,
                    'status' => 'active',
                    'notes' => trim((string) $item['stock_batch']->notes.' | Realisasi tukar barang '.$replacement->exchange_replacement_number),
                ]);

                StockMovement::query()->create([
                    'movement_date' => now(),
                    'movement_type' => 'purchase_exchange_replacement',
                    'reference_table' => 'purchase_exchange_replacement_items',
                    'reference_id' => $replacementItem->id,
                    'medicine_id' => $item['medicine']->id,
                    'stock_batch_id' => $item['stock_batch']->id,
                    'storage_location_id' => $item['stock_batch']->storage_location_id,
                    'quantity_in' => $item['quantity'],
                    'quantity_out' => 0,
                    'balance_after' => $newBalance,
                    'unit_cost' => $item['stock_unit_cost'],
                    'notes' => 'Realisasi tukar barang '.$replacement->exchange_replacement_number,
                    'created_by' => $request->user()?->id,
                ]);
            }

            return $replacement;
        });

        return redirect()
            ->route($moduleConfig['form_route'], ['purchase_return_id' => $purchaseReturn->id])
            ->with('toast', [
                'type' => 'success',
                'message' => str_replace(':number', $replacement->exchange_replacement_number, $moduleConfig['store_success_message']),
            ]);
    }

    /**
     * Delete a replacement realization and withdraw its restored stock again.
     */
    public function destroy(PurchaseExchangeReplacement $purchaseReturnReplacement): RedirectResponse
    {
        $moduleConfig = $this->moduleConfig();
        $purchaseReturnId = $purchaseReturnReplacement->purchase_exchange_id;
        $replacementNumber = $purchaseReturnReplacement->exchange_replacement_number;

        try {
            DB::transaction(function () use ($purchaseReturnReplacement): void {
                $lockedReplacement = PurchaseExchangeReplacement::query()
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
                        'notes' => $this->removeReplacementNote((string) $stockBatch->notes, $lockedReplacement->exchange_replacement_number),
                    ]);
                }

                StockMovement::query()
                    ->where('reference_table', 'purchase_exchange_replacement_items')
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
        return PurchaseExchangeReplacement::query()
            ->with([
                'supplier:id,name',
                'purchaseExchange:id,exchange_number,purchase_invoice_id',
                'purchaseExchange.purchaseInvoice:id,invoice_number,tax_percentage',
                'items' => fn ($query) => $query
                    ->with([
                        'medicine:id,code,name,small_unit',
                        'purchaseExchangeItem:id,purchase_invoice_item_id',
                    ])
                    ->orderBy('id'),
            ])
            ->when($selectedReturnId !== null, fn ($query) => $query->where('purchase_exchange_id', $selectedReturnId))
            ->when($historySearch !== '', function ($query) use ($historySearch) {
                $query->where(function ($replacementQuery) use ($historySearch) {
                    $replacementQuery
                        ->where('exchange_replacement_number', 'like', "%{$historySearch}%")
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'like', "%{$historySearch}%"))
                        ->orWhereHas('purchaseExchange', fn ($returnQuery) => $returnQuery->where('exchange_number', 'like', "%{$historySearch}%"))
                        ->orWhereHas('purchaseExchange.purchaseInvoice', fn ($invoiceQuery) => $invoiceQuery->where('invoice_number', 'like', "%{$historySearch}%"))
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
            ->latest('exchange_replacement_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * Build the selected return query with replacementable items.
     */
    private function selectedReturnQuery()
    {
        return PurchaseExchange::query()
            ->with([
                'supplier:id,name',
                'purchaseInvoice:id,invoice_number,invoice_date,tax_percentage',
                'items' => fn ($query) => $query
                    ->with([
                        'medicine:id,code,name,small_unit,large_unit,principal_id',
                        'medicine.principal:id,name',
                        'purchaseInvoiceItem:id,purchase_invoice_id',
                        'purchaseInvoiceItem.stockBatch',
                        'replacementItems:id,purchase_exchange_item_id,quantity',
                    ])
                    ->orderBy('id'),
            ]);
    }

    /**
     * Get return options that still have unreplaced quantity.
     */
    private function returnOptions(?int $selectedReturnId): Collection
    {
        return PurchaseExchange::query()
            ->select(['id', 'exchange_number', 'supplier_id', 'purchase_invoice_id', 'exchange_date'])
            ->with([
                'supplier:id,name',
                'purchaseInvoice:id,invoice_number',
                'items:id,purchase_exchange_id,quantity',
                'items.replacementItems:id,purchase_exchange_item_id,quantity',
            ])
            ->latest('exchange_date')
            ->latest('id')
            ->get()
            ->filter(function (PurchaseExchange $purchaseReturn) use ($selectedReturnId): bool {
                if ($selectedReturnId !== null && $purchaseReturn->id === $selectedReturnId) {
                    return true;
                }

                return $purchaseReturn->items->contains(
                    fn (PurchaseExchangeItem $item): bool => $this->remainingReplacementQuantity($item) > 0
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
    private function initialRows(Request $request, ?PurchaseExchange $purchaseReturn): array
    {
        if ($purchaseReturn === null) {
            return [];
        }

        $oldItems = collect($request->session()->getOldInput('items', []))
            ->filter(fn ($item) => is_array($item) && isset($item['purchase_return_item_id']))
            ->keyBy(fn (array $item): int => (int) $item['purchase_return_item_id']);

        return $purchaseReturn->items
            ->map(function (PurchaseExchangeItem $item) use ($oldItems): ?array {
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
     * @param  LengthAwarePaginator<int, PurchaseExchangeReplacement>  $replacements
     * @return array<int, array<string, mixed>>
     */
    private function detailPayloads(LengthAwarePaginator $replacements): array
    {
        return $replacements->getCollection()
            ->mapWithKeys(function (PurchaseExchangeReplacement $replacement): array {
                return [
                    $replacement->id => [
                        'replacement_number' => $replacement->exchange_replacement_number,
                        'replacement_date' => $replacement->exchange_replacement_date?->translatedFormat('d M Y') ?? '-',
                        'return_number' => $replacement->purchaseExchange?->exchange_number ?: '-',
                        'invoice_number' => $replacement->purchaseExchange?->purchaseInvoice?->invoice_number ?: '-',
                        'supplier' => $replacement->supplier?->name ?: '-',
                        'items' => $replacement->items->map(function (PurchaseExchangeReplacementItem $item): array {
                            return [
                                'id' => $item->id,
                                'medicine' => $item->medicine?->name ?: '-',
                                'medicine_code' => $item->medicine?->code ?: '-',
                                'batch_number' => $item->batch_number ?: '-',
                                'expiry_date' => $item->expiry_date?->translatedFormat('d M Y') ?? '-',
                                'quantity' => $this->formatQuantity((float) $item->quantity).' '.($item->medicine?->small_unit ?: 'unit'),
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
        $returnItems = PurchaseExchangeItem::query()
            ->with([
                'medicine',
                'purchaseInvoiceItem:id,purchase_invoice_id',
                'purchaseInvoiceItem.stockBatch',
                'replacementItems:id,purchase_exchange_item_id,quantity',
            ])
            ->whereIn('id', collect($items)->pluck('purchase_return_item_id')->map(fn ($id) => (int) $id)->all())
            ->get()
            ->keyBy('id');

        return collect($items)
            ->values()
            ->map(function (array $item) use ($returnItems, $purchaseReturnId): array {
                $purchaseReturnItemId = (int) $item['purchase_return_item_id'];
                /** @var PurchaseExchangeItem|null $returnItem */
                $returnItem = $returnItems->get($purchaseReturnItemId);

                if ($returnItem === null || (int) $returnItem->purchase_exchange_id !== $purchaseReturnId) {
                    abort(422, 'Item tukar barang tidak valid.');
                }

                $stockBatch = $returnItem->purchaseInvoiceItem?->stockBatch;

                if ($stockBatch === null) {
                    abort(422, 'Batch stok asal untuk item pengganti ini tidak ditemukan.');
                }

                $quantity = round((float) $item['quantity'], 2);
                $remainingQuantity = $this->remainingReplacementQuantity($returnItem);

                if ($quantity > $remainingQuantity) {
                    abort(422, 'Qty realisasi melebihi sisa tukar barang yang belum direalisasikan.');
                }

                return [
                    'purchase_return_item' => $returnItem,
                    'purchase_invoice_item' => $returnItem->purchaseInvoiceItem,
                    'stock_batch' => $stockBatch,
                    'medicine' => $returnItem->medicine,
                    'quantity' => $quantity,
                    'stock_unit_cost' => round((float) $stockBatch->purchase_price, 2),
                    'notes' => null,
                ];
            })
            ->all();
    }

    /**
     * Resolve the remaining quantity that can still be replaced for a return item.
     */
    private function remainingReplacementQuantity(PurchaseExchangeItem $item): float
    {
        $realizedQuantity = (float) $item->replacementItems->sum(fn ($replacementItem) => (float) $replacementItem->quantity);

        return max(round((float) $item->quantity - $realizedQuantity, 2), 0);
    }

    /**
     * Remove the appended replacement note from a stock batch.
     */
    private function removeReplacementNote(string $notes, string $replacementNumber): ?string
    {
        $cleaned = str_replace(' | Realisasi tukar barang '.$replacementNumber, '', $notes);
        $cleaned = str_replace('Realisasi tukar barang '.$replacementNumber.' | ', '', $cleaned);
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
     * Generate the next purchase exchange replacement number.
     */
    private function nextReplacementNumber(): string
    {
        $latestCode = PurchaseExchangeReplacement::query()
            ->where('exchange_replacement_number', 'like', 'RTK-%')
            ->orderByDesc('id')
            ->value('exchange_replacement_number');

        if (! is_string($latestCode) || ! preg_match('/(\d+)$/', $latestCode, $matches)) {
            return 'RTK-0001';
        }

        return 'RTK-'.str_pad((string) ((int) $matches[1] + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Build the page metadata for the purchase return replacement module.
     *
     * @return array<string, mixed>
     */
    private function pageData(string $routeName = 'pembelian.realisasi-tukar-barang', ?string $fallbackLabel = null): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $item): bool => $item['label'] === 'Pembelian');

        $siblings = $section['children'] ?? [];
        $page = collect($siblings)->firstWhere('route', $routeName);

        if ($page === null) {
            $page = [
                'label' => $fallbackLabel ?? 'Realisasi Tukar Barang',
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
     * Resolve the active module configuration for exchange replacement pages.
     *
     * @return array<string, string>
     */
    private function moduleConfig(): array
    {
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
            'show_invoice_tax' => false,
            'show_value_summary' => false,
            'show_unit_price' => false,
            'show_line_total' => false,
        ];
    }

    /**
     * Format quantity values.
     */
    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 0, ',', '.');
    }

}
