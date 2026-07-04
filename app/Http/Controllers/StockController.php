<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Medicine;
use App\Models\StockAdjustmentFollowUp;
use App\Models\StockAdjustmentFollowUpBatch;
use App\Models\StockBatch;
use App\Models\StockAdjustmentRecovery;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\PurchaseExchangeItem;
use App\Models\PurchaseExchangeReplacementItem;
use App\Models\PurchaseReturnItem;
use App\Models\PurchaseReturnReplacementItem;
use App\Models\SaleItem;
use App\Models\SaleReturnItem;
use App\Models\StorageLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class StockController extends Controller
{
    /**
     * Display the stock page grouped by medicine.
     */
    public function medicineIndex(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $stockState = trim((string) $request->string('stock_state', 'all'));
        $stockState = in_array($stockState, ['all', 'available', 'empty', 'low'], true) ? $stockState : 'all';
        $locationId = $request->integer('location_id') ?: null;
        $medicineRows = $this->medicineSummaryQuery($search, $stockState, $locationId)
            ->paginate(10)
            ->withQueryString();

        return view('stocks.index', [
            ...$this->pageData('stok-batch.stok-obat'),
            'mode' => 'medicine',
            'search' => $search,
            'stockState' => $stockState,
            'locationId' => $locationId,
            'locations' => $this->activeLocationOptions(),
            'rows' => $medicineRows,
            'detailPayloads' => $this->medicineDetailPayloads(
                $medicineRows->getCollection()->pluck('medicine_id')->filter(),
                $locationId,
            ),
        ]);
    }

    /**
     * Display the stock page grouped by batch.
     */
    public function batchIndex(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $expiryWithinMonths = $this->normalizeExpiryWithinMonths($request->input('expiry_within_months'));
        $locationId = $request->integer('location_id') ?: null;
        $groupedBatchRows = $this->groupedBatchRows($search, $expiryWithinMonths, $locationId);
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $currentItems = $groupedBatchRows->forPage($currentPage, $perPage)->values();
        $batchRows = new LengthAwarePaginator(
            $currentItems,
            $groupedBatchRows->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return view('stocks.index', [
            ...$this->pageData('stok-batch.stok-per-batch'),
            'mode' => 'batch',
            'search' => $search,
            'stockState' => 'available',
            'expiryWithinMonths' => $expiryWithinMonths !== null ? (string) $expiryWithinMonths : '',
            'locationId' => $locationId,
            'locations' => $this->activeLocationOptions(),
            'rows' => $batchRows,
            'detailPayloads' => $this->batchDetailPayloads($currentItems, $locationId),
        ]);
    }

    /**
     * Display stock adjustments generated from stock opname approvals.
     */
    public function adjustmentIndex(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $adjustmentType = trim((string) $request->string('adjustment_type', 'all'));
        $adjustmentType = in_array($adjustmentType, ['all', 'loss', 'gain'], true) ? $adjustmentType : 'all';
        $dateFrom = trim((string) $request->string('date_from', now()->startOfMonth()->toDateString()));
        $dateTo = trim((string) $request->string('date_to', now()->toDateString()));

        $rows = StockOpname::query()
            ->with([
                'creator:id,name',
                'approver:id,name',
                'items' => function ($query) {
                    $query
                        ->whereRaw('ABS(difference_quantity) > 0.0001')
                        ->with([
                            'medicine:id,code,name,small_unit',
                            'followUp:id,stock_opname_item_id,adjustment_number,status,settlement_type',
                        ])
                        ->orderBy('id');
                },
            ])
            ->where('status', 'approved')
            ->whereHas('items', function (Builder $builder) use ($adjustmentType, $search): void {
                $builder
                    ->whereRaw('ABS(difference_quantity) > 0.0001')
                    ->when($adjustmentType === 'loss', fn (Builder $query) => $query->where('difference_quantity', '<', 0))
                    ->when($adjustmentType === 'gain', fn (Builder $query) => $query->where('difference_quantity', '>', 0))
                    ->when($search !== '', function (Builder $query) use ($search): void {
                        $query->where(function (Builder $innerQuery) use ($search): void {
                            $innerQuery
                                ->whereHas('medicine', function (Builder $medicineQuery) use ($search): void {
                                    $medicineQuery
                                        ->where('code', 'like', "%{$search}%")
                                        ->orWhere('name', 'like', "%{$search}%");
                                })
                                ->orWhereHas('followUp', fn (Builder $followUpQuery) => $followUpQuery->where('adjustment_number', 'like', "%{$search}%"));
                        });
                    });
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $innerQuery) use ($search): void {
                    $innerQuery
                        ->where('opname_number', 'like', "%{$search}%")
                        ->orWhereHas('creator', fn (Builder $creatorQuery) => $creatorQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('approver', fn (Builder $approverQuery) => $approverQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($dateFrom !== '', fn (Builder $query) => $query->whereDate('opname_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn (Builder $query) => $query->whereDate('opname_date', '<=', $dateTo))
            ->latest('opname_date')
            ->latest('id')
            ->paginate(15)
            ->through(function (StockOpname $opname) use ($adjustmentType) {
                $items = $opname->items
                    ->filter(function (StockOpnameItem $item) use ($adjustmentType): bool {
                        if ($adjustmentType === 'loss') {
                            return (float) $item->difference_quantity < 0;
                        }

                        if ($adjustmentType === 'gain') {
                            return (float) $item->difference_quantity > 0;
                        }

                        return true;
                    })
                    ->values();

                $lossCount = $items->filter(fn (StockOpnameItem $item): bool => (float) $item->difference_quantity < 0)->count();
                $gainCount = $items->filter(fn (StockOpnameItem $item): bool => (float) $item->difference_quantity > 0)->count();
                $appliedCount = $items->filter(fn (StockOpnameItem $item): bool => $item->followUp?->status === 'applied')->count();
                $draftCount = $items->filter(fn (StockOpnameItem $item): bool => $item->followUp?->status === 'draft')->count();
                $status = $appliedCount === $items->count() && $items->count() > 0
                    ? 'applied'
                    : ($draftCount > 0 || $appliedCount > 0 ? 'draft' : 'pending');

                return (object) [
                    'id' => $opname->id,
                    'opname_number' => $opname->opname_number,
                    'opname_date' => $opname->opname_date,
                    'creator_name' => $opname->creator?->name ?: '-',
                    'approver_name' => $opname->approver?->name ?: '-',
                    'item_count' => $items->count(),
                    'loss_count' => $lossCount,
                    'gain_count' => $gainCount,
                    'total_adjustment_value' => (float) $items->sum('adjustment_value'),
                    'status' => $status,
                    'document_url' => route('stok-batch.penyesuaian-stok.dokumen', $opname->id),
                    'opname_url' => route('stok-batch.stok-opname.show', $opname->id),
                ];
            })
            ->withQueryString();

        $statsBase = StockOpnameItem::query()
            ->whereRaw('ABS(difference_quantity) > 0.0001')
            ->whereHas('stockOpname', function (Builder $builder) use ($dateFrom, $dateTo): void {
                $builder
                    ->where('status', 'approved')
                    ->when($dateFrom !== '', fn (Builder $query) => $query->whereDate('opname_date', '>=', $dateFrom))
                    ->when($dateTo !== '', fn (Builder $query) => $query->whereDate('opname_date', '<=', $dateTo));
            });

        return view('stocks.adjustments', [
            ...$this->pageData('stok-batch.penyesuaian-stok'),
            'rows' => $rows,
            'search' => $search,
            'adjustmentType' => $adjustmentType,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'stats' => [
                'total' => (clone $statsBase)->count(),
                'gain_count' => (clone $statsBase)->where('difference_quantity', '>', 0)->count(),
                'loss_count' => (clone $statsBase)->where('difference_quantity', '<', 0)->count(),
                'value_total' => (float) (clone $statsBase)
                    ->selectRaw('COALESCE(SUM(adjustment_value), 0) as total_value')
                    ->value('total_value'),
            ],
        ]);
    }

    /**
     * Show one stock-adjustment document grouped by stock opname.
     */
    public function adjustmentDocumentShow(StockOpname $stockOpname): View
    {
        $stockOpname->load([
            'creator:id,name',
            'approver:id,name',
            'items' => function ($query) {
                $query
                    ->whereRaw('ABS(difference_quantity) > 0.0001')
                    ->with([
                        'medicine:id,code,name,small_unit',
                        'followUp:id,stock_opname_item_id,adjustment_number,status,settlement_type',
                    ])
                    ->orderBy('id');
            },
        ]);

        abort_unless($stockOpname->status === 'approved', 404);

        $rows = $stockOpname->items
            ->sortBy(fn (StockOpnameItem $item) => [
                (string) ($item->medicine?->name ?? ''),
                (string) ($item->medicine?->code ?? ''),
            ])
            ->values();

        return view('stocks.adjustment-document', [
            ...$this->pageData('stok-batch.penyesuaian-stok'),
            'stockOpname' => $stockOpname,
            'rows' => $rows,
            'summary' => [
                'item_count' => $rows->count(),
                'loss_count' => $rows->filter(fn (StockOpnameItem $item): bool => (float) $item->difference_quantity < 0)->count(),
                'gain_count' => $rows->filter(fn (StockOpnameItem $item): bool => (float) $item->difference_quantity > 0)->count(),
                'applied_count' => $rows->filter(fn (StockOpnameItem $item): bool => $item->followUp?->status === 'applied')->count(),
                'draft_count' => $rows->filter(fn (StockOpnameItem $item): bool => $item->followUp?->status === 'draft')->count(),
                'pending_count' => $rows->filter(fn (StockOpnameItem $item): bool => $item->followUp === null)->count(),
                'total_adjustment' => $this->formatCurrency((float) $rows->sum('adjustment_value')),
            ],
        ]);
    }

    /**
     * Process all drafted follow-ups within one stock opname document.
     */
    public function adjustmentDocumentProcess(StockOpname $stockOpname): RedirectResponse
    {
        $stockOpname->load([
            'items' => function ($query) {
                $query
                    ->whereRaw('ABS(difference_quantity) > 0.0001')
                    ->with([
                        'stockOpname:id,opname_number,opname_date,status',
                        'medicine:id,code,name,purchase_price',
                        'followUp.batchSelections.stockBatch',
                        'followUp.recovery',
                    ])
                    ->orderBy('id');
            },
        ]);

        abort_unless($stockOpname->status === 'approved', 404);

        /** @var Collection<int, StockOpnameItem> $rows */
        $rows = $stockOpname->items->values();

        if ($rows->isEmpty()) {
            return redirect()
                ->route('stok-batch.penyesuaian-stok.dokumen', $stockOpname->id)
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Dokumen ini tidak memiliki item selisih untuk diproses.',
                ]);
        }

        $pendingItems = $rows->filter(fn (StockOpnameItem $item): bool => $item->followUp === null);

        if ($pendingItems->isNotEmpty()) {
            return redirect()
                ->route('stok-batch.penyesuaian-stok.dokumen', $stockOpname->id)
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Masih ada item selisih yang belum diatur. Lengkapi semua draft item terlebih dahulu sebelum memproses dokumen.',
                ]);
        }

        $draftItems = $rows->filter(fn (StockOpnameItem $item): bool => $item->followUp?->status === 'draft')->values();

        if ($draftItems->isEmpty()) {
            return redirect()
                ->route('stok-batch.penyesuaian-stok.dokumen', $stockOpname->id)
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Tidak ada draft tindak lanjut yang perlu diproses pada dokumen ini.',
                ]);
        }

        try {
            DB::transaction(function () use ($draftItems): void {
                foreach ($draftItems as $item) {
                    $this->processAdjustmentFollowUpOrFail($item);
                }
            });
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('stok-batch.penyesuaian-stok.dokumen', $stockOpname->id)
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('stok-batch.penyesuaian-stok.dokumen', $stockOpname->id)
            ->with('toast', [
                'type' => 'success',
                'message' => 'Seluruh draft tindak lanjut pada dokumen ini berhasil diproses.',
            ]);
    }

    /**
     * Show a follow-up form for one opname-difference item.
     */
    public function adjustmentFollowUpCreate(StockOpnameItem $stockOpnameItem): View
    {
        $stockOpnameItem->load([
            'medicine:id,code,name,small_unit',
            'stockOpname:id,opname_number,opname_date,status',
            'followUp.batchSelections.stockBatch:id,batch_number',
            'followUp.replacementStorageLocation:id,name',
            'followUp.recovery',
        ]);

        abort_unless($stockOpnameItem->stockOpname?->status === 'approved', 404);
        abort_if(abs((float) $stockOpnameItem->difference_quantity) < 0.0001, 404);

        $showAllBatches = request()->boolean('show_all_batches');

        $baseBatchQuery = StockBatch::query()
            ->with('storageLocation:id,name')
            ->where('medicine_id', $stockOpnameItem->medicine_id)
            ->orderBy('expiry_date')
            ->orderByDesc('quantity_balance')
            ->orderByDesc('received_at')
            ->orderBy('batch_number');

        $batchRows = $showAllBatches
            ? (clone $baseBatchQuery)->get()
            : (clone $baseBatchQuery)->where('quantity_balance', '>', 0)->get();

        if ($batchRows->isEmpty()) {
            $batchRows = (clone $baseBatchQuery)->get();
            $showAllBatches = true;
        }

        $activeLocations = StorageLocation::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectionMap = $stockOpnameItem->followUp?->batchSelections
            ?->keyBy(fn (StockAdjustmentFollowUpBatch $selection) => (string) ($selection->stockBatch?->batch_number ?: $selection->stock_batch_id))
            ?? collect();

        return view('stocks.adjustment-follow-up', [
            ...$this->pageData('stok-batch.penyesuaian-stok'),
            'item' => $stockOpnameItem,
            'batches' => $this->groupFollowUpBatchRows($batchRows),
            'showAllBatches' => $showAllBatches,
            'selectionMap' => $selectionMap,
            'activeLocations' => $activeLocations,
            'defaultAdjustmentNumber' => $stockOpnameItem->followUp?->adjustment_number ?? $this->generateAdjustmentFollowUpNumber(),
            'defaultAdjustmentDate' => $stockOpnameItem->followUp?->adjustment_date?->toDateString() ?? now()->toDateString(),
        ]);
    }

    /**
     * Store a draft follow-up adjustment selection per batch.
     */
    public function adjustmentFollowUpStore(Request $request, StockOpnameItem $stockOpnameItem): RedirectResponse
    {
        $stockOpnameItem->loadMissing([
            'stockOpname:id,opname_number,opname_date,status',
            'followUp.batchSelections',
        ]);

        abort_unless($stockOpnameItem->stockOpname?->status === 'approved', 404);
        abort_if(abs((float) $stockOpnameItem->difference_quantity) < 0.0001, 404);

        if ($stockOpnameItem->followUp?->status === 'applied') {
            return redirect()
                ->route('stok-batch.penyesuaian-stok.follow-up', $stockOpnameItem->id)
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Tindak lanjut ini sudah diproses dan tidak bisa diubah lagi.',
                ]);
        }

        $validator = Validator::make($request->all(), [
            'adjustment_number' => ['required', 'string', 'max:50'],
            'adjustment_date' => ['required', 'date'],
            'settlement_type' => ['required', 'string', 'max:30'],
            'employee_name' => ['nullable', 'string', 'max:255'],
            'replacement_batch_number' => ['nullable', 'string', 'max:100'],
            'replacement_expiry_date' => ['nullable', 'date'],
            'replacement_purchase_price' => ['nullable', 'numeric', 'min:0'],
            'replacement_storage_location_id' => ['nullable', 'integer', 'exists:storage_locations,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'batches' => ['required', 'array'],
            'batches.*.batch_number' => ['nullable', 'string', 'max:100'],
            'batches.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withInput()
                ->withErrors($validator)
                ->with('toast', [
                    'type' => 'error',
                    'message' => $validator->errors()->first(),
                ]);
        }

        $validated = $validator->validated();

        $differenceType = (float) $stockOpnameItem->difference_quantity < 0 ? 'loss' : 'gain';
        $physicalQuantity = round((float) $stockOpnameItem->physical_quantity, 2);
        $settlementType = trim((string) $validated['settlement_type']);

        if ($differenceType === 'gain' && $settlementType !== 'stock_found') {
            return back()
                ->withInput()
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Stok lebih hanya bisa memakai jenis penyelesaian stok lebih ditemukan.',
                ]);
        }

        if ($differenceType === 'loss' && ! in_array($settlementType, ['writeoff', 'replace_goods', 'replace_cash'], true)) {
            return back()
                ->withInput()
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Jenis penyelesaian stok hilang belum valid.',
                ]);
        }

        if ($settlementType === 'replace_cash' && blank($validated['employee_name'] ?? null)) {
            return back()
                ->withInput()
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Nama penanggung jawab wajib diisi untuk ganti uang.',
                ]);
        }

        if ($settlementType === 'replace_goods') {
            if (blank($validated['replacement_batch_number'] ?? null)) {
                return back()
                    ->withInput()
                    ->with('toast', [
                        'type' => 'error',
                        'message' => 'Batch pengganti wajib diisi untuk ganti barang.',
                    ]);
            }

            if (blank($validated['replacement_expiry_date'] ?? null)) {
                return back()
                    ->withInput()
                    ->with('toast', [
                        'type' => 'error',
                        'message' => 'Tanggal expired batch pengganti wajib diisi.',
                    ]);
            }
        }

        $selectedBatches = collect($validated['batches'])
            ->map(function (array $batch): ?array {
                $quantity = round((float) ($batch['quantity'] ?? 0), 2);
                $batchNumber = trim((string) ($batch['batch_number'] ?? ''));

                if ($quantity < 0 || $batchNumber === '') {
                    return null;
                }

                return [
                    'batch_number' => $batchNumber,
                    'quantity' => $quantity,
                ];
            })
            ->filter()
            ->values();

        $selectedQuantity = round((float) $selectedBatches->sum('quantity'), 2);

        if ($selectedBatches->isEmpty() && $physicalQuantity > 0.001) {
            return back()
                ->withInput()
                ->withErrors([
                    'batches' => 'Pilih minimal satu batch untuk tindak lanjut selisih stok.',
                ])
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Pilih minimal satu batch untuk tindak lanjut selisih stok.',
                ]);
        }

        if (abs($selectedQuantity - $physicalQuantity) > 0.001) {
            return back()
                ->withInput()
                ->withErrors([
                    'batches' => 'Total stok fisik per batch harus sama dengan stok fisik hasil opname.',
                ])
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Total stok fisik per batch harus sama dengan stok fisik hasil opname.',
                ]);
        }

        $batchRows = StockBatch::query()
            ->where('medicine_id', $stockOpnameItem->medicine_id)
            ->whereIn('batch_number', $selectedBatches->pluck('batch_number')->all())
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (StockBatch $batch): string => (string) $batch->batch_number);

        DB::transaction(function () use ($validated, $stockOpnameItem, $differenceType, $selectedBatches, $batchRows, $settlementType): void {
            /** @var StockAdjustmentFollowUp $followUp */
            $followUp = StockAdjustmentFollowUp::query()->updateOrCreate(
                ['stock_opname_item_id' => $stockOpnameItem->id],
                [
                    'adjustment_number' => trim((string) $validated['adjustment_number']),
                    'adjustment_date' => $validated['adjustment_date'],
                    'difference_type' => $differenceType,
                    'settlement_type' => $settlementType,
                    'status' => 'draft',
                    'employee_name' => filled($validated['employee_name'] ?? null) ? trim((string) $validated['employee_name']) : null,
                    'replacement_batch_number' => filled($validated['replacement_batch_number'] ?? null) ? trim((string) $validated['replacement_batch_number']) : null,
                    'replacement_expiry_date' => $validated['replacement_expiry_date'] ?? null,
                    'replacement_purchase_price' => filled($validated['replacement_purchase_price'] ?? null) ? round((float) $validated['replacement_purchase_price'], 2) : null,
                    'replacement_storage_location_id' => filled($validated['replacement_storage_location_id'] ?? null) ? (int) $validated['replacement_storage_location_id'] : null,
                    'notes' => filled($validated['notes'] ?? null) ? trim((string) $validated['notes']) : null,
                    'processed_at' => null,
                    'processed_by' => null,
                    'created_by' => auth()->id(),
                ]
            );

            $followUp->batchSelections()->delete();

            $followUp->batchSelections()->createMany(
                $selectedBatches
                    ->map(function (array $batch) use ($differenceType, $batchRows): array {
                        /** @var Collection<int, StockBatch> $groupedBatches */
                        $groupedBatches = $batchRows->get($batch['batch_number'], collect());
                        /** @var StockBatch|null $stockBatch */
                        $stockBatch = $groupedBatches->first();
                        $totalBalance = round((float) $groupedBatches->sum(fn (StockBatch $row) => (float) $row->quantity_balance), 2);
                        $weightedCost = $totalBalance > 0
                            ? round(
                                (float) $groupedBatches->sum(
                                    fn (StockBatch $row): float => (float) $row->quantity_balance * (float) $row->purchase_price
                                ) / $totalBalance,
                                2
                            )
                            : round((float) ($stockBatch?->purchase_price ?? 0), 2);

                        return [
                            'stock_batch_id' => $stockBatch?->id,
                            'action_type' => $differenceType === 'loss' ? 'deduct' : 'add',
                            'quantity' => $batch['quantity'],
                            'unit_cost' => $weightedCost,
                            'notes' => 'Batch '.$batch['batch_number'],
                        ];
                    })->all()
            );
        });

        return redirect()
            ->route('stok-batch.penyesuaian-stok.dokumen', $stockOpnameItem->stock_opname_id)
            ->with('toast', [
                'type' => 'success',
                'message' => 'Tindak lanjut penyesuaian stok berhasil disimpan. Stok belum berubah sebelum diproses.',
            ]);
    }

    /**
     * Process a saved follow-up so stock and internal recovery records are updated.
     */
    public function adjustmentFollowUpProcess(StockOpnameItem $stockOpnameItem): RedirectResponse
    {
        $stockOpnameItem->load([
            'stockOpname:id,opname_number,opname_date,status',
            'medicine:id,code,name,purchase_price',
            'followUp.batchSelections.stockBatch',
            'followUp.recovery',
        ]);

        abort_unless($stockOpnameItem->stockOpname?->status === 'approved', 404);

        try {
            DB::transaction(function () use ($stockOpnameItem): void {
                $this->processAdjustmentFollowUpOrFail($stockOpnameItem);
            });
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('stok-batch.penyesuaian-stok.follow-up', $stockOpnameItem->id)
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('stok-batch.penyesuaian-stok.follow-up', $stockOpnameItem->id)
            ->with('toast', [
                'type' => 'success',
                'message' => 'Tindak lanjut stok opname berhasil diproses.',
            ]);
    }

    /**
     * Process one follow-up and fail loudly when required data is incomplete.
     */
    private function processAdjustmentFollowUpOrFail(StockOpnameItem $stockOpnameItem): void
    {
        /** @var StockAdjustmentFollowUp|null $followUp */
        $followUp = $stockOpnameItem->followUp;

        if ($followUp === null || $followUp->batchSelections->isEmpty()) {
            throw new RuntimeException('Simpan tindak lanjut batch terlebih dahulu sebelum diproses.');
        }

        if ($followUp->status === 'applied') {
            throw new RuntimeException('Ada tindak lanjut yang sudah pernah diproses pada dokumen ini.');
        }

        $differenceQuantity = round((float) $stockOpnameItem->difference_quantity, 2);
        $missingQuantity = abs(min($differenceQuantity, 0));

        if ($followUp->settlement_type === 'replace_cash' && blank($followUp->employee_name)) {
            throw new RuntimeException('Nama penanggung jawab wajib diisi sebelum proses ganti uang.');
        }

        if ($followUp->settlement_type === 'replace_goods' && blank($followUp->replacement_batch_number)) {
            throw new RuntimeException('Data batch pengganti wajib lengkap sebelum proses ganti barang.');
        }

        $groupedBatches = StockBatch::query()
            ->where('medicine_id', $stockOpnameItem->medicine_id)
            ->whereIn('batch_number', $followUp->batchSelections
                ->map(fn (StockAdjustmentFollowUpBatch $selection): string => trim((string) Str::after((string) $selection->notes, 'Batch ')))
                ->filter()
                ->values()
                ->all())
            ->lockForUpdate()
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (StockBatch $batch): string => (string) $batch->batch_number);

        foreach ($followUp->batchSelections as $selection) {
            $batchNumber = trim((string) Str::after((string) $selection->notes, 'Batch '));
            /** @var Collection<int, StockBatch> $selectionBatches */
            $selectionBatches = $groupedBatches->get($batchNumber, collect());

            $this->applyFollowUpBatchAdjustment(
                stockOpnameItem: $stockOpnameItem,
                followUp: $followUp,
                groupedBatches: $selectionBatches,
                targetPhysicalQuantity: (float) $selection->quantity,
            );
        }

        if ($followUp->settlement_type === 'replace_goods' && $missingQuantity > 0.001) {
            $purchasePrice = filled($followUp->replacement_purchase_price)
                ? round((float) $followUp->replacement_purchase_price, 2)
                : max(round(abs((float) $stockOpnameItem->adjustment_value) / max($missingQuantity, 1), 2), 0);

            $replacementBatch = StockBatch::query()->create([
                'medicine_id' => $stockOpnameItem->medicine_id,
                'purchase_invoice_item_id' => null,
                'storage_location_id' => $followUp->replacement_storage_location_id,
                'batch_number' => trim((string) $followUp->replacement_batch_number),
                'expiry_date' => $followUp->replacement_expiry_date,
                'received_at' => $followUp->adjustment_date?->toDateString() ?? now()->toDateString(),
                'purchase_price' => $purchasePrice,
                'selling_price' => 0,
                'initial_quantity' => $missingQuantity,
                'quantity_in' => $missingQuantity,
                'quantity_out' => 0,
                'quantity_balance' => $missingQuantity,
                'status' => 'active',
                'notes' => 'Pengganti stok opname '.$followUp->adjustment_number,
            ]);

            StockMovement::query()->create([
                'movement_date' => $followUp->processed_at ?? now(),
                'movement_type' => 'stock_opname_gain',
                'reference_table' => 'stock_opname_items',
                'reference_id' => $stockOpnameItem->id,
                'medicine_id' => $stockOpnameItem->medicine_id,
                'stock_batch_id' => $replacementBatch->id,
                'storage_location_id' => $replacementBatch->storage_location_id,
                'quantity_in' => $missingQuantity,
                'quantity_out' => 0,
                'balance_after' => $missingQuantity,
                'unit_cost' => $purchasePrice,
                'notes' => 'Pengganti batch baru '.$followUp->adjustment_number,
                'created_by' => auth()->id(),
            ]);
        }

        if ($followUp->settlement_type === 'replace_cash' && $missingQuantity > 0.001) {
            StockAdjustmentRecovery::query()->updateOrCreate(
                ['stock_adjustment_follow_up_id' => $followUp->id],
                [
                    'stock_movement_id' => null,
                    'employee_name' => trim((string) $followUp->employee_name),
                    'replacement_amount' => round(abs((float) $stockOpnameItem->adjustment_value), 2),
                    'paid_amount' => (float) ($followUp->recovery?->paid_amount ?? 0),
                    'paid_at' => $followUp->recovery?->paid_at,
                    'status' => $this->resolveAdjustmentRecoveryStatus(
                        round(abs((float) $stockOpnameItem->adjustment_value), 2),
                        (float) ($followUp->recovery?->paid_amount ?? 0),
                    ),
                    'notes' => 'Tagihan '.$followUp->adjustment_number.' dari '.$stockOpnameItem->stockOpname?->opname_number,
                    'created_by' => auth()->id(),
                ]
            );
        }

        $followUp->update([
            'status' => 'applied',
            'processed_at' => now(),
            'processed_by' => auth()->id(),
        ]);
    }

    /**
     * Cancel an already processed follow-up and restore stock balances.
     */
    public function adjustmentFollowUpCancel(Request $request, StockOpnameItem $stockOpnameItem): RedirectResponse
    {
        $stockOpnameItem->load([
            'stockOpname:id,opname_number,opname_date,status',
            'followUp.recovery',
        ]);

        abort_unless($stockOpnameItem->stockOpname?->status === 'approved', 404);

        /** @var StockAdjustmentFollowUp|null $followUp */
        $followUp = $stockOpnameItem->followUp;

        if ($followUp === null || $followUp->status !== 'applied') {
            return $this->followUpCancelRedirect($request, $stockOpnameItem)
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Tindak lanjut ini belum diproses, jadi tidak ada yang perlu dibatalkan.',
                ]);
        }

        if ((float) ($followUp->recovery?->paid_amount ?? 0) > 0.001) {
            return $this->followUpCancelRedirect($request, $stockOpnameItem)
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Proses tidak bisa dibatalkan karena tagihan ganti uang sudah memiliki pembayaran.',
                ]);
        }

        try {
            DB::transaction(function () use ($stockOpnameItem, $followUp): void {
                $movements = StockMovement::query()
                    ->with('stockBatch')
                    ->where('reference_table', 'stock_opname_items')
                    ->where('reference_id', $stockOpnameItem->id)
                    ->where(function (Builder $query) use ($followUp) {
                        $query
                            ->where('notes', 'like', 'Tindak lanjut '.$followUp->adjustment_number.'%')
                            ->orWhere('notes', 'like', 'Pengganti batch baru '.$followUp->adjustment_number.'%');
                    })
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->get();

                if ($movements->isEmpty()) {
                    throw new RuntimeException('Riwayat proses tindak lanjut ini tidak ditemukan, jadi belum bisa dibatalkan.');
                }

                foreach ($movements as $movement) {
                    $hasLaterMovement = StockMovement::query()
                        ->where('stock_batch_id', $movement->stock_batch_id)
                        ->where('id', '>', $movement->id)
                        ->exists();

                    if ($hasLaterMovement) {
                        throw new RuntimeException('Pembatalan ditolak karena batch sudah memiliki mutasi stok setelah tindak lanjut ini diproses.');
                    }

                    $stockBatch = StockBatch::query()
                        ->lockForUpdate()
                        ->find($movement->stock_batch_id);

                    if ($stockBatch === null) {
                        throw new RuntimeException('Batch stok terkait tindak lanjut ini sudah tidak ditemukan.');
                    }

                    $quantityIn = round((float) $movement->quantity_in, 2);
                    $quantityOut = round((float) $movement->quantity_out, 2);
                    $newQuantityIn = round((float) $stockBatch->quantity_in - $quantityIn, 2);
                    $newQuantityOut = round((float) $stockBatch->quantity_out - $quantityOut, 2);
                    $newBalance = round((float) $stockBatch->quantity_balance - $quantityIn + $quantityOut, 2);

                    if ($newQuantityIn < -0.001 || $newQuantityOut < -0.001 || $newBalance < -0.001) {
                        throw new RuntimeException('Saldo batch sudah berubah sehingga pembatalan tidak bisa dilakukan dengan aman.');
                    }

                    $isReplacementBatchMovement = $quantityIn > 0
                        && str_starts_with((string) $movement->notes, 'Pengganti batch baru '.$followUp->adjustment_number)
                        && $stockBatch->purchase_invoice_item_id === null;

                    if ($isReplacementBatchMovement) {
                        $stockBatch->delete();
                        $movement->delete();
                        continue;
                    }

                    $stockBatch->update([
                        'quantity_in' => max($newQuantityIn, 0),
                        'quantity_out' => max($newQuantityOut, 0),
                        'quantity_balance' => max($newBalance, 0),
                        'status' => $newBalance > 0 ? 'active' : 'empty',
                        'notes' => $this->removeFollowUpNote(
                            notes: (string) $stockBatch->notes,
                            adjustmentNumber: $followUp->adjustment_number,
                        ),
                    ]);

                    $movement->delete();
                }

                $followUp->recovery()?->delete();

                $followUp->update([
                    'status' => 'draft',
                    'processed_at' => null,
                    'processed_by' => null,
                ]);
            });
        } catch (RuntimeException $exception) {
            return $this->followUpCancelRedirect($request, $stockOpnameItem)
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return $this->followUpCancelRedirect($request, $stockOpnameItem)
            ->with('toast', [
                'type' => 'success',
                'message' => 'Proses tindak lanjut berhasil dibatalkan dan stok batch sudah dikembalikan.',
            ]);
    }

    /**
     * Store or update employee reimbursement for a stock-loss adjustment.
     */
    public function adjustmentRecoveryStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'stock_movement_id' => ['required', 'integer', 'exists:stock_movements,id'],
            'employee_name' => ['required', 'string', 'max:255'],
            'replacement_amount' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $movement = StockMovement::query()
            ->with(['medicine:id,code,name', 'stockBatch:id,batch_number'])
            ->findOrFail((int) $validated['stock_movement_id']);

        if ($movement->movement_type !== 'stock_opname_loss') {
            return redirect()
                ->route('stok-batch.penyesuaian-stok')
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Penggantian uang hanya bisa dicatat untuk penyesuaian stok hilang.',
                ]);
        }

        $replacementAmount = round((float) $validated['replacement_amount'], 2);
        $paidAmount = round((float) $validated['paid_amount'], 2);

        if ($paidAmount - $replacementAmount > 0.001) {
            return redirect()
                ->route('stok-batch.penyesuaian-stok', ['recovery' => $movement->id])
                ->withInput()
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Nominal dibayar tidak boleh melebihi nilai tanggungan.',
                ]);
        }

        $status = $this->resolveAdjustmentRecoveryStatus($replacementAmount, $paidAmount);
        $paidAt = $paidAmount > 0
            ? ($validated['paid_at'] ?? now()->toDateString())
            : null;

        StockAdjustmentRecovery::query()->updateOrCreate(
            ['stock_movement_id' => $movement->id],
            [
                'employee_name' => trim((string) $validated['employee_name']),
                'replacement_amount' => $replacementAmount,
                'paid_amount' => $paidAmount,
                'paid_at' => $paidAt,
                'status' => $status,
                'notes' => filled($validated['notes'] ?? null) ? trim((string) $validated['notes']) : null,
                'created_by' => auth()->id(),
            ],
        );

        return redirect()
            ->route('stok-batch.penyesuaian-stok')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data ganti uang pegawai berhasil disimpan.',
            ]);
    }

    /**
     * Display the stock opname worksheet page per batch.
     */
    public function opnameIndex(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $locationId = (int) $request->integer('location_id');
        $locationId = $locationId > 0 ? $locationId : $this->defaultOpnameLocationId();

        $rows = $this->stockOpnameMedicineRows($search, $locationId);

        $activeLocations = StorageLocation::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('stocks.opname', [
            ...$this->pageData('stok-batch.stok-opname'),
            'search' => $search,
            'locationId' => $locationId,
            'rows' => $rows,
            'locations' => $activeLocations,
            'defaultOpnameNumber' => $this->generateOpnameNumber(),
            'defaultOpnameDate' => now()->toDateString(),
            'summary' => [
                'total_medicines' => $rows->count(),
                'total_batches' => (int) $rows->sum('batch_count'),
                'total_locations' => $rows->flatMap(fn (array $row): array => $row['location_names'] ?? [])->filter(fn (string $name): bool => $name !== '-')->unique()->count(),
                'total_system_quantity' => $this->formatWholeQuantity((float) $rows->sum('system_quantity')),
            ],
        ]);
    }

    /**
     * Resolve the default storage location for stock opname.
     */
    private function defaultOpnameLocationId(): ?int
    {
        $defaultLocation = StorageLocation::query()
            ->active()
            ->where(function (Builder $builder): void {
                $builder
                    ->where('code', 'LOC-0001')
                    ->orWhere('name', 'like', '%apotik%');
            })
            ->orderByRaw("CASE WHEN code = 'LOC-0001' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->first(['id']);

        return $defaultLocation?->id ? (int) $defaultLocation->id : null;
    }

    /**
     * Display the draft and approved stock-opname documents on a dedicated page.
     */
    public function opnameDraftIndex(Request $request): View
    {
        $status = trim((string) $request->string('status', 'all'));
        $status = in_array($status, ['all', 'draft', 'approved'], true) ? $status : 'all';
        $today = now()->toDateString();
        $dateFrom = trim((string) $request->string('date_from', $today));
        $dateTo = trim((string) $request->string('date_to', $today));

        return view('stocks.opname-drafts', [
            ...$this->pageData('stok-batch.stok-opname'),
            'status' => $status,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'recentOpnames' => $this->stockOpnameHistoryRows($status, $dateFrom, $dateTo),
        ]);
    }

    /**
     * Display a stock-opname audit result document.
     */
    public function opnameShow(StockOpname $stockOpname): View
    {
        $stockOpname->load([
            'creator:id,name',
            'approver:id,name',
            'items.medicine:id,code,name,small_unit',
        ]);

        $rows = $stockOpname->items
            ->sortBy(fn (StockOpnameItem $item) => [
                (string) ($item->medicine?->name ?? ''),
                (string) ($item->medicine?->code ?? ''),
            ])
            ->values()
            ->map(function (StockOpnameItem $item): array {
                $difference = round((float) $item->difference_quantity, 2);

                return [
                    'medicine_code' => $item->medicine?->code ?: '-',
                    'medicine_name' => $item->medicine?->name ?: '-',
                    'small_unit' => $item->medicine?->small_unit ?: 'unit',
                    'system_quantity' => (float) $item->system_quantity,
                    'physical_quantity' => (float) $item->physical_quantity,
                    'more_quantity' => $difference > 0 ? $difference : 0,
                    'less_quantity' => $difference < 0 ? abs($difference) : 0,
                    'adjustment_value' => (float) $item->adjustment_value,
                ];
            });

        return view('stocks.opname-show', [
            ...$this->pageData('stok-batch.stok-opname'),
            'stockOpname' => $stockOpname,
            'rows' => $rows,
            'summary' => [
                'item_count' => $rows->count(),
                'total_system' => $this->formatWholeQuantity((float) $rows->sum('system_quantity')),
                'total_physical' => $this->formatWholeQuantity((float) $rows->sum('physical_quantity')),
                'total_more' => $this->formatWholeQuantity((float) $rows->sum('more_quantity')),
                'total_less' => $this->formatWholeQuantity((float) $rows->sum('less_quantity')),
                'total_adjustment' => $this->formatCurrency((float) $rows->sum('adjustment_value')),
            ],
        ]);
    }

    /**
     * Store a stock opname draft document.
     */
    public function opnameStore(Request $request): RedirectResponse
    {
        $requestedOpnameDate = trim((string) $request->input('opname_date', ''));

        if ($requestedOpnameDate !== '' && StockOpname::query()->whereDate('opname_date', $requestedOpnameDate)->exists()) {
            return redirect()
                ->route('stok-batch.stok-opname')
                ->withInput()
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Stok opname hanya bisa dibuat satu kali dalam satu hari.',
                ]);
        }

        $validated = $request->validate([
            'opname_number' => ['required', 'string', 'max:50', 'unique:stock_opnames,opname_number'],
            'opname_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'items.*.stock_batch_id' => ['nullable', 'integer', 'exists:stock_batches,id'],
            'items.*.storage_location_id' => ['nullable', 'integer', 'exists:storage_locations,id'],
            'items.*.system_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.physical_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.average_unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $items = collect($validated['items'])
            ->map(function (array $item): ?array {
                if ($item['physical_quantity'] === null || $item['physical_quantity'] === '') {
                    return null;
                }

                $systemQuantity = round((float) $item['system_quantity'], 2);
                $physicalQuantity = round((float) $item['physical_quantity'], 2);
                $differenceQuantity = round($physicalQuantity - $systemQuantity, 2);

                return [
                    'stock_batch_id' => filled($item['stock_batch_id'] ?? null) ? (int) $item['stock_batch_id'] : null,
                    'medicine_id' => (int) $item['medicine_id'],
                    'storage_location_id' => filled($item['storage_location_id'] ?? null) ? (int) $item['storage_location_id'] : null,
                    'system_quantity' => $systemQuantity,
                    'physical_quantity' => $physicalQuantity,
                    'difference_quantity' => $differenceQuantity,
                    'average_unit_cost' => round((float) ($item['average_unit_cost'] ?? 0), 2),
                ];
            })
            ->filter()
            ->values();

        if ($items->isEmpty()) {
            return back()
                ->withInput()
                ->withErrors([
                    'items' => 'Isi minimal satu stok fisik obat untuk menyimpan draft stok opname.',
                ]);
        }

        DB::transaction(function () use ($validated, $items): void {
            $opname = StockOpname::query()->create([
                'opname_number' => $validated['opname_number'],
                'opname_date' => $validated['opname_date'],
                'status' => 'draft',
                'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
                'created_by' => auth()->id(),
            ]);

            $opname->items()->createMany(
                $items->map(function (array $item): array {
                    $differenceQuantity = (float) $item['difference_quantity'];

                    return [
                        'stock_batch_id' => $item['stock_batch_id'],
                        'medicine_id' => $item['medicine_id'],
                        'storage_location_id' => $item['storage_location_id'],
                        'system_quantity' => $item['system_quantity'],
                        'physical_quantity' => $item['physical_quantity'],
                        'difference_quantity' => $differenceQuantity,
                        'adjustment_value' => round($differenceQuantity * (float) $item['average_unit_cost'], 2),
                    ];
                })->all()
            );
        });

        return redirect()
            ->route('stok-batch.stok-opname')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Draft stok opname berhasil disimpan.',
            ]);
    }

    /**
     * Approve a stock opname draft as an audit document only.
     */
    public function opnameApprove(StockOpname $stockOpname): RedirectResponse
    {
        if ($stockOpname->status !== 'draft') {
            return redirect()
                ->route('stok-batch.stok-opname')
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Dokumen stok opname ini sudah diproses sebelumnya.',
                ]);
        }

        try {
            DB::transaction(function () use ($stockOpname): void {
                $lockedOpname = StockOpname::query()
                    ->lockForUpdate()
                    ->findOrFail($stockOpname->id);

                if ($lockedOpname->status !== 'draft') {
                    throw new RuntimeException('Dokumen stok opname ini sudah diproses sebelumnya.');
                }

                $lockedOpname->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                ]);
            });
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('stok-batch.stok-opname')
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('stok-batch.stok-opname')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Stok opname berhasil di-approve sebagai hasil audit. Stok real belum diubah.',
            ]);
    }

    /**
     * Delete a stock opname document and rollback its effect when still safe.
     */
    public function opnameDestroy(StockOpname $stockOpname): RedirectResponse
    {
        try {
            DB::transaction(function () use ($stockOpname): void {
                $lockedOpname = StockOpname::query()
                    ->lockForUpdate()
                    ->findOrFail($stockOpname->id);

                $lockedOpname->items()->delete();
                $lockedOpname->delete();
            });
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('stok-batch.stok-opname')
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('stok-batch.stok-opname')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Hasil stok opname berhasil dihapus.',
            ]);
    }

    /**
     * Build stock-opname rows grouped per medicine.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function stockOpnameMedicineRows(string $search, ?int $locationId = null): Collection
    {
        return Medicine::query()
            ->with([
                'principal:id,name',
                'stockBatches' => function ($query) use ($locationId): void {
                    $query
                        ->with('storageLocation:id,name')
                        ->when($locationId !== null, fn (Builder $builder) => $builder->where('storage_location_id', $locationId))
                        ->orderBy('expiry_date')
                        ->orderBy('batch_number');
                },
            ])
            ->where('is_active', true)
            ->when($search !== '', function (Builder $builder) use ($search, $locationId): void {
                $builder->where(function (Builder $innerQuery) use ($search, $locationId): void {
                    $innerQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('composition', 'like', "%{$search}%")
                        ->orWhereHas('principal', fn (Builder $principalQuery) => $principalQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('stockBatches', function (Builder $batchQuery) use ($search, $locationId): void {
                            $batchQuery
                                ->when($locationId !== null, fn (Builder $batchBuilder) => $batchBuilder->where('storage_location_id', $locationId))
                                ->where(function (Builder $batchSearchQuery) use ($search): void {
                                    $batchSearchQuery
                                        ->where('batch_number', 'like', "%{$search}%")
                                        ->orWhereHas('storageLocation', fn (Builder $locationQuery) => $locationQuery->where('name', 'like', "%{$search}%"));
                                });
                        });
                });
            })
            ->orderBy('name')
            ->get()
            ->map(function (Medicine $medicine): array {
                /** @var Collection<int, StockBatch> $batches */
                $batches = $medicine->stockBatches;
                /** @var Collection<int, StockBatch> $batches */
                $firstBatch = $batches->first();
                $systemQuantity = round((float) $batches->sum(fn (StockBatch $batch): float => (float) $batch->quantity_balance), 2);
                $masterPurchasePrice = round((float) ($medicine->purchase_price ?? 0), 2);
                $nearestExpiry = $batches
                    ->pluck('expiry_date')
                    ->filter()
                    ->sort()
                    ->first();
                $locationNames = $batches
                    ->map(fn (StockBatch $batch): string => $batch->storageLocation?->name ?: '-')
                    ->filter()
                    ->unique()
                    ->values();
                $batchLabels = $batches
                    ->map(fn (StockBatch $batch): string => trim(($batch->batch_number ?: '-').' ('.$this->formatWholeQuantity((float) $batch->quantity_balance).')'))
                    ->unique()
                    ->values();
                $displayLocationName = $locationNames->isNotEmpty() ? $locationNames->implode(', ') : '-';
                $displayBatchSummary = $batchLabels->isNotEmpty() ? $batchLabels->take(3)->implode(', ') : 'Belum ada batch';

                return [
                    'id' => (int) ($firstBatch?->id ?? 0),
                    'medicine_id' => (int) $medicine->id,
                    'stock_batch_id' => $batches->count() === 1 ? (int) ($firstBatch?->id ?? 0) : null,
                    'storage_location_id' => $locationNames->count() === 1
                        ? ($firstBatch?->storage_location_id ? (int) $firstBatch->storage_location_id : null)
                        : null,
                    'medicine_code' => $medicine->code ?: '-',
                    'medicine_name' => $medicine->name ?: '-',
                    'principal_name' => $medicine->principal?->name ?: '-',
                    'small_unit' => $medicine->small_unit ?: 'unit',
                    'batch_count' => $batches->count(),
                    'batch_summary' => $displayBatchSummary,
                    'batch_summary_more' => max($batchLabels->count() - 3, 0),
                    'expiry_date' => $nearestExpiry?->translatedFormat('d M Y') ?: '-',
                    'expiry_date_value' => $nearestExpiry?->toDateString(),
                    'location_name' => $displayLocationName,
                    'location_names' => $locationNames->isNotEmpty() ? $locationNames->all() : ['-'],
                    'location_count' => $locationNames->filter(fn (string $name): bool => $name !== '-')->count(),
                    'system_quantity' => $systemQuantity,
                    'system_quantity_label' => $this->formatWholeQuantity($systemQuantity),
                    'purchase_price' => $masterPurchasePrice,
                    'purchase_price_label' => $this->formatCurrency($masterPurchasePrice),
                ];
            })
            ->values();
    }

    /**
     * Build the stock data grouped by medicine.
     */
    private function medicineSummaryQuery(string $search, string $stockState = 'all', ?int $locationId = null): Builder
    {
        $totalStockExpression = 'COALESCE(SUM(CASE WHEN stock_batches.quantity_balance > 0 THEN stock_batches.quantity_balance ELSE 0 END), 0)';

        return Medicine::query()
            ->leftJoin('stock_batches', 'stock_batches.medicine_id', '=', 'medicines.id')
            ->leftJoin('principals', 'medicines.principal_id', '=', 'principals.id')
            ->leftJoin('storage_locations', 'stock_batches.storage_location_id', '=', 'storage_locations.id')
            ->where('medicines.is_active', true)
            ->selectRaw('
                medicines.id as medicine_id,
                medicines.code,
                medicines.name,
                medicines.large_unit,
                medicines.small_unit,
                medicines.minimum_stock,
                medicines.is_active,
                principals.name as principal_name,
                COUNT(CASE WHEN stock_batches.quantity_balance > 0 THEN 1 END) as batch_count,
                '.$totalStockExpression.' as total_stock,
                COALESCE(SUM(CASE WHEN stock_batches.quantity_balance > 0 THEN stock_batches.quantity_balance * stock_batches.purchase_price ELSE 0 END), 0) as stock_value,
                MIN(CASE WHEN stock_batches.quantity_balance > 0 THEN stock_batches.expiry_date END) as nearest_expiry
            ')
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('medicines.code', 'like', "%{$search}%")
                        ->orWhere('medicines.name', 'like', "%{$search}%")
                        ->orWhere('principals.name', 'like', "%{$search}%")
                        ->orWhere('stock_batches.batch_number', 'like', "%{$search}%")
                        ->orWhere('storage_locations.name', 'like', "%{$search}%");
                });
            })
            ->when($locationId !== null, fn (Builder $builder) => $builder->where('stock_batches.storage_location_id', $locationId))
            ->when($stockState === 'available', function (Builder $builder) {
                $builder->havingRaw('COALESCE(SUM(CASE WHEN stock_batches.quantity_balance > 0 THEN stock_batches.quantity_balance ELSE 0 END), 0) > 0');
            })
            ->when($stockState === 'empty', function (Builder $builder) {
                $builder->havingRaw('COALESCE(SUM(CASE WHEN stock_batches.quantity_balance > 0 THEN stock_batches.quantity_balance ELSE 0 END), 0) <= 0');
            })
            ->when($stockState === 'low', function (Builder $builder) use ($totalStockExpression) {
                $builder->havingRaw(
                    $totalStockExpression.' > 0 AND COALESCE(medicines.minimum_stock, 0) > 0 AND '
                    .$totalStockExpression.' <= COALESCE(medicines.minimum_stock, 0)'
                );
            })
            ->groupBy(
                'medicines.id',
                'medicines.code',
                'medicines.name',
                'medicines.large_unit',
                'medicines.small_unit',
                'medicines.minimum_stock',
                'medicines.is_active',
                'principals.name',
            )
            ->orderBy('medicines.name');
    }

    /**
     * Build the stock data grouped by batch.
     */
    private function batchBaseQuery(string $search, ?int $locationId = null): Builder
    {
        return StockBatch::query()
            ->where('quantity_balance', '>', 0)
            ->when($locationId !== null, fn (Builder $builder) => $builder->where('storage_location_id', $locationId))
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('batch_number', 'like', "%{$search}%")
                        ->orWhereHas('medicine', function (Builder $medicineQuery) use ($search) {
                            $medicineQuery
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhereHas('principal', fn (Builder $principalQuery) => $principalQuery->where('name', 'like', "%{$search}%"));
                        })
                        ->orWhereHas('storageLocation', fn (Builder $locationQuery) => $locationQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('purchaseInvoiceItem.invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('invoice_number', 'like', "%{$search}%"))
                        ->orWhereHas('purchaseInvoiceItem.invoice.supplier', fn (Builder $supplierQuery) => $supplierQuery->where('name', 'like', "%{$search}%"));
                });
            });
    }

    /**
     * Build the stock opname batch query.
     */
    private function stockOpnameBatchQuery(string $search, ?int $locationId = null): Builder
    {
        return StockBatch::query()
            ->with([
                'medicine:id,code,name,small_unit,principal_id',
                'medicine.principal:id,name',
                'storageLocation:id,name',
            ])
            ->leftJoin('medicines', 'medicines.id', '=', 'stock_batches.medicine_id')
            ->when($locationId !== null, fn (Builder $builder) => $builder->where('stock_batches.storage_location_id', $locationId))
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('stock_batches.batch_number', 'like', "%{$search}%")
                        ->orWhere('medicines.code', 'like', "%{$search}%")
                        ->orWhere('medicines.name', 'like', "%{$search}%")
                        ->orWhereHas('medicine.principal', fn (Builder $principalQuery) => $principalQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('storageLocation', fn (Builder $locationQuery) => $locationQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->select('stock_batches.*');
    }

    /**
     * Build grouped stock rows per medicine and batch number.
     */
    private function groupedBatchRows(string $search, ?int $expiryWithinMonths = null, ?int $locationId = null): Collection
    {
        $rows = $this->batchBaseQuery($search, $locationId)
            ->with($this->batchRelations())
            ->orderBy('expiry_date')
            ->orderByDesc('received_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (StockBatch $batch): string => $this->batchGroupKey($batch))
            ->map(function (Collection $batches, string $groupKey): object {
                $firstBatch = $batches->first();
                $quantityBalance = (float) $batches->sum(fn (StockBatch $batch): float => (float) $batch->quantity_balance);
                $stockValue = (float) $batches->sum(fn (StockBatch $batch): float => (float) $batch->quantity_balance * (float) $batch->purchase_price);
                $invoiceNumbers = $batches
                    ->map(fn (StockBatch $batch): ?string => $batch->purchaseInvoiceItem?->invoice?->invoice_number)
                    ->filter()
                    ->unique()
                    ->values();

                return (object) [
                    'row_key' => $groupKey,
                    'medicine_code' => $firstBatch?->medicine?->code ?: '-',
                    'medicine_name' => $firstBatch?->medicine?->name ?: '-',
                    'principal_name' => $firstBatch?->medicine?->principal?->name ?: '-',
                    'batch_number' => $firstBatch?->batch_number ?: '-',
                    'invoice_label' => $invoiceNumbers->count() <= 1
                        ? ($invoiceNumbers->first() ?: '-')
                        : $invoiceNumbers->count().' faktur',
                    'supplier_label' => $this->collapseLabels(
                        $batches->map(fn (StockBatch $batch): ?string => $batch->purchaseInvoiceItem?->invoice?->supplier?->name),
                        'supplier'
                    ),
                    'location_label' => $this->collapseLabels(
                        $batches->map(fn (StockBatch $batch): ?string => $batch->storageLocation?->name),
                        'lokasi'
                    ),
                    'received_at_label' => $this->collapseDates(
                        $batches->map(fn (StockBatch $batch) => $batch->received_at)
                    ),
                    'expiry_date_label' => $this->collapseDates(
                        $batches->map(fn (StockBatch $batch) => $batch->expiry_date)
                    ),
                    'quantity_balance' => $quantityBalance,
                    'stock_value' => $stockValue,
                    'sort_name' => Str::lower((string) ($firstBatch?->medicine?->name ?: '')),
                    'sort_batch' => Str::lower((string) ($firstBatch?->batch_number ?: '')),
                    'sort_expiry' => $batches
                        ->pluck('expiry_date')
                        ->filter()
                        ->map(fn ($date) => $date?->toDateString())
                        ->sort()
                        ->first() ?: '9999-12-31',
                ];
            })
            ->sortBy(fn (object $row): string => implode('|', [
                $row->sort_expiry,
                $row->sort_name,
                $row->sort_batch,
            ]))
            ->values();

        if ($expiryWithinMonths === null) {
            return $rows;
        }

        $today = now()->startOfDay();
        $cutoff = now()->startOfDay()->addMonths($expiryWithinMonths);

        return $rows
            ->filter(function (object $row) use ($today, $cutoff): bool {
                if (($row->sort_expiry ?? '9999-12-31') === '9999-12-31') {
                    return false;
                }

                $expiryDate = Carbon::parse((string) $row->sort_expiry)->startOfDay();

                return $expiryDate->greaterThanOrEqualTo($today)
                    && $expiryDate->lessThanOrEqualTo($cutoff);
            })
            ->values();
    }

    /**
     * Normalize the batch expiry-window filter.
     */
    private function normalizeExpiryWithinMonths(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $months = (int) $value;

        return $months >= 1 && $months <= 12 ? $months : null;
    }

    /**
     * Get active storage-location options for stock filters.
     */
    private function activeLocationOptions(): Collection
    {
        return StorageLocation::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Build detail payloads for medicine rows.
     *
     * @param  Collection<int, int|string>  $medicineIds
     * @return array<int, array<string, mixed>>
     */
    private function medicineDetailPayloads(Collection $medicineIds, ?int $locationId = null): array
    {
        if ($medicineIds->isEmpty()) {
            return [];
        }

        $movementPayloads = $this->medicineMovementPayloads($medicineIds, $locationId);
        $medicines = Medicine::query()
            ->with('principal:id,name')
            ->whereIn('id', $medicineIds->all())
            ->get()
            ->keyBy('id');
        $activeBatchesByMedicine = $this->batchBaseQuery('', $locationId)
            ->with([
                'medicine:id,code,name,large_unit,small_unit,principal_id',
                'medicine.principal:id,name',
                'storageLocation:id,name',
                'purchaseInvoiceItem:id,purchase_invoice_id',
                'purchaseInvoiceItem.invoice:id,invoice_number,invoice_date,supplier_id',
                'purchaseInvoiceItem.invoice.supplier:id,name',
            ])
            ->whereIn('medicine_id', $medicineIds->all())
            ->orderBy('expiry_date')
            ->orderBy('batch_number')
            ->get()
            ->groupBy('medicine_id');

        return $medicineIds
            ->mapWithKeys(function ($medicineId) use ($medicines, $activeBatchesByMedicine, $movementPayloads): array {
                /** @var Medicine|null $medicine */
                $medicine = $medicines->get((int) $medicineId);
                /** @var Collection<int, StockBatch> $batches */
                $batches = $activeBatchesByMedicine->get((int) $medicineId, collect());
                $totalStock = $batches->sum(fn (StockBatch $batch): float => (float) $batch->quantity_balance);
                $stockValue = $batches->sum(fn (StockBatch $batch): float => (float) $batch->quantity_balance * (float) $batch->purchase_price);
                $nearestExpiry = optional($batches->sortBy('expiry_date')->first())->expiry_date?->translatedFormat('d M Y') ?? '-';
                $smallUnit = $medicine?->small_unit ?: 'unit';

                return [
                    (int) $medicineId => [
                        'type' => 'medicine',
                        'code' => $medicine?->code ?: '-',
                        'name' => $medicine?->name ?: '-',
                        'principal_name' => $medicine?->principal?->name ?: '-',
                        'large_unit' => $medicine?->large_unit ?: '-',
                        'small_unit' => $smallUnit,
                        'batch_count' => $batches->count(),
                        'total_stock' => $this->formatWholeQuantity($totalStock),
                        'total_stock_label' => $this->formatWholeQuantity($totalStock).' '.$smallUnit,
                        'stock_value' => $this->formatCurrency($stockValue),
                        'nearest_expiry' => $nearestExpiry,
                        'movement_count' => count($movementPayloads[(int) $medicineId] ?? []),
                        'movements' => $movementPayloads[(int) $medicineId] ?? [],
                        'batches' => $batches->map(function (StockBatch $batch): array {
                            $balance = (float) $batch->quantity_balance;
                            $value = $balance * (float) $batch->purchase_price;

                            return [
                                'id' => $batch->id,
                                'batch_number' => $batch->batch_number ?: '-',
                                'invoice_number' => $batch->purchaseInvoiceItem?->invoice?->invoice_number ?: '-',
                                'supplier' => $batch->purchaseInvoiceItem?->invoice?->supplier?->name ?: '-',
                                'location' => $batch->storageLocation?->name ?: '-',
                                'received_at' => $batch->received_at?->translatedFormat('d M Y') ?? '-',
                                'expiry_date' => $batch->expiry_date?->translatedFormat('d M Y') ?? '-',
                                'quantity_balance' => $this->formatWholeQuantity($balance),
                                'stock_value' => $this->formatCurrency($value),
                            ];
                        })->values()->all(),
                    ],
                ];
            })
            ->all();
    }

    /**
     * Build stock-card movement history grouped by medicine code.
     *
     * @param  Collection<int, int|string>  $medicineIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function medicineMovementPayloads(Collection $medicineIds, ?int $locationId = null): array
    {
        $movements = StockMovement::query()
            ->with([
                'stockBatch:id,purchase_invoice_item_id,batch_number',
                'stockBatch.purchaseInvoiceItem:id,purchase_invoice_id',
                'stockBatch.purchaseInvoiceItem.invoice:id,invoice_number,supplier_id',
                'stockBatch.purchaseInvoiceItem.invoice.supplier:id,name',
                'storageLocation:id,name',
            ])
            ->whereIn('medicine_id', $medicineIds->all())
            ->when($locationId !== null, fn (Builder $builder) => $builder->where('storage_location_id', $locationId))
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();

        $purchaseReturnReferences = PurchaseReturnItem::query()
            ->with([
                'purchaseReturn:id,return_number,purchase_invoice_id',
                'purchaseReturn.purchaseInvoice:id,invoice_number',
            ])
            ->whereIn('id', $movements
                ->where('reference_table', 'purchase_return_items')
                ->pluck('reference_id')
                ->filter()
                ->unique()
                ->values()
                ->all())
            ->get()
            ->keyBy('id');

        $purchaseExchangeReferences = PurchaseExchangeItem::query()
            ->with([
                'purchaseExchange:id,exchange_number,purchase_invoice_id',
                'purchaseExchange.purchaseInvoice:id,invoice_number',
            ])
            ->whereIn('id', $movements
                ->where('reference_table', 'purchase_exchange_items')
                ->pluck('reference_id')
                ->filter()
                ->unique()
                ->values()
                ->all())
            ->get()
            ->keyBy('id');

        $replacementReferences = PurchaseReturnReplacementItem::query()
            ->with([
                'purchaseReturnReplacement:id,replacement_number,purchase_return_id',
                'purchaseReturnReplacement.purchaseReturn:id,return_number,purchase_invoice_id',
                'purchaseReturnReplacement.purchaseReturn.purchaseInvoice:id,invoice_number',
            ])
            ->whereIn('id', $movements
                ->where('reference_table', 'purchase_return_replacement_items')
                ->pluck('reference_id')
                ->filter()
                ->unique()
                ->values()
                ->all())
            ->get()
            ->keyBy('id');

        $exchangeReplacementReferences = PurchaseExchangeReplacementItem::query()
            ->with([
                'purchaseExchangeReplacement:id,exchange_replacement_number,purchase_exchange_id',
                'purchaseExchangeReplacement.purchaseExchange:id,exchange_number,purchase_invoice_id',
                'purchaseExchangeReplacement.purchaseExchange.purchaseInvoice:id,invoice_number',
            ])
            ->whereIn('id', $movements
                ->where('reference_table', 'purchase_exchange_replacement_items')
                ->pluck('reference_id')
                ->filter()
                ->unique()
                ->values()
                ->all())
            ->get()
            ->keyBy('id');

        $saleReferences = SaleItem::query()
            ->with([
                'sale:id,sale_number,customer_name',
            ])
            ->whereIn('id', $movements
                ->where('reference_table', 'sale_items')
                ->pluck('reference_id')
                ->filter()
                ->unique()
                ->values()
                ->all())
            ->get()
            ->keyBy('id');

        $saleReturnReferences = SaleReturnItem::query()
            ->with([
                'saleReturn:id,return_number,sale_id',
                'saleReturn.sale:id,sale_number,customer_name',
            ])
            ->whereIn('id', $movements
                ->where('reference_table', 'sale_return_items')
                ->pluck('reference_id')
                ->filter()
                ->unique()
                ->values()
                ->all())
            ->get()
            ->keyBy('id');

        $stockOpnameReferences = StockOpnameItem::query()
            ->with([
                'stockOpname:id,opname_number,opname_date,status',
            ])
            ->whereIn('id', $movements
                ->where('reference_table', 'stock_opname_items')
                ->pluck('reference_id')
                ->filter()
                ->unique()
                ->values()
                ->all())
            ->get()
            ->keyBy('id');

        return $movements
            ->groupBy('medicine_id')
            ->map(function (Collection $movements) use ($purchaseReturnReferences, $purchaseExchangeReferences, $replacementReferences, $exchangeReplacementReferences, $saleReferences, $saleReturnReferences, $stockOpnameReferences): array {
                $runningBalance = 0.0;

                return $movements->map(function (StockMovement $movement) use (&$runningBalance, $purchaseReturnReferences, $purchaseExchangeReferences, $replacementReferences, $exchangeReplacementReferences, $saleReferences, $saleReturnReferences, $stockOpnameReferences): array {
                    $quantityIn = (float) $movement->quantity_in;
                    $quantityOut = (float) $movement->quantity_out;
                    $runningBalance += $quantityIn - $quantityOut;
                    $meta = $this->stockMovementMeta($movement);
                    $reference = $this->stockMovementReference($movement, $purchaseReturnReferences, $purchaseExchangeReferences, $replacementReferences, $exchangeReplacementReferences, $saleReferences, $saleReturnReferences, $stockOpnameReferences);
                    $status = $this->stockMovementStatus($movement);

                    return [
                        'id' => $movement->id,
                        'movement_date' => $movement->movement_date?->translatedFormat('d M Y H:i') ?? '-',
                        'movement_date_value' => $movement->movement_date?->toDateString() ?? '',
                        'type_label' => $meta['label'],
                        'type_class' => $meta['class'],
                        'status_label' => $status['label'],
                        'status_class' => $status['class'],
                        'batch_number' => $movement->stockBatch?->batch_number ?: '-',
                        'reference' => $reference['code'],
                        'reference_detail' => $reference['detail'],
                        'location' => $movement->storageLocation?->name ?: '-',
                        'quantity_in' => $quantityIn > 0 ? $this->formatWholeQuantity($quantityIn) : '-',
                        'quantity_in_value' => $quantityIn,
                        'quantity_out' => $quantityOut > 0 ? $this->formatWholeQuantity($quantityOut) : '-',
                        'quantity_out_value' => $quantityOut,
                        'running_balance' => $this->formatWholeQuantity($runningBalance),
                        'running_balance_value' => $runningBalance,
                    ];
                })->values()->all();
            })
            ->all();
    }

    /**
     * Build detail payloads for batch rows.
     *
     * @param  LengthAwarePaginator<int, StockBatch>  $batchRows
     * @return array<int, array<string, mixed>>
     */
    private function batchDetailPayloads(Collection $batchRows, ?int $locationId = null): array
    {
        $groupKeys = $batchRows
            ->pluck('row_key')
            ->filter()
            ->values();

        $batchesByGroup = $this->batchBaseQuery('', $locationId)
            ->with($this->batchRelations())
            ->get()
            ->groupBy(fn (StockBatch $batch): string => $this->batchGroupKey($batch));

        return $groupKeys
            ->mapWithKeys(function (string $groupKey) use ($batchesByGroup): array {
                /** @var Collection<int, StockBatch> $batches */
                $batches = $batchesByGroup->get($groupKey, collect());
                /** @var StockBatch|null $firstBatch */
                $firstBatch = $batches->first();
                $balance = (float) $batches->sum(fn (StockBatch $batch): float => (float) $batch->quantity_balance);
                $quantityIn = (float) $batches->sum(fn (StockBatch $batch): float => (float) $batch->quantity_in);
                $quantityOut = (float) $batches->sum(fn (StockBatch $batch): float => (float) $batch->quantity_out);
                $stockValue = (float) $batches->sum(fn (StockBatch $batch): float => (float) $batch->quantity_balance * (float) $batch->purchase_price);
                $weightedPurchasePrice = $balance > 0
                    ? round($stockValue / $balance, 2)
                    : round((float) ($firstBatch?->purchase_price ?? 0), 2);
                $invoiceNumbers = $batches
                    ->map(fn (StockBatch $batch): ?string => $batch->purchaseInvoiceItem?->invoice?->invoice_number)
                    ->filter()
                    ->unique()
                    ->values();
                $invoiceDates = $batches
                    ->map(fn (StockBatch $batch) => $batch->purchaseInvoiceItem?->invoice?->invoice_date)
                    ->filter()
                    ->values();

                return [
                    $groupKey => [
                        'type' => 'batch',
                        'code' => $firstBatch?->medicine?->code ?: '-',
                        'name' => $firstBatch?->medicine?->name ?: '-',
                        'principal_name' => $firstBatch?->medicine?->principal?->name ?: '-',
                        'batch_number' => $firstBatch?->batch_number ?: '-',
                        'invoice_number' => $invoiceNumbers->count() <= 1
                            ? ($invoiceNumbers->first() ?: '-')
                            : $invoiceNumbers->count().' faktur',
                        'invoice_date' => $this->collapseDates($invoiceDates),
                        'supplier' => $this->collapseLabels(
                            $batches->map(fn (StockBatch $batch): ?string => $batch->purchaseInvoiceItem?->invoice?->supplier?->name),
                            'supplier'
                        ),
                        'location' => $this->collapseLabels(
                            $batches->map(fn (StockBatch $batch): ?string => $batch->storageLocation?->name),
                            'lokasi'
                        ),
                        'received_at' => $this->collapseDates(
                            $batches->map(fn (StockBatch $batch) => $batch->received_at)
                        ),
                        'expiry_date' => $this->collapseDates(
                            $batches->map(fn (StockBatch $batch) => $batch->expiry_date)
                        ),
                        'quantity_in' => $this->formatWholeQuantity($quantityIn),
                        'quantity_out' => $this->formatWholeQuantity($quantityOut),
                        'quantity_balance' => $this->formatWholeQuantity($balance),
                        'purchase_price' => $this->formatCurrency($weightedPurchasePrice),
                        'stock_value' => $this->formatCurrency($stockValue),
                    ],
                ];
            })
            ->all();
    }

    /**
     * Get eager-load relations for batch pages.
     *
     * @return array<int, string>
     */
    private function batchRelations(): array
    {
        return [
            'medicine:id,code,name,large_unit,small_unit,principal_id',
            'medicine.principal:id,name',
            'storageLocation:id,name',
            'purchaseInvoiceItem:id,purchase_invoice_id',
            'purchaseInvoiceItem.invoice:id,invoice_number,invoice_date,supplier_id',
            'purchaseInvoiceItem.invoice.supplier:id,name',
        ];
    }

    /**
     * Resolve a stable grouping key for batch rows.
     */
    private function batchGroupKey(StockBatch $batch): string
    {
        return implode('|', [
            (int) $batch->medicine_id,
            Str::upper(trim((string) $batch->batch_number)),
        ]);
    }

    /**
     * Collapse many labels into a single summary label.
     */
    private function collapseLabels(Collection $labels, string $groupLabel): string
    {
        $uniqueLabels = $labels
            ->filter(fn ($label): bool => filled($label))
            ->map(fn ($label): string => trim((string) $label))
            ->unique()
            ->values();

        if ($uniqueLabels->isEmpty()) {
            return '-';
        }

        if ($uniqueLabels->count() === 1) {
            return $uniqueLabels->first();
        }

        return $uniqueLabels->count().' '.$groupLabel;
    }

    /**
     * Collapse many dates into one readable date or range.
     */
    private function collapseDates(Collection $dates): string
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
            return optional($dates->first())->translatedFormat('d M Y') ?? '-';
        }

        $firstDate = $dates
            ->filter(fn ($date) => $date?->toDateString() === $uniqueDates->first())
            ->first();
        $lastDate = $dates
            ->filter(fn ($date) => $date?->toDateString() === $uniqueDates->last())
            ->first();

        return ($firstDate?->translatedFormat('d M Y') ?? $uniqueDates->first())
            .' s.d. '
            .($lastDate?->translatedFormat('d M Y') ?? $uniqueDates->last());
    }

    /**
     * Resolve the display meta for a stock movement row.
     *
     * @return array{label: string, class: string}
     */
    private function stockMovementMeta(StockMovement $movement): array
    {
        return match ($movement->movement_type) {
            'purchase_receipt' => [
                'label' => 'Faktur Pembelian',
                'class' => 'border-emerald-100 bg-emerald-50 text-emerald-700',
            ],
            'purchase_return' => [
                'label' => 'Retur Pembelian',
                'class' => 'border-amber-100 bg-amber-50 text-amber-700',
            ],
            'purchase_exchange' => [
                'label' => 'Tukar Barang',
                'class' => 'border-orange-100 bg-orange-50 text-orange-700',
            ],
            'purchase_return_replacement' => [
                'label' => 'Pengganti Retur',
                'class' => 'border-teal-100 bg-teal-50 text-teal-700',
            ],
            'purchase_exchange_replacement' => [
                'label' => 'Realisasi Tukar Barang',
                'class' => 'border-cyan-100 bg-cyan-50 text-cyan-700',
            ],
            'stock_opname_gain' => [
                'label' => 'Stok Opname Lebih',
                'class' => 'border-sky-100 bg-sky-50 text-sky-700',
            ],
            'stock_opname_loss' => [
                'label' => 'Stok Opname Hilang',
                'class' => 'border-rose-100 bg-rose-50 text-rose-700',
            ],
            'sale' => [
                'label' => 'Penjualan',
                'class' => 'border-rose-100 bg-rose-50 text-rose-700',
            ],
            'sale_return' => [
                'label' => 'Retur Penjualan',
                'class' => 'border-sky-100 bg-sky-50 text-sky-700',
            ],
            default => [
                'label' => Str::headline(str_replace('_', ' ', $movement->movement_type)),
                'class' => 'border-slate-200 bg-slate-50 text-slate-700',
            ],
        };
    }

    /**
     * Resolve movement status as incoming or outgoing history.
     *
     * @return array{label: string, class: string}
     */
    private function stockMovementStatus(StockMovement $movement): array
    {
        if ((float) $movement->quantity_in > 0 && (float) $movement->quantity_out <= 0) {
            return [
                'label' => 'Masuk',
                'class' => 'border-emerald-100 bg-emerald-50 text-emerald-700',
            ];
        }

        if ((float) $movement->quantity_out > 0 && (float) $movement->quantity_in <= 0) {
            return [
                'label' => 'Keluar',
                'class' => 'border-rose-100 bg-rose-50 text-rose-700',
            ];
        }

        return [
            'label' => 'Mutasi',
            'class' => 'border-slate-200 bg-slate-50 text-slate-700',
        ];
    }

    /**
     * Resolve transaction reference for a stock movement row.
     *
     * @param  Collection<int, PurchaseReturnItem>  $purchaseReturnReferences
     * @param  Collection<int, PurchaseExchangeItem>  $purchaseExchangeReferences
     * @param  Collection<int, PurchaseReturnReplacementItem>  $replacementReferences
     * @param  Collection<int, PurchaseExchangeReplacementItem>  $exchangeReplacementReferences
     * @param  Collection<int, SaleItem>  $saleReferences
     * @param  Collection<int, SaleReturnItem>  $saleReturnReferences
     * @param  Collection<int, StockOpnameItem>  $stockOpnameReferences
     * @return array{code: string, detail: string}
     */
    private function stockMovementReference(
        StockMovement $movement,
        Collection $purchaseReturnReferences,
        Collection $purchaseExchangeReferences,
        Collection $replacementReferences,
        Collection $exchangeReplacementReferences,
        Collection $saleReferences,
        Collection $saleReturnReferences,
        Collection $stockOpnameReferences
    ): array
    {
        $invoiceNumber = $movement->stockBatch?->purchaseInvoiceItem?->invoice?->invoice_number;
        $supplierName = $movement->stockBatch?->purchaseInvoiceItem?->invoice?->supplier?->name;

        if ($movement->reference_table === 'purchase_return_items') {
            /** @var PurchaseReturnItem|null $returnItem */
            $returnItem = $purchaseReturnReferences->get((int) $movement->reference_id);
            $returnNumber = $returnItem?->purchaseReturn?->return_number;
            $returnInvoiceNumber = $returnItem?->purchaseReturn?->purchaseInvoice?->invoice_number;

            return [
                'code' => $returnNumber ?: 'Retur Pembelian',
                'detail' => $returnInvoiceNumber
                    ? 'Faktur '.$returnInvoiceNumber
                    : ($supplierName ?: ($movement->notes ?: '-')),
            ];
        }

        if ($movement->reference_table === 'purchase_exchange_items') {
            /** @var PurchaseExchangeItem|null $exchangeItem */
            $exchangeItem = $purchaseExchangeReferences->get((int) $movement->reference_id);
            $exchangeNumber = $exchangeItem?->purchaseExchange?->exchange_number;
            $exchangeInvoiceNumber = $exchangeItem?->purchaseExchange?->purchaseInvoice?->invoice_number;

            return [
                'code' => $exchangeNumber ?: 'Tukar Barang',
                'detail' => $exchangeInvoiceNumber
                    ? 'Faktur '.$exchangeInvoiceNumber
                    : ($supplierName ?: ($movement->notes ?: '-')),
            ];
        }

        if ($movement->reference_table === 'purchase_return_replacement_items') {
            /** @var PurchaseReturnReplacementItem|null $replacementItem */
            $replacementItem = $replacementReferences->get((int) $movement->reference_id);
            $replacementNumber = $replacementItem?->purchaseReturnReplacement?->replacement_number;
            $returnNumber = $replacementItem?->purchaseReturnReplacement?->purchaseReturn?->return_number;

            return [
                'code' => $replacementNumber ?: 'Pengganti Retur',
                'detail' => $returnNumber
                    ? 'Retur '.$returnNumber
                    : ($supplierName ?: ($movement->notes ?: '-')),
            ];
        }

        if ($movement->reference_table === 'purchase_exchange_replacement_items') {
            /** @var PurchaseExchangeReplacementItem|null $replacementItem */
            $replacementItem = $exchangeReplacementReferences->get((int) $movement->reference_id);
            $replacementNumber = $replacementItem?->purchaseExchangeReplacement?->exchange_replacement_number;
            $exchangeNumber = $replacementItem?->purchaseExchangeReplacement?->purchaseExchange?->exchange_number;

            return [
                'code' => $replacementNumber ?: 'Realisasi Tukar Barang',
                'detail' => $exchangeNumber
                    ? 'Tukar '.$exchangeNumber
                    : ($supplierName ?: ($movement->notes ?: '-')),
            ];
        }

        if ($movement->reference_table === 'sale_items') {
            /** @var SaleItem|null $saleItem */
            $saleItem = $saleReferences->get((int) $movement->reference_id);
            $saleNumber = $saleItem?->sale?->sale_number;
            $customerName = $saleItem?->sale?->customer_name;

            return [
                'code' => $saleNumber ?: 'Penjualan',
                'detail' => $customerName ?: ($movement->notes ?: '-'),
            ];
        }

        if ($movement->reference_table === 'sale_return_items') {
            /** @var SaleReturnItem|null $saleReturnItem */
            $saleReturnItem = $saleReturnReferences->get((int) $movement->reference_id);
            $returnNumber = $saleReturnItem?->saleReturn?->return_number;
            $saleNumber = $saleReturnItem?->saleReturn?->sale?->sale_number;
            $customerName = $saleReturnItem?->saleReturn?->sale?->customer_name;

            return [
                'code' => $returnNumber ?: 'Retur Penjualan',
                'detail' => $saleNumber
                    ? 'Penjualan '.$saleNumber.($customerName ? ' / '.$customerName : '')
                    : ($movement->notes ?: '-'),
            ];
        }

        if ($movement->reference_table === 'stock_opname_items') {
            /** @var StockOpnameItem|null $opnameItem */
            $opnameItem = $stockOpnameReferences->get((int) $movement->reference_id);
            $opnameNumber = $opnameItem?->stockOpname?->opname_number;
            $opnameDate = $opnameItem?->stockOpname?->opname_date?->translatedFormat('d M Y');

            return [
                'code' => $opnameNumber ?: 'Stok Opname',
                'detail' => $opnameDate
                    ? 'Penyesuaian stok '.$opnameDate
                    : ($movement->notes ?: 'Penyesuaian stok opname'),
            ];
        }

        if ($invoiceNumber !== null) {
            return [
                'code' => $invoiceNumber,
                'detail' => $supplierName ?: ($movement->notes ?: '-'),
            ];
        }

        return [
            'code' => $movement->reference_table ? Str::headline(str_replace('_', ' ', $movement->reference_table)) : '-',
            'detail' => $movement->notes ?: '-',
        ];
    }

    /**
     * Build the page metadata for the stock module.
     *
     * @return array<string, mixed>
     */
    private function pageData(string $routeName): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $item): bool => $item['label'] === 'Stok & Batch');

        $siblings = $section['children'] ?? [];
        $page = collect($siblings)->firstWhere('route', $routeName);

        return [
            'page' => $page,
            'section' => $section['label'] ?? 'Stok & Batch',
            'siblings' => $siblings,
        ];
    }

    /**
     * Format stock totals without decimal places.
     */
    private function formatWholeQuantity(float $quantity): string
    {
        return number_format($quantity, 0, ',', '.');
    }

    /**
     * Format currency values for stock displays.
     */
    private function formatCurrency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    /**
     * Build stock-opname history rows for listing pages.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function stockOpnameHistoryRows(string $status = 'all', string $dateFrom = '', string $dateTo = ''): Collection
    {
        return StockOpname::query()
            ->withCount('items')
            ->with(['creator:id,name'])
            ->when($status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when($dateFrom !== '', fn (Builder $builder) => $builder->whereDate('opname_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn (Builder $builder) => $builder->whereDate('opname_date', '<=', $dateTo))
            ->latest('opname_date')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function (StockOpname $opname): array {
                $totals = StockOpnameItem::query()
                    ->where('stock_opname_id', $opname->id)
                    ->selectRaw('
                        COALESCE(SUM(CASE WHEN difference_quantity > 0 THEN difference_quantity ELSE 0 END), 0) as total_more,
                        COALESCE(SUM(CASE WHEN difference_quantity < 0 THEN ABS(difference_quantity) ELSE 0 END), 0) as total_less
                    ')
                    ->first();

                return [
                    'id' => $opname->id,
                    'number' => $opname->opname_number,
                    'date' => $opname->opname_date?->translatedFormat('d M Y') ?: '-',
                    'status' => $opname->status,
                    'item_count' => (int) $opname->items_count,
                    'created_by' => $opname->creator?->name ?: '-',
                    'total_more' => $this->formatWholeQuantity((float) ($totals?->total_more ?? 0)),
                    'total_less' => $this->formatWholeQuantity((float) ($totals?->total_less ?? 0)),
                ];
            });
    }

    /**
     * Collapse follow-up batch rows by batch number.
     *
     * @param  Collection<int, StockBatch>  $batchRows
     * @return Collection<int, object>
     */
    private function groupFollowUpBatchRows(Collection $batchRows): Collection
    {
        return $batchRows
            ->groupBy(fn (StockBatch $batch): string => trim((string) $batch->batch_number) !== '' ? trim((string) $batch->batch_number) : '-')
            ->map(function (Collection $batches, string $batchNumber): object {
                $locations = $batches
                    ->map(fn (StockBatch $batch): string => trim((string) ($batch->storageLocation?->name ?? '')))
                    ->filter()
                    ->unique()
                    ->values();

                $totalBalance = round((float) $batches->sum(fn (StockBatch $batch) => (float) $batch->quantity_balance), 2);
                $weightedCost = $totalBalance > 0
                    ? round(
                        (float) $batches->sum(
                            fn (StockBatch $batch): float => (float) $batch->quantity_balance * (float) $batch->purchase_price
                        ) / $totalBalance,
                        2
                    )
                    : round((float) ($batches->first()?->purchase_price ?? 0), 2);

                return (object) [
                    'key' => $batchNumber,
                    'batch_number' => $batchNumber,
                    'location_label' => $locations->isNotEmpty() ? $locations->implode(', ') : '-',
                    'quantity_balance' => $totalBalance,
                    'purchase_price' => $weightedCost,
                ];
            })
            ->sortBy('batch_number', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Apply one follow-up batch result to the underlying stock-batch rows.
     *
     * @param  Collection<int, StockBatch>  $groupedBatches
     */
    private function applyFollowUpBatchAdjustment(
        StockOpnameItem $stockOpnameItem,
        StockAdjustmentFollowUp $followUp,
        Collection $groupedBatches,
        float $targetPhysicalQuantity,
    ): void {
        $targetPhysicalQuantity = round($targetPhysicalQuantity, 2);
        $currentSystemQuantity = round((float) $groupedBatches->sum(fn (StockBatch $batch): float => (float) $batch->quantity_balance), 2);
        $differenceQuantity = round($targetPhysicalQuantity - $currentSystemQuantity, 2);

        if (abs($differenceQuantity) < 0.001) {
            return;
        }

        $adjustmentMoment = $followUp->adjustment_date
            ? $followUp->adjustment_date->copy()->setTime(now()->hour, now()->minute, now()->second)
            : now();
        $userId = auth()->id();
        $opnameNumber = $stockOpnameItem->stockOpname?->opname_number ?: 'Stok Opname';
        $adjustmentNumber = $followUp->adjustment_number;

        if ($differenceQuantity < 0) {
            $remainingToDeduct = abs($differenceQuantity);

            foreach ($groupedBatches->sortByDesc('id') as $stockBatch) {
                $availableQuantity = round((float) $stockBatch->quantity_balance, 2);
                $deductQuantity = round(min($availableQuantity, $remainingToDeduct), 2);

                if ($deductQuantity <= 0) {
                    continue;
                }

                $newQuantityOut = round((float) $stockBatch->quantity_out + $deductQuantity, 2);
                $newBalance = round($availableQuantity - $deductQuantity, 2);

                $stockBatch->update([
                    'quantity_out' => $newQuantityOut,
                    'quantity_balance' => $newBalance,
                    'status' => $newBalance > 0 ? 'active' : 'empty',
                    'notes' => $this->appendFollowUpNote(
                        existingNotes: (string) $stockBatch->notes,
                        adjustmentNumber: $adjustmentNumber,
                        opnameNumber: $opnameNumber,
                        movementLabel: 'kurang',
                        quantity: $deductQuantity,
                    ),
                ]);

                StockMovement::query()->create([
                    'movement_date' => $adjustmentMoment,
                    'movement_type' => 'stock_opname_loss',
                    'reference_table' => 'stock_opname_items',
                    'reference_id' => $stockOpnameItem->id,
                    'medicine_id' => $stockOpnameItem->medicine_id,
                    'stock_batch_id' => $stockBatch->id,
                    'storage_location_id' => $stockBatch->storage_location_id,
                    'quantity_in' => 0,
                    'quantity_out' => $deductQuantity,
                    'balance_after' => $newBalance,
                    'unit_cost' => $stockBatch->purchase_price,
                    'notes' => 'Tindak lanjut '.$adjustmentNumber.' dari '.$opnameNumber.' batch '.$stockBatch->batch_number,
                    'created_by' => $userId,
                ]);

                $remainingToDeduct = round($remainingToDeduct - $deductQuantity, 2);

                if ($remainingToDeduct <= 0.001) {
                    break;
                }
            }

            if ($remainingToDeduct > 0.001) {
                throw new RuntimeException('Stok batch untuk tindak lanjut ini berubah. Silakan cek ulang saldo batch sebelum menyimpan.');
            }

            return;
        }

        /** @var StockBatch|null $targetBatch */
        $targetBatch = $groupedBatches->sortBy('id')->first();

        if ($targetBatch === null) {
            throw new RuntimeException('Batch tujuan untuk penyesuaian stok tidak ditemukan.');
        }

        $newQuantityIn = round((float) $targetBatch->quantity_in + $differenceQuantity, 2);
        $newBalance = round((float) $targetBatch->quantity_balance + $differenceQuantity, 2);

        $targetBatch->update([
            'quantity_in' => $newQuantityIn,
            'quantity_balance' => $newBalance,
            'status' => $newBalance > 0 ? 'active' : 'empty',
            'notes' => $this->appendFollowUpNote(
                existingNotes: (string) $targetBatch->notes,
                adjustmentNumber: $adjustmentNumber,
                opnameNumber: $opnameNumber,
                movementLabel: 'tambah',
                quantity: $differenceQuantity,
            ),
        ]);

        StockMovement::query()->create([
            'movement_date' => $adjustmentMoment,
            'movement_type' => 'stock_opname_gain',
            'reference_table' => 'stock_opname_items',
            'reference_id' => $stockOpnameItem->id,
            'medicine_id' => $stockOpnameItem->medicine_id,
            'stock_batch_id' => $targetBatch->id,
            'storage_location_id' => $targetBatch->storage_location_id,
            'quantity_in' => $differenceQuantity,
            'quantity_out' => 0,
            'balance_after' => $newBalance,
            'unit_cost' => $targetBatch->purchase_price,
            'notes' => 'Tindak lanjut '.$adjustmentNumber.' dari '.$opnameNumber.' batch '.$targetBatch->batch_number,
            'created_by' => $userId,
        ]);
    }

    /**
     * Append a follow-up adjustment note to a stock-batch note trail.
     */
    private function appendFollowUpNote(
        string $existingNotes,
        string $adjustmentNumber,
        string $opnameNumber,
        string $movementLabel,
        float $quantity,
    ): string {
        $note = 'Tindak lanjut '.$adjustmentNumber
            .' dari '.$opnameNumber
            .' '.$movementLabel.' '.$this->formatWholeQuantity(abs($quantity));

        return trim($existingNotes !== '' ? $existingNotes.' | '.$note : $note);
    }

    /**
     * Remove a follow-up note trail from a stock-batch note field.
     */
    private function removeFollowUpNote(string $notes, string $adjustmentNumber): ?string
    {
        $segments = collect(explode('|', $notes))
            ->map(fn (string $segment): string => trim($segment))
            ->filter(fn (string $segment): bool => $segment !== '')
            ->reject(fn (string $segment): bool => str_starts_with($segment, 'Tindak lanjut '.$adjustmentNumber))
            ->reject(fn (string $segment): bool => str_starts_with($segment, 'Pengganti stok opname '.$adjustmentNumber))
            ->values();

        return $segments->isNotEmpty()
            ? $segments->implode(' | ')
            : null;
    }

    /**
     * Resolve the proper redirect after canceling a processed follow-up.
     */
    private function followUpCancelRedirect(Request $request, StockOpnameItem $stockOpnameItem): RedirectResponse
    {
        if ($request->string('redirect_to')->toString() === 'internal-billing') {
            return redirect()->route('keuangan.riwayat-tagihan-internal');
        }

        return redirect()->route('stok-batch.penyesuaian-stok.follow-up', $stockOpnameItem->id);
    }

    /**
     * Generate the next stock opname number.
     */
    private function generateOpnameNumber(): string
    {
        $prefix = 'SO-'.now()->format('Ymd').'-';
        $latestTodayNumber = StockOpname::query()
            ->where('opname_number', 'like', $prefix.'%')
            ->latest('id')
            ->value('opname_number');

        if (! is_string($latestTodayNumber) || ! str_starts_with($latestTodayNumber, $prefix)) {
            return $prefix.'001';
        }

        $lastSequence = (int) Str::after($latestTodayNumber, $prefix);

        return $prefix.str_pad((string) ($lastSequence + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generate the next follow-up adjustment number.
     */
    private function generateAdjustmentFollowUpNumber(): string
    {
        $prefix = 'TL-'.now()->format('Ymd').'-';
        $latestTodayNumber = StockAdjustmentFollowUp::query()
            ->where('adjustment_number', 'like', $prefix.'%')
            ->latest('id')
            ->value('adjustment_number');

        if (! is_string($latestTodayNumber) || ! str_starts_with($latestTodayNumber, $prefix)) {
            return $prefix.'001';
        }

        $lastSequence = (int) Str::afterLast($latestTodayNumber, '-');

        return $prefix.str_pad((string) ($lastSequence + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Append a stock-opname note to the stock batch trail.
     */
    private function appendStockOpnameNote(
        string $existingNotes,
        string $opnameNumber,
        float $differenceQuantity,
        float $systemQuantity,
        float $physicalQuantity,
    ): string {
        $direction = $differenceQuantity > 0 ? 'lebih' : 'hilang';
        $note = 'Stok opname '.$opnameNumber
            .' ('.$direction.' '.$this->formatWholeQuantity(abs($differenceQuantity))
            .', sistem '.$this->formatWholeQuantity($systemQuantity)
            .', fisik '.$this->formatWholeQuantity($physicalQuantity).')';

        return trim($existingNotes !== '' ? $existingNotes.' | '.$note : $note);
    }

    /**
     * Build a consistent stock-opname note for movement history.
     */
    private function stockOpnameMovementNote(
        string $opnameNumber,
        float $differenceQuantity,
        float $systemQuantity,
        float $physicalQuantity,
        ?string $itemNotes,
    ): string {
        $direction = $differenceQuantity > 0 ? 'lebih' : 'hilang';
        $note = 'Stok opname '.$opnameNumber
            .' '.$direction.' '.$this->formatWholeQuantity(abs($differenceQuantity))
            .' (sistem '.$this->formatWholeQuantity($systemQuantity)
            .', fisik '.$this->formatWholeQuantity($physicalQuantity).')';

        if (filled($itemNotes)) {
            return $note.' | '.trim((string) $itemNotes);
        }

        return $note;
    }

    /**
     * Resolve reimbursement payment status from total vs paid amount.
     */
    private function resolveAdjustmentRecoveryStatus(float $replacementAmount, float $paidAmount): string
    {
        if ($paidAmount <= 0.001) {
            return 'unpaid';
        }

        if ($paidAmount + 0.001 < $replacementAmount) {
            return 'partial';
        }

        return 'paid';
    }

    /**
     * Roll back an approved stock opname if no later stock mutation exists on its batches.
     */
    private function rollbackApprovedOpname(StockOpname $stockOpname): void
    {
        $movementMap = StockMovement::query()
            ->where('reference_table', 'stock_opname_items')
            ->whereIn('reference_id', $stockOpname->items->pluck('id')->all())
            ->get()
            ->keyBy('reference_id');

        foreach ($stockOpname->items as $item) {
            $differenceQuantity = round((float) $item->difference_quantity, 2);

            if (abs($differenceQuantity) < 0.001) {
                continue;
            }

            /** @var StockMovement|null $movement */
            $movement = $movementMap->get($item->id);

            if ($movement === null) {
                throw new RuntimeException('Riwayat mutasi stok opname tidak lengkap, jadi hasil ini belum bisa dihapus.');
            }

            $hasLaterMovement = StockMovement::query()
                ->where('stock_batch_id', $item->stock_batch_id)
                ->where('id', '>', $movement->id)
                ->exists();

            if ($hasLaterMovement) {
                throw new RuntimeException('Hasil stok opname tidak bisa dihapus karena batch sudah punya mutasi stok lanjutan.');
            }

            $stockBatch = StockBatch::query()
                ->lockForUpdate()
                ->find($item->stock_batch_id);

            if ($stockBatch === null) {
                throw new RuntimeException('Batch stok untuk hasil opname ini sudah tidak ditemukan.');
            }

            $newQuantityIn = round((float) $stockBatch->quantity_in, 2);
            $newQuantityOut = round((float) $stockBatch->quantity_out, 2);

            if ($differenceQuantity > 0) {
                $newQuantityIn = round($newQuantityIn - $differenceQuantity, 2);
            } else {
                $newQuantityOut = round($newQuantityOut - abs($differenceQuantity), 2);
            }

            if ($newQuantityIn < -0.001 || $newQuantityOut < -0.001) {
                throw new RuntimeException('Saldo mutasi batch sudah tidak sinkron, jadi hasil opname belum bisa dihapus.');
            }

            $restoredBalance = round((float) $item->system_quantity, 2);

            $stockBatch->update([
                'quantity_in' => max($newQuantityIn, 0),
                'quantity_out' => max($newQuantityOut, 0),
                'quantity_balance' => max($restoredBalance, 0),
                'status' => $restoredBalance > 0 ? 'active' : 'empty',
                'notes' => $this->removeStockOpnameNote((string) $stockBatch->notes, $stockOpname->opname_number),
            ]);

            $movement->delete();
        }
    }

    /**
     * Remove an appended stock-opname note from a stock batch.
     */
    private function removeStockOpnameNote(string $notes, string $opnameNumber): ?string
    {
        $segments = collect(explode('|', $notes))
            ->map(fn (string $segment): string => trim($segment))
            ->filter(fn (string $segment): bool => $segment !== '')
            ->reject(fn (string $segment): bool => str_starts_with($segment, 'Stok opname '.$opnameNumber))
            ->values();

        return $segments->isNotEmpty()
            ? $segments->implode(' | ')
            : null;
    }
}
