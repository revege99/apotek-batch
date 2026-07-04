<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\OpeningStockEntry;
use App\Models\OpeningStockEntryItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class ProductionOpeningSetupController extends Controller
{
    /**
     * Show the opening stock workspace.
     */
    public function stockIndex(Request $request): View
    {
        return view('settings.opening-setup', [
            ...$this->pageData('setup-saldo-awal.stok'),
            'locationOptions' => StorageLocation::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'initialLocationId' => (string) old('storage_location_id', ''),
            'defaultEntryNumber' => $this->nextEntryNumber(),
            'defaultOpeningDate' => now()->toDateString(),
            'initialRows' => $this->initialOpeningRows($request),
            'openingStockStats' => $this->openingStockStats(),
        ]);
    }

    /**
     * Show opening stock posting history.
     */
    public function stockHistoryIndex(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        return view('settings.opening-setup-history', [
            ...$this->pageData('setup-saldo-awal.stok', 'Riwayat Saldo Awal Stok'),
            'search' => $search,
            'entryItems' => $this->openingStockItemQuery($search)
                ->paginate(15)
                ->withQueryString(),
            'openingStockStats' => $this->openingStockStats(),
        ]);
    }

    /**
     * Show the opening receivable workspace.
     */
    public function receivableIndex(): View
    {
        return $this->placeholderPage(
            'setup-saldo-awal.piutang',
            'Piutang Awal',
            'Catat saldo piutang pelanggan yang masih berjalan sebelum klinik mulai memakai aplikasi ini penuh.',
            [
                'Simpan per pelanggan, nominal sisa, tanggal, dan jatuh tempo.',
                'Pembayaran berikutnya akan melanjutkan saldo awal tersebut.',
                'Cocok untuk migrasi tanpa input ulang semua penjualan lama.',
            ],
        );
    }

    /**
     * Show the opening payable workspace.
     */
    public function payableIndex(): View
    {
        return $this->placeholderPage(
            'setup-saldo-awal.hutang',
            'Hutang Awal',
            'Catat sisa hutang supplier dari sistem lama tanpa perlu membangun ulang seluruh histori faktur pembelian.',
            [
                'Simpan per supplier, nomor referensi, tanggal, dan nominal sisa.',
                'Pembayaran hutang setelah go-live tinggal melunasi saldo awal ini.',
                'Menjaga laporan hutang tetap nyambung dari hari pertama aplikasi dipakai.',
            ],
        );
    }

    /**
     * Show the opening cash workspace.
     */
    public function cashIndex(): View
    {
        return $this->placeholderPage(
            'setup-saldo-awal.kas',
            'Kas Awal',
            'Siapkan saldo kas pembuka agar laporan kas tidak mulai dari nol saat aplikasi mulai digunakan.',
            [
                'Bisa dipakai untuk kas utama atau sumber dana operasional sederhana.',
                'Membantu laporan penerimaan kas lebih akurat sejak awal penggunaan aplikasi.',
                'Cocok dipadukan dengan saldo awal stok, piutang, dan hutang.',
            ],
        );
    }

    /**
     * Store one opening stock document and post it immediately.
     */
    public function storeOpeningStock(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'entry_number' => ['required', 'string', 'max:50', 'unique:opening_stock_entries,entry_number'],
            'opening_date' => ['required', 'date'],
            'storage_location_id' => ['required', 'integer', 'exists:storage_locations,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array'],
            'items.*.medicine_id' => ['nullable', 'integer', 'exists:medicines,id'],
            'items.*.batch_number' => ['nullable', 'string', 'max:100'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'entry_number.unique' => 'Nomor dokumen saldo awal sudah dipakai.',
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
        $normalizedRows = $this->normalizeOpeningRows($validated['items'] ?? [], (int) $validated['storage_location_id']);

        if ($normalizedRows->isEmpty()) {
            return back()
                ->withInput()
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Isi minimal satu batch saldo awal sebelum disimpan.',
                ]);
        }

        $duplicateCheck = $this->findDuplicateRows($normalizedRows);
        if ($duplicateCheck !== null) {
            return back()
                ->withInput()
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Ada batch saldo awal yang sama lebih dari satu kali di dokumen ini: '.$duplicateCheck,
                ]);
        }

        try {
            DB::transaction(function () use ($request, $validated, $normalizedRows): void {
                $entry = OpeningStockEntry::query()->create([
                    'entry_number' => trim((string) $validated['entry_number']),
                    'opening_date' => $validated['opening_date'],
                    'status' => 'posted',
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()?->id,
                    'posted_by' => $request->user()?->id,
                    'posted_at' => now(),
                ]);

                foreach ($normalizedRows as $row) {
                    $existingBatch = $this->duplicatePostedBatchQuery(
                        (int) $row['medicine_id'],
                        (int) ($row['storage_location_id'] ?? 0) ?: null,
                        (string) $row['batch_number'],
                        $row['expiry_date']
                    )->exists();

                    if ($existingBatch) {
                        throw new RuntimeException('Batch saldo awal untuk '.$row['medicine_label'].' / '.$row['batch_number'].' sudah pernah diposting sebelumnya.');
                    }

                    $stockBatch = StockBatch::query()->create([
                        'medicine_id' => $row['medicine_id'],
                        'purchase_invoice_item_id' => null,
                        'storage_location_id' => $row['storage_location_id'],
                        'batch_number' => $row['batch_number'],
                        'expiry_date' => $row['expiry_date'],
                        'received_at' => $validated['opening_date'],
                        'purchase_price' => $row['purchase_price'],
                        'selling_price' => $row['selling_price'],
                        'initial_quantity' => $row['quantity'],
                        'quantity_in' => $row['quantity'],
                        'quantity_out' => 0,
                        'quantity_balance' => $row['quantity'],
                        'status' => 'active',
                        'notes' => $row['notes'] ?: 'Saldo awal stok '.$entry->entry_number,
                    ]);

                    $entryItem = $entry->items()->create([
                        'medicine_id' => $row['medicine_id'],
                        'storage_location_id' => $row['storage_location_id'],
                        'stock_batch_id' => $stockBatch->id,
                        'batch_number' => $row['batch_number'],
                        'expiry_date' => $row['expiry_date'],
                        'quantity' => $row['quantity'],
                        'purchase_price' => $row['purchase_price'],
                        'selling_price' => $row['selling_price'],
                        'notes' => $row['notes'],
                    ]);

                    StockMovement::query()->create([
                        'movement_date' => $validated['opening_date'].' 00:00:00',
                        'movement_type' => 'opening_balance',
                        'reference_table' => 'opening_stock_entry_items',
                        'reference_id' => $entryItem->id,
                        'medicine_id' => $row['medicine_id'],
                        'stock_batch_id' => $stockBatch->id,
                        'storage_location_id' => $row['storage_location_id'],
                        'quantity_in' => $row['quantity'],
                        'quantity_out' => 0,
                        'balance_after' => $row['quantity'],
                        'unit_cost' => $row['purchase_price'],
                        'notes' => 'Saldo awal stok '.$entry->entry_number.' / '.$row['batch_number'],
                        'created_by' => $request->user()?->id,
                    ]);

                    Medicine::query()
                        ->whereKey($row['medicine_id'])
                        ->update(['purchase_price' => $row['purchase_price']]);
                }
            });
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('setup-saldo-awal.stok')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Dokumen saldo awal stok berhasil diposting.',
            ]);
    }

    /**
     * Delete one opening stock batch row if it has never been used.
     */
    public function destroyOpeningStock(OpeningStockEntryItem $openingStockEntryItem): RedirectResponse
    {
        $openingStockEntryItem->load([
            'entry:id,entry_number',
            'medicine:id,name',
            'stockBatch',
        ]);

        try {
            DB::transaction(function () use ($openingStockEntryItem): void {
                $lockedItem = OpeningStockEntryItem::query()
                    ->with(['entry', 'stockBatch'])
                    ->lockForUpdate()
                    ->findOrFail($openingStockEntryItem->id);

                $stockBatch = $lockedItem->stockBatch;

                if ($stockBatch === null) {
                    throw new RuntimeException('Batch stok saldo awal tidak ditemukan.');
                }

                $batchMovements = StockMovement::query()
                    ->where('stock_batch_id', $stockBatch->id)
                    ->orderBy('id')
                    ->get();

                if (
                    $batchMovements->count() !== 1
                    || $batchMovements->first()?->movement_type !== 'opening_balance'
                    || $batchMovements->first()?->reference_table !== 'opening_stock_entry_items'
                    || (int) $batchMovements->first()?->reference_id !== $lockedItem->id
                    || abs((float) $stockBatch->quantity_out) > 0.0001
                    || round((float) $stockBatch->quantity_balance, 2) !== round((float) $stockBatch->quantity_in, 2)
                ) {
                    throw new RuntimeException('Batch saldo awal ini sudah dipakai pada proses lain, jadi tidak bisa dihapus.');
                }

                StockMovement::query()
                    ->where('stock_batch_id', $stockBatch->id)
                    ->delete();

                $stockBatch->delete();

                $entry = $lockedItem->entry;
                $lockedItem->delete();

                if ($entry !== null && ! $entry->items()->exists()) {
                    $entry->delete();
                }
            });
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('setup-saldo-awal.stok')
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('setup-saldo-awal.stok')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Batch saldo awal berhasil dihapus.',
            ]);
    }

    /**
     * Resolve page metadata from the navigation config.
     *
     * @return array{page: array<string, mixed>, section: string}
     */
    private function pageData(string $routeName, ?string $labelOverride = null): array
    {
        $section = collect(config('apotik.navigation'))
            ->first(fn (array $group): bool => collect($group['children'] ?? [])
                ->contains(fn (array $child): bool => ($child['route'] ?? null) === $routeName));

        $page = collect($section['children'] ?? [])
            ->firstWhere('route', $routeName);

        return [
            'page' => [
                ...($page ?? ['label' => 'Setup Saldo Awal']),
                ...($labelOverride !== null ? ['label' => $labelOverride] : []),
            ],
            'section' => $section['label'] ?? 'Pengaturan',
        ];
    }

    /**
     * Build stock opening mini statistics.
     *
     * @return array{entries:int,batches:int,medicines:int,quantity:float,value:float}
     */
    private function openingStockStats(): array
    {
        $statsQuery = $this->openingStockItemQuery()->reorder();

        return [
            'entries' => OpeningStockEntry::query()->where('status', 'posted')->count(),
            'batches' => (clone $statsQuery)->count(),
            'medicines' => (clone $statsQuery)->distinct('medicine_id')->count('medicine_id'),
            'quantity' => (float) (clone $statsQuery)
                ->selectRaw('COALESCE(SUM(quantity), 0) as total_quantity')
                ->value('total_quantity'),
            'value' => (float) (clone $statsQuery)
                ->selectRaw('COALESCE(SUM(quantity * purchase_price), 0) as total_value')
                ->value('total_value'),
        ];
    }

    /**
     * Render a placeholder page for upcoming opening-balance modules.
     *
     * @param  array<int, string>  $checkpoints
     */
    private function placeholderPage(string $routeName, string $title, string $description, array $checkpoints): View
    {
        return view('settings.opening-setup-placeholder', [
            ...$this->pageData($routeName),
            'title' => $title,
            'description' => $description,
            'checkpoints' => $checkpoints,
        ]);
    }

    /**
     * Build initial rows from all active medicines and hydrate old input rows.
     *
     * @return array<int, array<string, mixed>>
     */
    private function initialOpeningRows(Request $request): array
    {
        $selectedLocationId = (string) old('storage_location_id', '');
        $medicineCollection = Medicine::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'small_unit', 'purchase_price']);
        $medicines = $medicineCollection->keyBy('id');

        $oldRows = collect($request->session()->getOldInput('items', []))
            ->filter(fn ($row) => is_array($row))
            ->values();

        if ($oldRows->isNotEmpty()) {
            $hydratedRows = $oldRows->map(function (array $row, int $index) use ($medicines, $selectedLocationId): array {
                $medicine = $medicines->get((int) ($row['medicine_id'] ?? 0));

                return [
                    'key' => 'opening-row-old-'.$index,
                    'medicine_id' => (string) ($row['medicine_id'] ?? ''),
                    'medicine_code' => $medicine?->code ?: '',
                    'medicine_name' => $medicine?->name ?: '',
                    'small_unit' => $medicine?->small_unit ?: 'unit',
                    'storage_location_id' => $selectedLocationId,
                    'batch_number' => (string) ($row['batch_number'] ?? ''),
                    'expiry_date' => (string) ($row['expiry_date'] ?? ''),
                    'quantity' => (string) ($row['quantity'] ?? ''),
                    'purchase_price' => (string) ($row['purchase_price'] ?? ($medicine?->purchase_price ?? '')),
                    'selling_price' => (string) ($row['selling_price'] ?? ''),
                    'notes' => (string) ($row['notes'] ?? ''),
                ];
            })->values();

            $existingMedicineIds = $hydratedRows
                ->pluck('medicine_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

            $remainingRows = $medicineCollection
                ->reject(fn (Medicine $medicine): bool => in_array($medicine->id, $existingMedicineIds, true))
                ->values()
                ->map(fn (Medicine $medicine, int $index): array => $this->blankOpeningRow('opening-row-rest-'.$index, $medicine, $selectedLocationId));

            return $hydratedRows
                ->concat($remainingRows)
                ->all();
        }

        return $medicineCollection
            ->values()
            ->map(fn (Medicine $medicine, int $index): array => $this->blankOpeningRow('opening-row-'.$index, $medicine, $selectedLocationId))
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function normalizeOpeningRows(array $rows, int $storageLocationId): Collection
    {
        $medicines = Medicine::query()
            ->whereIn('id', collect($rows)->pluck('medicine_id')->filter()->map(fn ($id) => (int) $id)->all())
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        return collect($rows)
            ->map(function (array $row) use ($medicines, $storageLocationId): ?array {
                $medicineId = (int) ($row['medicine_id'] ?? 0);
                $batchNumber = trim((string) ($row['batch_number'] ?? ''));
                $quantity = round((float) ($row['quantity'] ?? 0), 2);
                $expiryDate = filled($row['expiry_date'] ?? null) ? (string) $row['expiry_date'] : null;
                $purchasePrice = round((float) ($row['purchase_price'] ?? 0), 2);
                $sellingPrice = round((float) ($row['selling_price'] ?? 0), 2);
                $notes = trim((string) ($row['notes'] ?? '')) ?: null;

                if (
                    $medicineId <= 0
                    && $batchNumber === ''
                    && $quantity <= 0
                ) {
                    return null;
                }

                if (
                    $medicineId > 0
                    && $batchNumber === ''
                    && $quantity <= 0
                    && $expiryDate === null
                    && $sellingPrice <= 0
                    && $notes === null
                ) {
                    return null;
                }

                if ($medicineId <= 0 || $batchNumber === '' || $quantity <= 0) {
                    throw new RuntimeException('Pastikan setiap baris saldo awal yang dipakai sudah berisi obat, batch, dan qty.');
                }

                $medicine = $medicines->get($medicineId);

                if ($medicine === null) {
                    throw new RuntimeException('Ada obat saldo awal yang tidak valid.');
                }

                return [
                    'medicine_id' => $medicineId,
                    'medicine_label' => trim($medicine->code.' - '.$medicine->name),
                    'storage_location_id' => $storageLocationId,
                    'batch_number' => $batchNumber,
                    'expiry_date' => $expiryDate,
                    'quantity' => $quantity,
                    'purchase_price' => $purchasePrice,
                    'selling_price' => $sellingPrice,
                    'notes' => $notes,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Find duplicate keys inside one submitted document.
     */
    private function findDuplicateRows(Collection $rows): ?string
    {
        $seen = [];

        foreach ($rows as $row) {
            $key = implode('|', [
                $row['medicine_id'],
                $row['storage_location_id'] ?? 'null',
                mb_strtolower($row['batch_number']),
                $row['expiry_date'] ?? 'null',
            ]);

            if (isset($seen[$key])) {
                return $row['medicine_label'].' / '.$row['batch_number'];
            }

            $seen[$key] = true;
        }

        return null;
    }

    /**
     * Build query for opening stock item history.
     */
    private function openingStockItemQuery(string $search = '')
    {
        return OpeningStockEntryItem::query()
            ->with([
                'entry:id,entry_number,opening_date',
                'medicine:id,code,name,small_unit',
                'storageLocation:id,name',
                'stockBatch:id,quantity_in,quantity_out,quantity_balance',
            ])
            ->whereHas('entry', fn ($query) => $query->where('status', 'posted'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('batch_number', 'like', "%{$search}%")
                        ->orWhereHas('entry', fn ($entryQuery) => $entryQuery->where('entry_number', 'like', "%{$search}%"))
                        ->orWhereHas('medicine', function ($medicineQuery) use ($search) {
                            $medicineQuery
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('storageLocation', fn ($locationQuery) => $locationQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest(
                OpeningStockEntry::query()
                    ->select('opening_date')
                    ->whereColumn('opening_stock_entries.id', 'opening_stock_entry_items.opening_stock_entry_id')
                    ->limit(1)
            )
            ->latest('id');
    }

    /**
     * Query duplicate already-posted opening batches.
     */
    private function duplicatePostedBatchQuery(int $medicineId, ?int $storageLocationId, string $batchNumber, ?string $expiryDate)
    {
        return OpeningStockEntryItem::query()
            ->where('medicine_id', $medicineId)
            ->where('storage_location_id', $storageLocationId)
            ->where('batch_number', $batchNumber)
            ->when($expiryDate !== null, fn ($query) => $query->whereDate('expiry_date', $expiryDate), fn ($query) => $query->whereNull('expiry_date'))
            ->whereHas('entry', fn ($query) => $query->where('status', 'posted'));
    }

    /**
     * Build one blank opening row.
     *
     * @return array<string, mixed>
     */
    private function blankOpeningRow(string $key, ?Medicine $medicine = null, string $storageLocationId = ''): array
    {
        return [
            'key' => $key,
            'medicine_id' => $medicine ? (string) $medicine->id : '',
            'medicine_code' => $medicine?->code ?: '',
            'medicine_name' => $medicine?->name ?: '',
            'small_unit' => $medicine?->small_unit ?: 'unit',
            'storage_location_id' => $storageLocationId,
            'batch_number' => '',
            'expiry_date' => '',
            'quantity' => '',
            'purchase_price' => $medicine ? (string) ($medicine->purchase_price ?? '') : '',
            'selling_price' => '',
            'notes' => '',
        ];
    }

    /**
     * Generate the next document number.
     */
    private function nextEntryNumber(): string
    {
        $todayPrefix = 'SA-'.now()->format('Ymd').'-';

        $latestCode = OpeningStockEntry::query()
            ->where('entry_number', 'like', $todayPrefix.'%')
            ->orderByDesc('id')
            ->value('entry_number');

        if (! is_string($latestCode) || ! preg_match('/(\d+)$/', $latestCode, $matches)) {
            return $todayPrefix.'001';
        }

        return $todayPrefix.str_pad((string) ((int) $matches[1] + 1), 3, '0', STR_PAD_LEFT);
    }
}
