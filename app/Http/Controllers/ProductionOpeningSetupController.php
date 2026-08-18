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
    private const NO_BATCH_NUMBER = 'TANPA BATCH';

    /**
     * Show the opening stock workspace.
     */
    public function stockIndex(Request $request): View
    {
        $openingEntry = OpeningStockEntry::query()
            ->where('status', 'posted')
            ->oldest('id')
            ->first();
        $openingLocationId = OpeningStockEntryItem::query()
            ->whereNotNull('storage_location_id')
            ->oldest('id')
            ->value('storage_location_id');

        return view('settings.opening-setup', [
            ...$this->pageData('setup-saldo-awal.stok'),
            'locationOptions' => StorageLocation::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'initialLocationId' => (string) old('storage_location_id', $openingLocationId ?? ''),
            'defaultEntryNumber' => $openingEntry?->entry_number ?: $this->nextEntryNumber(),
            'defaultOpeningDate' => $openingEntry?->opening_date?->toDateString() ?: now()->toDateString(),
            'initialRows' => $this->initialOpeningRows($request, $openingLocationId),
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
        if ($request->filled('items_payload')) {
            $decodedItems = json_decode((string) $request->input('items_payload'), true);
            $request->merge(['items' => is_array($decodedItems) ? $decodedItems : 'invalid']);
        }

        $validator = Validator::make($request->all(), [
            'entry_number' => ['required', 'string', 'max:50'],
            'opening_date' => ['required', 'date'],
            'storage_location_id' => ['required', 'integer', 'exists:storage_locations,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*.opening_item_id' => ['nullable', 'integer', 'exists:opening_stock_entry_items,id'],
            'items.*.medicine_id' => ['nullable', 'integer', 'exists:medicines,id'],
            'items.*.batch_number' => ['nullable', 'string', 'max:100'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
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

        try {
            $normalizedRows = $this->normalizeOpeningRows(
                $validated['items'] ?? [],
                (int) $validated['storage_location_id']
            );
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->with('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);
        }

        if ($normalizedRows->isEmpty()) {
            return back()
                ->withInput()
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Tidak ada saldo awal baru yang perlu disimpan.',
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
                $entry = OpeningStockEntry::query()
                    ->where('status', 'posted')
                    ->oldest('id')
                    ->first();

                if ($entry === null) {
                    $entry = OpeningStockEntry::query()->create([
                        'entry_number' => trim((string) $validated['entry_number']),
                        'opening_date' => $validated['opening_date'],
                        'status' => 'posted',
                        'notes' => $validated['notes'] ?? null,
                        'created_by' => $request->user()?->id,
                        'posted_by' => $request->user()?->id,
                        'posted_at' => now(),
                    ]);
                }

                foreach ($normalizedRows as $row) {
                    $openingItemId = (int) ($row['opening_item_id'] ?? 0);

                    if ($openingItemId > 0) {
                        $this->updateOpeningStockQuantity(
                            $openingItemId,
                            (int) $row['quantity'],
                            (string) $row['batch_number'],
                            $row['expiry_date']
                        );

                        continue;
                    }

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
                'message' => 'Saldo awal stok berhasil disimpan.',
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
    private function initialOpeningRows(Request $request, ?int $savedLocationId = null): array
    {
        $selectedLocationId = (string) old('storage_location_id', $savedLocationId ?? '');
        $medicineCollection = Medicine::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'small_unit', 'purchase_price', 'is_active']);
        $medicines = $medicineCollection->keyBy('id');

        $oldRows = collect($request->session()->getOldInput('items', []))
            ->filter(fn ($row) => is_array($row))
            ->reject(fn (array $row): bool => (int) ($row['opening_item_id'] ?? 0) > 0)
            ->values();
        $savedRows = $this->savedOpeningRows($selectedLocationId);

        if ($oldRows->isNotEmpty()) {
            $hydratedRows = $oldRows->map(function (array $row, int $index) use ($medicines, $selectedLocationId): array {
                $medicine = $medicines->get((int) ($row['medicine_id'] ?? 0));

                return [
                    'key' => 'opening-row-old-'.$index,
                    'medicine_id' => (string) ($row['medicine_id'] ?? ''),
                    'medicine_code' => $medicine?->code ?: '',
                    'medicine_name' => $medicine?->name ?: '',
                    'small_unit' => $medicine?->small_unit ?: 'unit',
                    'is_active' => (bool) ($medicine?->is_active ?? false),
                    'is_saved' => false,
                    'opening_item_id' => '',
                    'storage_location_id' => $selectedLocationId,
                    'batch_number' => (string) ($row['batch_number'] ?? ''),
                    'expiry_date' => (string) (($row['expiry_date'] ?? '') ?: now()->toDateString()),
                    'quantity' => (string) ($row['quantity'] ?? ''),
                    'purchase_price' => (string) ($row['purchase_price'] ?? ($medicine?->purchase_price ?? '')),
                    'selling_price' => (string) ($row['selling_price'] ?? ''),
                    'notes' => (string) ($row['notes'] ?? ''),
                    'is_dirty' => true,
                ];
            })->values();

            $existingMedicineIds = $hydratedRows
                ->pluck('medicine_id')
                ->concat($savedRows->pluck('medicine_id'))
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

            $remainingRows = $medicineCollection
                ->reject(fn (Medicine $medicine): bool => in_array($medicine->id, $existingMedicineIds, true))
                ->values()
                ->map(fn (Medicine $medicine, int $index): array => $this->blankOpeningRow('opening-row-rest-'.$index, $medicine, $selectedLocationId));

            return $savedRows
                ->concat($hydratedRows)
                ->concat($remainingRows)
                ->values()
                ->all();
        }

        $savedMedicineIds = $savedRows
            ->pluck('medicine_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $blankRows = $medicineCollection
            ->reject(fn (Medicine $medicine): bool => in_array($medicine->id, $savedMedicineIds, true))
            ->values()
            ->map(fn (Medicine $medicine, int $index): array => $this->blankOpeningRow('opening-row-'.$index, $medicine, $selectedLocationId))
            ->all();

        return $savedRows->concat($blankRows)->values()->all();
    }

    /**
     * Hydrate the already-posted opening balances for the single setup page.
     */
    private function savedOpeningRows(string $selectedLocationId): Collection
    {
        return OpeningStockEntryItem::query()
            // Snapshot rows whose master medicine was removed are historical data,
            // not inactive medicines, so they must not appear in the setup catalog.
            ->whereNotNull('medicine_id')
            ->whereHas('medicine')
            ->with([
                'medicine:id,code,name,small_unit,is_active',
            ])
            ->oldest('id')
            ->get()
            ->map(function (OpeningStockEntryItem $item, int $index) use ($selectedLocationId): array {
                return [
                    'key' => 'opening-row-saved-'.$item->id.'-'.$index,
                    'opening_item_id' => (string) $item->id,
                    'medicine_id' => (string) $item->medicine_id,
                    'medicine_code' => $item->medicine?->code ?: $item->medicine_code_snapshot,
                    'medicine_name' => $item->medicine?->name ?: $item->medicine_name_snapshot,
                    'small_unit' => $item->medicine?->small_unit ?: ($item->medicine_unit_snapshot ?: 'unit'),
                    'is_active' => (bool) ($item->medicine?->is_active ?? false),
                    'is_saved' => true,
                    'storage_location_id' => (string) ($item->storage_location_id ?: $selectedLocationId),
                    'batch_number' => $item->batch_number === self::NO_BATCH_NUMBER ? '' : (string) $item->batch_number,
                    'expiry_date' => $item->expiry_date?->toDateString() ?: now()->toDateString(),
                    'quantity' => (string) (int) $item->quantity,
                    'purchase_price' => (string) $item->purchase_price,
                    'selling_price' => (string) $item->selling_price,
                    'notes' => (string) ($item->notes ?? ''),
                    'is_committed' => true,
                    'is_dirty' => false,
                ];
            });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function normalizeOpeningRows(array $rows, int $storageLocationId): Collection
    {
        $openingItems = OpeningStockEntryItem::query()
            ->whereIn('id', collect($rows)->pluck('opening_item_id')->filter()->map(fn ($id) => (int) $id)->all())
            ->get()
            ->keyBy('id');
        $medicines = Medicine::query()
            ->whereIn('id', collect($rows)->pluck('medicine_id')->filter()->map(fn ($id) => (int) $id)->all())
            ->get(['id', 'code', 'name', 'purchase_price'])
            ->keyBy('id');

        return collect($rows)
            ->map(function (array $row) use ($openingItems, $medicines, $storageLocationId): ?array {
                $openingItemId = (int) ($row['opening_item_id'] ?? 0);
                $medicineId = (int) ($row['medicine_id'] ?? 0);
                $batchNumber = trim((string) ($row['batch_number'] ?? ''));
                $quantity = (int) ($row['quantity'] ?? 0);
                $expiryDate = filled($row['expiry_date'] ?? null) ? (string) $row['expiry_date'] : null;
                $purchasePrice = 0.0;
                $sellingPrice = 0.0;
                $notes = trim((string) ($row['notes'] ?? '')) ?: null;

                if ($openingItemId <= 0 && $quantity <= 0) {
                    return null;
                }

                if ($openingItemId > 0) {
                    $openingItem = $openingItems->get($openingItemId);

                    if ($openingItem === null) {
                        throw new RuntimeException('Data saldo awal yang akan diperbarui tidak ditemukan.');
                    }

                    return [
                        'opening_item_id' => $openingItemId,
                        'medicine_id' => $openingItem->medicine_id,
                        'medicine_label' => $openingItem->medicine_name_snapshot ?: 'Obat snapshot',
                        'storage_location_id' => $openingItem->storage_location_id,
                        'batch_number' => $batchNumber !== '' ? $batchNumber : self::NO_BATCH_NUMBER,
                        'expiry_date' => $expiryDate,
                        'quantity' => $quantity,
                        'purchase_price' => (float) $openingItem->purchase_price,
                        'selling_price' => (float) $openingItem->selling_price,
                        'notes' => $openingItem->notes,
                    ];
                }

                if ($medicineId <= 0 || ($openingItemId <= 0 && $quantity <= 0)) {
                    throw new RuntimeException('Obat pada baris saldo awal yang diisi tidak valid.');
                }

                $medicine = $medicines->get($medicineId);

                if ($medicine === null) {
                    throw new RuntimeException('Ada obat saldo awal yang tidak valid.');
                }

                $batchNumber = $batchNumber !== '' ? $batchNumber : self::NO_BATCH_NUMBER;
                $purchasePrice = round((float) ($medicine->purchase_price ?? 0), 2);

                return [
                    'opening_item_id' => $openingItemId ?: null,
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
     * Keep the opening item, stock batch, and opening movement in sync.
     */
    private function updateOpeningStockQuantity(
        int $openingItemId,
        int $quantity,
        string $batchNumber,
        ?string $expiryDate
    ): void
    {
        $item = OpeningStockEntryItem::query()
            ->with(['entry', 'stockBatch'])
            ->lockForUpdate()
            ->findOrFail($openingItemId);
        $batch = $item->stockBatch;
        $batchNumber = trim($batchNumber) !== '' ? trim($batchNumber) : self::NO_BATCH_NUMBER;

        $duplicateBatchExists = OpeningStockEntryItem::query()
            ->whereKeyNot($item->id)
            ->where('medicine_id', $item->medicine_id)
            ->where('storage_location_id', $item->storage_location_id)
            ->where('batch_number', $batchNumber)
            ->when(
                $expiryDate !== null,
                fn ($query) => $query->whereDate('expiry_date', $expiryDate),
                fn ($query) => $query->whereNull('expiry_date')
            )
            ->exists();

        if ($duplicateBatchExists) {
            throw new RuntimeException('Batch '.$batchNumber.' sudah digunakan pada saldo awal obat yang sama.');
        }

        if ($batch === null) {
            $batch = StockBatch::query()->create([
                'medicine_id' => $item->medicine_id,
                'purchase_invoice_item_id' => null,
                'storage_location_id' => $item->storage_location_id,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
                'received_at' => $item->entry?->opening_date ?: now()->toDateString(),
                'purchase_price' => $item->purchase_price,
                'selling_price' => $item->selling_price,
                'initial_quantity' => $quantity,
                'quantity_in' => $quantity,
                'quantity_out' => 0,
                'quantity_balance' => $quantity,
                'status' => 'active',
                'notes' => $item->notes ?: 'Pemulihan saldo awal stok',
            ]);

            $item->update([
                'stock_batch_id' => $batch->id,
                'quantity' => $quantity,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
            ]);

            StockMovement::query()->create([
                'movement_date' => ($item->entry?->opening_date?->toDateString() ?: now()->toDateString()).' 00:00:00',
                'movement_type' => 'opening_balance',
                'reference_table' => 'opening_stock_entry_items',
                'reference_id' => $item->id,
                'medicine_id' => $item->medicine_id,
                'stock_batch_id' => $batch->id,
                'storage_location_id' => $item->storage_location_id,
                'quantity_in' => $quantity,
                'quantity_out' => 0,
                'balance_after' => $quantity,
                'unit_cost' => $item->purchase_price,
                'notes' => 'Saldo awal stok '.$batchNumber,
                'created_by' => $item->entry?->posted_by,
            ]);

            return;
        }

        $quantityOut = (float) $batch->quantity_out;

        if ($quantity < $quantityOut) {
            throw new RuntimeException(
                'Qty awal '.$item->medicine_name_snapshot.' tidak boleh lebih kecil dari qty yang sudah keluar ('.number_format($quantityOut, 0, ',', '.').').'
            );
        }

        $item->update([
            'quantity' => $quantity,
            'batch_number' => $batchNumber,
            'expiry_date' => $expiryDate,
        ]);
        $batch->update([
            'batch_number' => $batchNumber,
            'expiry_date' => $expiryDate,
            'initial_quantity' => $quantity,
            'quantity_in' => $quantity,
            'quantity_balance' => $quantity - $quantityOut,
        ]);

        StockMovement::query()
            ->where('reference_table', 'opening_stock_entry_items')
            ->where('reference_id', $item->id)
            ->where('movement_type', 'opening_balance')
            ->update([
                'quantity_in' => $quantity,
                'balance_after' => $quantity,
                'notes' => 'Saldo awal stok '.$batchNumber,
            ]);
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
            'is_active' => (bool) ($medicine?->is_active ?? false),
            'is_saved' => false,
            'opening_item_id' => '',
            'storage_location_id' => $storageLocationId,
            'batch_number' => '',
            'expiry_date' => now()->toDateString(),
            'quantity' => '',
            'purchase_price' => $medicine ? (string) ($medicine->purchase_price ?? '') : '',
            'selling_price' => '',
            'notes' => '',
            'is_dirty' => false,
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
