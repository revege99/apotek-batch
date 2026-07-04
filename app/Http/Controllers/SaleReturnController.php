<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleReturnRequest;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
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

class SaleReturnController extends Controller
{
    /**
     * Display the sale return page.
     */
    public function index(Request $request): View
    {
        $selectedSaleId = $this->selectedSaleId($request);
        $selectedSale = $selectedSaleId !== null
            ? $this->selectedSaleQuery()->find($selectedSaleId)
            : null;

        return view('sale-returns.index', [
            ...$this->pageData(),
            'saleOptions' => $this->saleOptions($selectedSaleId),
            'selectedSale' => $selectedSale,
            'selectedSaleId' => $selectedSaleId,
            'initialForm' => [
                'return_number' => $this->previewReturnNumber(),
                'return_date' => (string) $request->session()->getOldInput('return_date', now()->format('Y-m-d')),
                'rows' => $this->initialRows($request, $selectedSale),
            ],
            'showFormSection' => true,
            'showHistorySection' => false,
        ]);
    }

    /**
     * Display the sale return history page.
     */
    public function history(Request $request): View
    {
        $selectedSaleId = $this->selectedSaleId($request);
        $historySearch = trim((string) $request->query('history_search', ''));
        $returns = $this->saleReturnHistory($historySearch, $selectedSaleId);

        return view('sale-returns.index', [
            ...$this->pageData('penjualan.riwayat-retur-penjualan', 'Riwayat Retur Penjualan'),
            'saleOptions' => collect(),
            'selectedSale' => null,
            'selectedSaleId' => $selectedSaleId,
            'historySearch' => $historySearch,
            'returns' => $returns,
            'detailPayloads' => $this->detailPayloads($returns),
            'showFormSection' => false,
            'showHistorySection' => true,
        ]);
    }

