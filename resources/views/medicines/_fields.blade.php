@php
    $fieldPrefix = $fieldPrefix ?? '';
    $selectedMedicineType = old('medicine_type', $medicine->medicine_type);
    $selectedCategoryName = old('category_name', $medicine->category_name);
    $selectedMedicineGroup = old('medicine_group', $medicine->medicine_group);
    $selectedLargeUnit = old('large_unit', $medicine->large_unit);
    $selectedSmallUnit = old('small_unit', $medicine->small_unit);
    $selectedPrincipalId = (string) old('principal_id', $selectedPrincipalId ?? '');

    $medicineTypeOptions = collect($typeSuggestions)
        ->prepend($selectedMedicineType)
        ->filter()
        ->unique(fn (string $value): string => \Illuminate\Support\Str::lower($value))
        ->values();
    $categoryOptions = collect($categorySuggestions)
        ->prepend($selectedCategoryName)
        ->filter()
        ->unique(fn (string $value): string => \Illuminate\Support\Str::lower($value))
        ->values();
    $groupOptions = collect($groupSuggestions)
        ->prepend($selectedMedicineGroup)
        ->filter()
        ->unique(fn (string $value): string => \Illuminate\Support\Str::lower($value))
        ->values();
    $largeUnitOptions = collect($largeUnitSuggestions)
        ->prepend($selectedLargeUnit)
        ->filter()
        ->unique(fn (string $value): string => \Illuminate\Support\Str::lower($value))
        ->values();
    $smallUnitOptions = collect($smallUnitSuggestions)
        ->prepend($selectedSmallUnit)
        ->filter()
        ->unique(fn (string $value): string => \Illuminate\Support\Str::lower($value))
        ->values();
    $rawPurchasePrice = old('purchase_price', $medicine->purchase_price);
    $purchasePriceDisplay = '';
    $minimumStockValue = old('minimum_stock', $medicine->minimum_stock);

    if ($rawPurchasePrice !== null && $rawPurchasePrice !== '') {
        $purchasePriceText = trim((string) $rawPurchasePrice);

        if (str_contains($purchasePriceText, ',')) {
            $purchasePriceText = explode(',', $purchasePriceText, 2)[0];
        } elseif (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $purchasePriceText) !== 1 && is_numeric($purchasePriceText)) {
            $purchasePriceText = (string) ((int) ((float) $purchasePriceText));
        }

        $purchasePriceDigits = preg_replace('/\D+/', '', $purchasePriceText) ?? '';
        $purchasePriceDisplay = $purchasePriceDigits !== ''
            ? number_format((int) $purchasePriceDigits, 0, ',', '.')
            : '';
    }

    if ($minimumStockValue !== null && $minimumStockValue !== '' && is_numeric($minimumStockValue)) {
        $minimumStockValue = round((float) $minimumStockValue, 2);
        $minimumStockValue = floor($minimumStockValue) === $minimumStockValue
            ? (string) ((int) $minimumStockValue)
            : rtrim(rtrim(number_format($minimumStockValue, 2, '.', ''), '0'), '.');
    }
@endphp

<div class="grid gap-3 md:grid-cols-2">
    <div class="space-y-2">
        <label for="{{ $fieldPrefix }}code" class="text-sm font-semibold text-slate-800">Kode barang</label>
        <input
            id="{{ $fieldPrefix }}code"
            name="code"
            type="text"
            value="{{ old('code', $medicine->code) }}"
            placeholder="oba0000001"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
        >
        @error('code')
            <p class="text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="space-y-2">
        <label for="{{ $fieldPrefix }}principal_id" class="text-sm font-semibold text-slate-800">Industri farmasi</label>
        <select
            id="{{ $fieldPrefix }}principal_id"
            name="principal_id"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
        >
            <option value="">Pilih industri farmasi</option>
            @foreach ($principalOptions as $option)
                <option value="{{ $option->id }}" @selected($selectedPrincipalId === (string) $option->id)>
                    {{ $option->name }}{{ $option->is_active ? '' : ' (nonaktif)' }}
                </option>
            @endforeach
        </select>
        @error('principal_id')
            <p class="text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="space-y-2">
    <label for="{{ $fieldPrefix }}name" class="text-sm font-semibold text-slate-800">Nama barang</label>
    <input
        id="{{ $fieldPrefix }}name"
        name="name"
        type="text"
        value="{{ old('name', $medicine->name) }}"
        placeholder="Contoh: Paracetamol 500 mg"
        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
    >
    @error('name')
        <p class="text-sm text-rose-600">{{ $message }}</p>
    @enderror
</div>

