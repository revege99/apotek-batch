<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseExchangeRequest;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseExchange;
use App\Models\PurchaseExchangeItem;
use App\Models\PurchaseExchangeReplacementItem;
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

class PurchaseExchangeController extends Controller
{
    /**
     * Display the purchase exchange page.
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
     * Display the purchase exchange history page.
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
     * Store a newly created purchase exchange.
     */
    public function store(PurchaseExchangeRequest $request): RedirectResponse
    {
        $moduleConfig = $this->moduleConfig();
        $validated = $request->validated();
        $purchaseInvoice = PurchaseInvoice::query()
            ->with('supplier:id,name')
            ->findOrFail((int) $validated['purchase_invoice_id']);
        $returnDate = Carbon::parse($validated['return_date']);
        $normalizedItems = $this->normalizeReturnItems($validated['items'], $purchaseInvoice->id);
        $purchaseExchange = DB::transaction(function () use ($request, $purchaseInvoice, $returnDate, $normalizedItems) {
            $purchaseExchange = PurchaseExchange::query()->create([
                'exchange_number' => $this->nextExchangeNumber(),
                'purchase_invoice_id' => $purchaseInvoice->id,
                'supplier_id' => $purchaseInvoice->supplier_id,
                'exchange_date' => $returnDate->toDateString(),
                'status' => 'posted',
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            foreach ($normalizedItems as $item) {
                $returnItem = $purchaseExchange->items()->create([
                    'purchase_invoice_item_id' => $item['purchase_invoice_item']->id,
                    'medicine_id' => $item['medicine']->id,
                    'batch_number' => $item['stock_batch']->batch_number,
                    'expiry_date' => $item['stock_batch']->expiry_date,
                    'quantity' => $item['quantity'],
                    'reason' => $item['reason'],
                ]);

                $newQuantityOut = round((float) $item['stock_batch']->quantity_out + $item['quantity'], 2);
                $newBalance = round((float) $item['stock_batch']->quantity_balance - $item['quantity'], 2);

                $item['stock_batch']->update([
                    'quantity_out' => $newQuantityOut,
                    'quantity_balance' => $newBalance,
                    'status' => $newBalance > 0 ? $item['stock_batch']->status : 'returned',
                    'notes' => trim((string) $item['stock_batch']->notes.' | Tukar barang '.$purchaseExchange->exchange_number),
                ]);

                StockMovement::query()->create([
                    'movement_date' => now(),
                    'movement_type' => 'purchase_exchange',
                    'reference_table' => 'purchase_exchange_items',
                    'reference_id' => $returnItem->id,
                    'medicine_id' => $item['medicine']->id,
                    'stock_batch_id' => $item['stock_batch']->id,
                    'storage_location_id' => $item['stock_batch']->storage_location_id,
                    'quantity_in' => 0,
                    'quantity_out' => $item['quantity'],
                    'balance_after' => $newBalance,
                    'unit_cost' => $item['stock_unit_cost'],
                    'notes' => 'Tukar barang '.$purchaseExchange->exchange_number,
                    'created_by' => $request->user()?->id,
                ]);
            }

            return $purchaseExchange;
        });

        return redirect()
            ->route($moduleConfig['form_route'], ['purchase_invoice_id' => $purchaseInvoice->id])
            ->with('toast', [
                'type' => 'success',
                'message' => str_replace(':number', $purchaseExchange->exchange_number, $moduleConfig['store_success_message']),
            ]);
    }

    /**
     * Delete a purchase exchange and restore its stock balance.
     */
    public function destroy(PurchaseExchange $purchaseExchange): RedirectResponse
    {
        $moduleConfig = $this->moduleConfig();
        $purchaseExchange->load('items');

        $invoiceId = $purchaseExchange->purchase_invoice_id;
        $returnNumber = $purchaseExchange->exchange_number;

        try {
            DB::transaction(function () use ($purchaseExchange, $moduleConfig): void {
                $lockedReturn = PurchaseExchange::query()
                    ->with('items')
                    ->lockForUpdate()
                    ->findOrFail($purchaseExchange->id);

                $hasReplacementRealization = PurchaseExchangeReplacementItem::query()
                    ->whereIn('purchase_exchange_item_id', $lockedReturn->items->pluck('id'))
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
                        'notes' => $this->removeReturnNote((string) $stockBatch->notes, $lockedReturn->exchange_number),
                    ]);
                }

                StockMovement::query()
                    ->where('reference_table', 'purchase_exchange_items')
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
     * Build the purchase exchange history result set.
     */
    private function returnHistory(string $historySearch, ?int $selectedInvoiceId): LengthAwarePaginator
    {
        return PurchaseExchange::query()
            ->with([
                'supplier:id,name',
                'purchaseInvoice:id,invoice_number,tax_percentage',
                'items' => fn ($query) => $query
                    ->with([
                        'medicine:id,code,name,small_unit',
                    ])
                    ->orderBy('id'),
            ])
            ->when($selectedInvoiceId !== null, fn ($query) => $query->where('purchase_invoice_id', $selectedInvoiceId))
            ->when($historySearch !== '', function ($query) use ($historySearch) {
                $query->where(function ($returnQuery) use ($historySearch) {
                    $returnQuery
                        ->where('exchange_number', 'like', "%{$historySearch}%")
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
            ->latest('exchange_date')
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
                    'quantity' => (string) ($oldItem['quantity'] ?? ''),
                    'reason' => (string) ($oldItem['reason'] ?? ''),
                ];
            })
            ->values()
            ->all();
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
                'purchaseInvoiceItem:id,purchase_invoice_id',
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
                    abort(422, 'Batch tukar barang tidak valid.');
                }

                $quantity = round((float) $item['quantity'], 2);

                if ($quantity > (float) $stockBatch->quantity_balance) {
                    abort(422, 'Qty tukar barang melebihi saldo stok batch.');
                }

                return [
                    'purchase_invoice_item' => $stockBatch->purchaseInvoiceItem,
                    'stock_batch' => $stockBatch,
                    'medicine' => $stockBatch->medicine,
                    'quantity' => $quantity,
                    'stock_unit_cost' => round((float) $stockBatch->purchase_price, 2),
                    'reason' => trim((string) ($item['reason'] ?? '')) ?: null,
                ];
            })
            ->all();
    }

    /**
     * Build detail payloads for the return history.
     *
     * @param  LengthAwarePaginator<int, PurchaseExchange>  $returns
     * @return array<int, array<string, mixed>>
     */
    private function detailPayloads(LengthAwarePaginator $returns): array
    {
        return $returns->getCollection()
            ->mapWithKeys(function (PurchaseExchange $purchaseReturn): array {
                return [
                    $purchaseReturn->id => [
                        'return_number' => $purchaseReturn->exchange_number,
                        'return_date' => $purchaseReturn->exchange_date?->translatedFormat('d M Y') ?? '-',
                        'invoice_number' => $purchaseReturn->purchaseInvoice?->invoice_number ?: '-',
                        'supplier' => $purchaseReturn->supplier?->name ?: '-',
                        'items' => $purchaseReturn->items->map(function (PurchaseExchangeItem $item): array {
                            return [
                                'id' => $item->id,
                                'medicine' => $item->medicine?->name ?: '-',
                                'medicine_code' => $item->medicine?->code ?: '-',
                                'batch_number' => $item->batch_number ?: '-',
                                'expiry_date' => $item->expiry_date?->translatedFormat('d M Y') ?? '-',
                                'quantity' => $this->formatQuantity((float) $item->quantity).' '.($item->medicine?->small_unit ?: 'unit'),
                                'reason' => $item->reason ?: '-',
                            ];
                        })->values()->all(),
                    ],
                ];
            })
            ->all();
    }

    /**
     * Remove the appended return note from a stock batch.
     */
    private function removeReturnNote(string $notes, string $returnNumber): ?string
    {
        $cleaned = str_replace(' | Tukar barang '.$returnNumber, '', $notes);
        $cleaned = str_replace('Tukar barang '.$returnNumber.' | ', '', $cleaned);
        $cleaned = trim($cleaned, " |\t\n\r\0\x0B");

        return $cleaned !== '' ? $cleaned : null;
    }

    /**
     * Preview the next return number for the form.
     */
    private function previewReturnNumber(): string
    {
        return $this->nextExchangeNumber();
    }

    /**
     * Generate the next purchase exchange number.
     */
    private function nextExchangeNumber(): string
    {
        $latestCode = PurchaseExchange::query()
            ->where('exchange_number', 'like', 'TKB-%')
            ->orderByDesc('id')
            ->value('exchange_number');

        if (! is_string($latestCode) || ! preg_match('/(\d+)$/', $latestCode, $matches)) {
            return 'TKB-0001';
        }

        return 'TKB-'.str_pad((string) ((int) $matches[1] + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Build the page metadata for the purchase-return module.
     *
     * @return array<string, mixed>
     */
    private function pageData(string $routeName = 'pembelian.tukar-barang', ?string $fallbackLabel = null): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $item): bool => $item['label'] === 'Pembelian');

        $siblings = $section['children'] ?? [];
        $page = collect($siblings)->firstWhere('route', $routeName);

        if ($page === null) {
            $page = [
                'label' => $fallbackLabel ?? 'Tukar Barang',
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
     * Resolve the active module configuration for purchase exchange pages.
     *
     * @return array<string, string>
     */
    private function moduleConfig(): array
    {
        return [
            'form_route' => 'pembelian.tukar-barang',
            'history_route' => 'pembelian.riwayat-tukar-barang',
            'store_route' => 'pembelian.tukar-barang.store',
            'destroy_route' => 'pembelian.tukar-barang.destroy',
            'page_label' => 'Tukar Barang',
            'history_page_label' => 'Riwayat Tukar Barang',
            'switch_to_form_label' => 'Form Tukar Barang',
            'switch_to_history_label' => 'Riwayat Tukar Barang',
            'form_title' => 'Form tukar barang',
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
            'show_unit_price' => false,
            'show_line_total' => false,
            'document_number_label' => 'Nomor TKG',
            'document_date_label' => 'Tanggal',
            'notes_label' => 'Catatan TKG',
            'form_description' => null,
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