    /**
     * Store a newly created sale return.
     */
    public function store(SaleReturnRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $sale = Sale::query()
            ->with([
                'customer:id,name,phone',
                'customerGroup:id,name,markup_percentage',
            ])
            ->findOrFail((int) $validated['sale_id']);
        $returnDate = $this->resolveReturnDateTime((string) $validated['return_date']);

        try {
            $saleReturn = DB::transaction(function () use ($request, $sale, $returnDate, $validated) {
                $normalizedItems = $this->normalizeReturnItems($validated['items'], $sale->id);
                $subtotal = round(collect($normalizedItems)->sum('line_total'), 2);

                $saleReturn = SaleReturn::query()->create([
                    'return_number' => $this->nextReturnNumber(),
                    'sale_id' => $sale->id,
                    'return_date' => $returnDate,
                    'status' => 'posted',
                    'subtotal' => $subtotal,
                    'tax_amount' => 0,
                    'total_amount' => $subtotal,
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()?->id,
                ]);

                foreach ($normalizedItems as $item) {
                    $stockBatch = StockBatch::query()
                        ->lockForUpdate()
                        ->find($item['stock_batch']->id);

                    if ($stockBatch === null) {
                        throw new RuntimeException('Batch stok asal untuk item retur penjualan ini tidak ditemukan.');
                    }

                    $saleReturnItem = $saleReturn->items()->create([
                        'sale_item_id' => $item['sale_item']->id,
                        'medicine_id' => $item['medicine']->id,
                        'stock_batch_id' => $stockBatch->id,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'line_total' => $item['line_total'],
                        'reason' => $item['reason'],
                    ]);

                    $newQuantityIn = round((float) $stockBatch->quantity_in + $item['quantity'], 2);
                    $newBalance = round((float) $stockBatch->quantity_balance + $item['quantity'], 2);

                    $stockBatch->update([
                        'quantity_in' => $newQuantityIn,
                        'quantity_balance' => $newBalance,
                        'status' => 'active',
                        'notes' => trim((string) $stockBatch->notes.' | Retur penjualan '.$saleReturn->return_number),
                    ]);

                    StockMovement::query()->create([
                        'movement_date' => $returnDate,
                        'movement_type' => 'sale_return',
                        'reference_table' => 'sale_return_items',
                        'reference_id' => $saleReturnItem->id,
                        'medicine_id' => $item['medicine']->id,
                        'stock_batch_id' => $stockBatch->id,
                        'storage_location_id' => $stockBatch->storage_location_id,
                        'quantity_in' => $item['quantity'],
                        'quantity_out' => 0,
                        'balance_after' => $newBalance,
                        'unit_cost' => $item['stock_unit_cost'],
                        'notes' => 'Retur penjualan '.$saleReturn->return_number,
                        'created_by' => $request->user()?->id,
                    ]);
                }

                return $saleReturn;
            });
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('penjualan.retur-penjualan', ['sale_id' => $sale->id])
                ->withInput()
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('penjualan.retur-penjualan', ['sale_id' => $sale->id])
            ->with('toast', [
                'type' => 'success',
                'message' => 'Retur penjualan '.$saleReturn->return_number.' berhasil disimpan.',
            ]);
    }

    /**
     * Delete a sale return and withdraw the returned stock again.
     */
    public function destroy(SaleReturn $saleReturn): RedirectResponse
    {
        $saleId = $saleReturn->sale_id;
        $returnNumber = $saleReturn->return_number;

        try {
            DB::transaction(function () use ($saleReturn): void {
                $lockedReturn = SaleReturn::query()
                    ->with('items')
                    ->lockForUpdate()
                    ->findOrFail($saleReturn->id);

                foreach ($lockedReturn->items as $returnItem) {
                    $stockBatch = StockBatch::query()
                        ->lockForUpdate()
                        ->find($returnItem->stock_batch_id);

                    if ($stockBatch === null) {
                        throw new RuntimeException('Batch stok asal untuk retur ini tidak ditemukan, jadi retur belum bisa dihapus.');
                    }

                    $quantity = (float) $returnItem->quantity;

                    if ((float) $stockBatch->quantity_balance + 0.001 < $quantity) {
                        throw new RuntimeException('Stok hasil retur ini sudah terpakai, jadi retur belum bisa dihapus.');
                    }

                    $newQuantityIn = round((float) $stockBatch->quantity_in - $quantity, 2);
                    $newBalance = round((float) $stockBatch->quantity_balance - $quantity, 2);

                    if ($newQuantityIn < -0.001 || $newBalance < -0.001) {
                        throw new RuntimeException('Saldo stok batch sudah tidak sinkron, jadi retur belum bisa dihapus.');
                    }

                    $stockBatch->update([
                        'quantity_in' => max($newQuantityIn, 0),
                        'quantity_balance' => max($newBalance, 0),
                        'status' => $newBalance > 0 ? 'active' : 'sold_out',
                        'notes' => $this->removeSaleReturnNote((string) $stockBatch->notes, $lockedReturn->return_number),
                    ]);
                }

                StockMovement::query()
                    ->where('reference_table', 'sale_return_items')
                    ->whereIn('reference_id', $lockedReturn->items->pluck('id'))
                    ->delete();

                $lockedReturn->items()->delete();
                $lockedReturn->delete();
            });
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('penjualan.riwayat-retur-penjualan', $saleId ? ['sale_id' => $saleId] : [])
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('penjualan.riwayat-retur-penjualan', $saleId ? ['sale_id' => $saleId] : [])
            ->with('toast', [
                'type' => 'success',
                'message' => 'Retur penjualan '.$returnNumber.' berhasil dihapus dan stok ditarik kembali.',
            ]);
    }

    /**
     * Build the sale return history result set.
     */
    private function saleReturnHistory(string $historySearch, ?int $selectedSaleId): LengthAwarePaginator
    {
        return SaleReturn::query()
            ->with([
                'sale:id,sale_number,sale_date,customer_name,customer_phone,payment_method,paid_amount,grand_total',
                'items' => fn ($query) => $query
                    ->with([
                        'medicine:id,code,name,small_unit',
                        'stockBatch:id,batch_number,expiry_date',
                        'saleItem:id,batch_number_snapshot,expiry_date_snapshot',
                    ])
                    ->orderBy('id'),
            ])
            ->when($selectedSaleId !== null, fn ($query) => $query->where('sale_id', $selectedSaleId))
            ->when($historySearch !== '', function ($query) use ($historySearch) {
                $query->where(function ($returnQuery) use ($historySearch) {
                    $returnQuery
                        ->where('return_number', 'like', "%{$historySearch}%")
                        ->orWhereHas('sale', function ($saleQuery) use ($historySearch) {
                            $saleQuery
                                ->where('sale_number', 'like', "%{$historySearch}%")
                                ->orWhere('customer_name', 'like', "%{$historySearch}%");
                        })
                        ->orWhereHas('items', function ($itemQuery) use ($historySearch) {
                            $itemQuery
                                ->where('reason', 'like', "%{$historySearch}%")
                                ->orWhereHas('stockBatch', fn ($batchQuery) => $batchQuery->where('batch_number', 'like', "%{$historySearch}%"))
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
     * Build the selected sale query with returnable items.
     */
    private function selectedSaleQuery()
    {
        return Sale::query()
            ->where('status', 'posted')
            ->with([
                'customer:id,name,phone',
                'customerGroup:id,name,markup_percentage',
                'items' => fn ($query) => $query
                    ->with([
                        'medicine:id,code,name,small_unit,large_unit,principal_id',
                        'medicine.principal:id,name',
                        'stockBatch:id,batch_number,expiry_date,quantity_balance,purchase_price,storage_location_id,quantity_in',
                        'saleReturnItems:id,sale_item_id,quantity',
                    ])
                    ->orderBy('id'),
            ]);
    }

    /**
     * Get sale options that still have returnable quantity.
     */
    private function saleOptions(?int $selectedSaleId): Collection
    {
        return Sale::query()
            ->select(['id', 'sale_number', 'sale_date', 'customer_id', 'customer_name'])
            ->where('status', 'posted')
            ->with([
                'customer:id,name',
                'items:id,sale_id,quantity',
                'items.saleReturnItems:id,sale_item_id,quantity',
            ])
            ->latest('sale_date')
            ->latest('id')
            ->get()
            ->filter(function (Sale $sale) use ($selectedSaleId): bool {
                if ($selectedSaleId !== null && $sale->id === $selectedSaleId) {
                    return true;
                }

                return $sale->items->contains(
                    fn (SaleItem $item): bool => $this->remainingReturnQuantity($item) > 0
                );
            })
            ->values();
    }

    /**
     * Determine the currently selected sale ID.
     */
    private function selectedSaleId(Request $request): ?int
    {
        $oldInputId = $request->session()->getOldInput('sale_id');
        $queryId = $request->query('sale_id');
        $rawValue = $oldInputId !== null ? $oldInputId : $queryId;

        if (! filled($rawValue)) {
            return null;
        }

        return (int) $rawValue;
    }

    /**
     * Build initial returnable rows for the selected sale.
     *
     * @return array<int, array<string, mixed>>
     */
    private function initialRows(Request $request, ?Sale $sale): array
    {
        if ($sale === null) {
            return [];
        }

        $oldItems = collect($request->session()->getOldInput('items', []))
            ->filter(fn ($item) => is_array($item) && isset($item['sale_item_id']))
            ->keyBy(fn (array $item): int => (int) $item['sale_item_id']);

        return $sale->items
            ->map(function (SaleItem $item) use ($oldItems): ?array {
                $remainingQuantity = $this->remainingReturnQuantity($item);

                if ($remainingQuantity <= 0) {
                    return null;
                }

                $oldItem = $oldItems->get($item->id, []);
                $stockBatch = $item->stockBatch;

                return [
                    'key' => 'sale-return-item-'.$item->id,
                    'sale_item_id' => $item->id,
                    'medicine_code' => $item->medicine?->code ?: '-',
                    'medicine_name' => $item->medicine?->name ?: '-',
                    'principal_name' => $item->medicine?->principal?->name ?: '-',
                    'small_unit' => $item->medicine?->small_unit ?: 'unit',
                    'batch_number' => $stockBatch?->batch_number ?: ($item->batch_number_snapshot ?: '-'),
                    'expiry_date' => $stockBatch?->expiry_date?->format('Y-m-d') ?? $item->expiry_date_snapshot?->format('Y-m-d'),
                    'expiry_label' => $stockBatch?->expiry_date?->translatedFormat('d M Y') ?? $item->expiry_date_snapshot?->translatedFormat('d M Y') ?? '-',
                    'sold_quantity' => (string) round((float) $item->quantity, 2),
                    'sold_quantity_label' => $this->formatQuantity((float) $item->quantity),
                    'available_quantity' => (string) $remainingQuantity,
                    'available_quantity_label' => $this->formatQuantity($remainingQuantity),
                    'current_stock' => (string) round((float) ($stockBatch?->quantity_balance ?? 0), 2),
                    'current_stock_label' => $this->formatQuantity((float) ($stockBatch?->quantity_balance ?? 0)),
                    'unit_price' => (string) round((float) $item->unit_price, 2),
                    'quantity' => (string) ($oldItem['quantity'] ?? ''),
                    'reason' => (string) ($oldItem['reason'] ?? ''),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Build detail payloads for sale return history.
     *
     * @param  LengthAwarePaginator<int, SaleReturn>  $returns
     * @return array<int, array<string, mixed>>
     */
    private function detailPayloads(LengthAwarePaginator $returns): array
    {
        return $returns->getCollection()
            ->mapWithKeys(function (SaleReturn $saleReturn): array {
                return [
                    $saleReturn->id => [
                        'return_number' => $saleReturn->return_number,
                        'return_date' => $saleReturn->return_date?->translatedFormat('d M Y') ?? '-',
                        'sale_number' => $saleReturn->sale?->sale_number ?: '-',
                        'customer' => $saleReturn->sale?->customer_name ?: '-',
                        'payment_status' => $this->paymentStatusLabel($saleReturn->sale),
                        'subtotal' => $this->formatCurrency((float) $saleReturn->subtotal),
                        'total_amount' => $this->formatCurrency((float) $saleReturn->total_amount),
                        'items' => $saleReturn->items->map(function (SaleReturnItem $item): array {
                            return [
                                'id' => $item->id,
                                'medicine' => $item->medicine?->name ?: '-',
                                'medicine_code' => $item->medicine?->code ?: '-',
                                'batch_number' => $item->stockBatch?->batch_number ?: ($item->saleItem?->batch_number_snapshot ?: '-'),
                                'expiry_date' => $item->stockBatch?->expiry_date?->translatedFormat('d M Y')
                                    ?? $item->saleItem?->expiry_date_snapshot?->translatedFormat('d M Y')
                                    ?? '-',
                                'quantity' => $this->formatQuantity((float) $item->quantity).' '.($item->medicine?->small_unit ?: 'unit'),
                                'unit_price' => $this->formatCurrency((float) $item->unit_price),
                                'line_total' => $this->formatCurrency((float) $item->line_total),
                                'reason' => $item->reason ?: '-',
                            ];
                        })->values()->all(),
                    ],
                ];
            })
            ->all();
    }

    /**
     * Normalize submitted return rows using authoritative sale and batch data.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeReturnItems(array $items, int $saleId): array
    {
        $saleItems = SaleItem::query()
            ->with([
                'medicine',
                'stockBatch',
                'saleReturnItems:id,sale_item_id,quantity',
            ])
            ->whereIn('id', collect($items)->pluck('sale_item_id')->map(fn ($id) => (int) $id)->all())
            ->get()
            ->keyBy('id');

        return collect($items)
            ->values()
            ->map(function (array $item) use ($saleItems, $saleId): array {
                $saleItemId = (int) $item['sale_item_id'];
                /** @var SaleItem|null $saleItem */
                $saleItem = $saleItems->get($saleItemId);

                if ($saleItem === null || (int) $saleItem->sale_id !== $saleId) {
                    throw new RuntimeException('Item retur penjualan tidak valid.');
                }

                if ($saleItem->stockBatch === null) {
                    throw new RuntimeException('Batch stok asal untuk item retur penjualan ini tidak ditemukan.');
                }

                $quantity = round((float) $item['quantity'], 2);
                $remainingQuantity = $this->remainingReturnQuantity($saleItem);

                if ($quantity > $remainingQuantity) {
                    throw new RuntimeException('Qty retur melebihi sisa qty jual yang belum diretur.');
                }

                $unitPrice = round((float) $saleItem->unit_price, 2);

                return [
                    'sale_item' => $saleItem,
                    'stock_batch' => $saleItem->stockBatch,
                    'medicine' => $saleItem->medicine,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($quantity * $unitPrice, 2),
                    'stock_unit_cost' => round((float) $saleItem->stockBatch->purchase_price, 2),
                    'reason' => filled($item['reason'] ?? null) ? trim((string) $item['reason']) : null,
                ];
            })
            ->all();
    }

    /**
     * Resolve the remaining quantity that can still be returned for a sale item.
     */
    private function remainingReturnQuantity(SaleItem $item): float
    {
        $returnedQuantity = (float) $item->saleReturnItems->sum(fn ($returnItem) => (float) $returnItem->quantity);

        return max(round((float) $item->quantity - $returnedQuantity, 2), 0);
    }

    /**
     * Remove the appended sale return note from a stock batch.
     */
    private function removeSaleReturnNote(string $notes, string $returnNumber): ?string
    {
        $cleaned = str_replace(' | Retur penjualan '.$returnNumber, '', $notes);
        $cleaned = str_replace('Retur penjualan '.$returnNumber.' | ', '', $cleaned);
        $cleaned = trim($cleaned, " |\t\n\r\0\x0B");

        return $cleaned !== '' ? $cleaned : null;
    }

    /**
     * Preview the next sale return number for the form.
     */
    private function previewReturnNumber(): string
    {
        return $this->nextReturnNumber();
    }

    /**
     * Combine the chosen return date with the actual save time so stock-card ordering stays natural.
     */
    private function resolveReturnDateTime(string $returnDate): Carbon
    {
        return Carbon::parse($returnDate)->setTimeFrom(now());
    }

    /**
     * Generate the next sale return number.
     */
    private function nextReturnNumber(): string
    {
        $latestCode = SaleReturn::query()
            ->where('return_number', 'like', 'RTJ-%')
            ->orderByDesc('id')
            ->value('return_number');

        if (! is_string($latestCode) || ! preg_match('/(\d+)$/', $latestCode, $matches)) {
            return 'RTJ-0001';
        }

        return 'RTJ-'.str_pad((string) ((int) $matches[1] + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Resolve the display payment status for a sale.
     */
    private function paymentStatusLabel(?Sale $sale): string
    {
        if ($sale === null) {
            return '-';
        }

        return $sale->payment_method === 'credit' && (float) $sale->paid_amount + 0.001 < (float) $sale->grand_total
            ? 'Kredit'
            : 'Lunas';
    }

    /**
     * Build the page metadata for the sale return module.
     *
     * @return array<string, mixed>
     */
    private function pageData(string $routeName = 'penjualan.retur-penjualan', ?string $fallbackLabel = null): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $item): bool => $item['label'] === 'Penjualan');

        $siblings = $section['children'] ?? [];
        $page = collect($siblings)->firstWhere('route', $routeName);

        if ($page === null) {
            $page = [
                'label' => $fallbackLabel ?? 'Retur Penjualan',
                'route' => $routeName,
                'path' => '',
            ];
        }

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Penjualan',
            'siblings' => $siblings,
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