<div class="grid gap-3 md:grid-cols-3">
    <div class="space-y-2">
        <label for="{{ $fieldPrefix }}medicine_type" class="text-sm font-semibold text-slate-800">Jenis</label>
        <select
            id="{{ $fieldPrefix }}medicine_type"
            name="medicine_type"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
        >
            <option value="">Pilih jenis obat</option>
            @foreach ($medicineTypeOptions as $option)
                <option value="{{ $option }}" @selected($selectedMedicineType === $option)>{{ $option }}</option>
            @endforeach
        </select>
        @error('medicine_type')
            <p class="text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="space-y-2">
        <label for="{{ $fieldPrefix }}category_name" class="text-sm font-semibold text-slate-800">Kategori</label>
        <select
            id="{{ $fieldPrefix }}category_name"
            name="category_name"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
        >
            <option value="">Pilih kategori obat</option>
            @foreach ($categoryOptions as $option)
                <option value="{{ $option }}" @selected($selectedCategoryName === $option)>{{ $option }}</option>
            @endforeach
        </select>
        @error('category_name')
            <p class="text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="space-y-2">
        <label for="{{ $fieldPrefix }}medicine_group" class="text-sm font-semibold text-slate-800">Golongan</label>
        <select
            id="{{ $fieldPrefix }}medicine_group"
            name="medicine_group"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
        >
            <option value="">Pilih golongan obat</option>
            @foreach ($groupOptions as $option)
                <option value="{{ $option }}" @selected($selectedMedicineGroup === $option)>{{ $option }}</option>
            @endforeach
        </select>
        @error('medicine_group')
            <p class="text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="grid gap-3 md:grid-cols-3">
    <div class="space-y-2">
        <label for="{{ $fieldPrefix }}large_unit" class="text-sm font-semibold text-slate-800">Satuan besar</label>
        <select
            id="{{ $fieldPrefix }}large_unit"
            name="large_unit"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
        >
            <option value="">Pilih satuan besar</option>
            @foreach ($largeUnitOptions as $option)
                <option value="{{ $option }}" @selected($selectedLargeUnit === $option)>{{ $option }}</option>
            @endforeach
        </select>
        @error('large_unit')
            <p class="text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="space-y-2">
        <label for="{{ $fieldPrefix }}small_unit" class="text-sm font-semibold text-slate-800">Satuan kecil</label>
        <select
            id="{{ $fieldPrefix }}small_unit"
            name="small_unit"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
        >
            <option value="">Pilih satuan kecil</option>
            @foreach ($smallUnitOptions as $option)
                <option value="{{ $option }}" @selected($selectedSmallUnit === $option)>{{ $option }}</option>
            @endforeach
        </select>
        @error('small_unit')
            <p class="text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="space-y-2">
        <label for="{{ $fieldPrefix }}small_unit_per_large_unit" class="text-sm font-semibold text-slate-800">Isi</label>
        <input
            id="{{ $fieldPrefix }}small_unit_per_large_unit"
            name="small_unit_per_large_unit"
            type="number"
            min="1"
            value="{{ old('small_unit_per_large_unit', $medicine->small_unit_per_large_unit) }}"
            placeholder="Contoh: 10"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
        >
        @error('small_unit_per_large_unit')
            <p class="text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="grid gap-3 md:grid-cols-[220px,180px,minmax(0,1fr)]">
    <div class="space-y-2" x-data="medicinePriceInput()">
        <label for="{{ $fieldPrefix }}purchase_price" class="text-sm font-semibold text-slate-800">Harga beli</label>
        <input
            id="{{ $fieldPrefix }}purchase_price"
            name="purchase_price"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            value="{{ $purchasePriceDisplay }}"
            placeholder="Contoh: 2.500"
            @input="formatInput($event)"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
        >
        @error('purchase_price')
            <p class="text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="space-y-2">
        <label for="{{ $fieldPrefix }}minimum_stock" class="text-sm font-semibold text-slate-800">Stok minimum</label>
        <input
            id="{{ $fieldPrefix }}minimum_stock"
            name="minimum_stock"
            type="number"
            min="0"
            step="1"
            value="{{ $minimumStockValue }}"
            placeholder="Contoh: 10"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
        >
        @error('minimum_stock')
            <p class="text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="space-y-2">
        <label for="{{ $fieldPrefix }}composition" class="text-sm font-semibold text-slate-800">Kandungan / komposisi</label>
        <textarea
            id="{{ $fieldPrefix }}composition"
            name="composition"
            rows="2"
            placeholder="Contoh: Tiap tablet mengandung Paracetamol 500 mg."
            class="w-full rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-2 text-sm leading-6 text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
        >{{ old('composition', $medicine->composition) }}</textarea>
        @error('composition')
            <p class="text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<label class="flex items-start gap-3 rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 px-4 py-3">
    <input
        type="checkbox"
        name="is_active"
        value="1"
        @checked(old('is_active', $medicine->exists ? $medicine->is_active : true))
        class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-200"
    >

    <span>
        <span class="block text-sm font-semibold text-slate-900">Obat aktif</span>
        <span class="mt-1 block content-copy">
            Jika aktif, data obat akan langsung muncul di daftar dan siap dipakai pada proses pembelian, stok, dan penjualan.
        </span>
    </span>
</label>
