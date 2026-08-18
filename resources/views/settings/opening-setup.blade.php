<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
            <span>{{ $section }}</span>
            <span class="text-slate-300">/</span>
            <span class="text-slate-600">{{ $page['label'] }}</span>
        </div>
    </x-slot>

    <div
        x-data="openingStockSetupForm({
            initialRows: @js($initialRows),
            locationOptions: @js($locationOptions),
            initialLocationId: @js($initialLocationId),
            defaultExpiryDate: @js(now()->toDateString()),
        })"
        class="space-y-5"
    >
        <section class="panel-surface overflow-visible p-0">
            <div class="border-b border-slate-200/80 px-4 py-3">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex flex-wrap items-center gap-2 text-[0.72rem]">
                        <div class="flex items-center gap-2">
                            <label for="entry_number" class="text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-500">No dokumen</label>
                            <input
                                id="entry_number"
                                name="entry_number"
                                form="opening-stock-form"
                                type="text"
                                value="{{ old('entry_number', $defaultEntryNumber) }}"
                                class="ui-control h-[35px] w-[11.5rem] border-white bg-white px-3 text-[0.74rem]"
                            >
                        </div>

                        <div class="flex items-center gap-2">
                            <label for="opening_date" class="text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Tanggal</label>
                            <input
                                id="opening_date"
                                name="opening_date"
                                form="opening-stock-form"
                                type="date"
                                value="{{ old('opening_date', $defaultOpeningDate) }}"
                                class="ui-control h-[35px] w-[9.25rem] border-white bg-white px-3 text-[0.74rem]"
                            >
                        </div>

                        <div class="flex items-center gap-2">
                            <label for="storage_location_id" class="text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Lokasi</label>
                            <select
                                id="storage_location_id"
                                name="storage_location_id"
                                form="opening-stock-form"
                                x-model="selectedLocationId"
                                @change="syncSelectedLocation()"
                                class="ui-select-control h-[35px] w-[11rem] border-white bg-white px-3 text-[0.74rem]"
                            >
                                <option value="">Pilih lokasi</option>
                                @foreach ($locationOptions as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="inline-flex rounded-full border border-emerald-100 bg-emerald-50 px-3 py-2 text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-emerald-700">
                        Superadmin only
                    </div>
                </div>
            </div>

            <form id="opening-stock-form" method="POST" action="{{ route('setup-saldo-awal.stok.store') }}" class="flex flex-col">
                @csrf
                <input type="hidden" name="items_payload" :value="submissionPayload()">

                <div class="border-b border-slate-200/80 px-4 py-3">
                    <input type="hidden" id="notes" name="notes" value="{{ old('notes') }}">

                    <div class="flex flex-wrap items-center gap-2.5 xl:flex-nowrap">
                        <div class="min-w-0 flex-1">
                            <input
                                x-model.debounce.150ms="searchTerm"
                                type="text"
                                placeholder="Cari nama obat atau kode obat"
                                class="ui-control w-full px-3 text-[0.74rem]"
                            >
                        </div>

                        <div class="w-full sm:w-auto">
                            <div class="flex w-full flex-wrap gap-2 sm:w-auto">
                                <button
                                    type="button"
                                    class="ui-action-btn ui-action-btn--neutral w-full px-3 text-[0.74rem] sm:w-auto"
                                    @click="setAllMedicineQuantities(1000)"
                                    title="Isi Qty Awal seluruh obat menjadi 1000"
                                >
                                    Set semua Qty 1000
                                </button>
                                <button
                                    type="button"
                                    class="ui-action-btn ui-action-btn--neutral w-full px-3 text-[0.74rem] sm:w-auto"
                                    @click="confirmCompanionRows()"
                                    title="Tambahkan baris batch lanjutan untuk obat yang sudah diisi"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 11l3 3L22 4" />
                                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                                    </svg>
                                </button>
                                <button type="submit" class="ui-action-btn ui-action-btn--soft w-full px-4 text-[0.74rem] sm:w-auto">
                                    Simpan saldo awal
                                </button>
                            </div>
                        </div>
                    </div>

                    @if ($errors->has('entry_number') || $errors->has('opening_date') || $errors->has('storage_location_id'))
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                            @error('entry_number')
                                <p class="text-[0.68rem] text-rose-600">{{ $message }}</p>
                            @enderror
                            @error('opening_date')
                                <p class="text-[0.68rem] text-rose-600">{{ $message }}</p>
                            @enderror
                            @error('storage_location_id')
                                <p class="text-[0.68rem] text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    @if ($errors->has('items'))
                        <div class="mt-2">
                            <p class="text-[0.68rem] text-rose-600">{{ $errors->first('items') }}</p>
                        </div>
                    @endif
                </div>

                <div class="overflow-x-hidden">
                    <table class="w-full table-fixed divide-y divide-slate-200/80 text-[0.72rem]">
                        <thead class="bg-slate-50/95">
                            <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <th class="w-[34%] px-1.5 py-2">Obat</th>
                                <th class="w-[17%] px-1 py-2 text-center">Batch</th>
                                <th class="w-[21%] px-1 py-2 text-center">Expired</th>
                                <th class="w-[12%] px-1 py-2 text-center">Qty Awal</th>
                                <th class="w-[16%] px-1.5 py-2 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 bg-white">
                            <template x-for="item in renderedRows" :key="item.row.key">
                                <tr
                                    class="align-middle"
                                    :class="isCompanionPlaceholder(item.row) ? 'bg-emerald-50/35' : ''"
                                    style="content-visibility: auto; contain-intrinsic-size: 44px;"
                                >
                                    <td class="px-1.5 py-1.5">
                                        <div class="px-1 text-[0.72rem] font-semibold text-slate-900" x-text="item.row.medicine_name || '-'"></div>
                                    </td>
                                    <td class="px-1 py-1.5 text-center">
                                        <input
                                            x-model="item.row.batch_number"
                                            @input="markRowForSubmit(item.index)"
                                            type="text"
                                            class="ui-control mx-auto !h-[35px] min-h-[35px] max-h-[35px] px-2 text-[0.72rem]"
                                            style="width: 4.6rem; min-width: 4.6rem; max-width: 4.6rem;"
                                            placeholder="Opsional"
                                        >
                                    </td>
                                    <td class="px-1 py-1.5 text-center">
                                        <input
                                            x-model="item.row.expiry_date"
                                            @change="markRowForSubmit(item.index)"
                                            type="date"
                                            class="ui-control mx-auto !h-[35px] min-h-[35px] max-h-[35px] px-2 text-[0.72rem]"
                                            style="width: 8rem; min-width: 8rem; max-width: 8rem;"
                                        >
                                    </td>
                                    <td class="px-1 py-1.5 text-center">
                                        <input
                                            x-model="item.row.quantity"
                                            @input="handleNumericInput(item.index, 'quantity')"
                                            type="number"
                                            min="0"
                                            step="1"
                                            class="ui-control number-input-no-spinner mx-auto !h-[35px] min-h-[35px] max-h-[35px] px-1.5 text-center text-[0.72rem]"
                                            style="width: 3.5rem; min-width: 3.5rem; max-width: 3.5rem;"
                                            placeholder="0"
                                        >
                                    </td>
                                    <td class="px-1.5 py-1.5 text-center">
                                        <span
                                            class="inline-flex rounded-full border px-2.5 py-1 text-[0.66rem] font-semibold uppercase tracking-[0.12em]"
                                            :class="item.row.is_active
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                : 'border-slate-200 bg-slate-100 text-slate-500'"
                                            x-text="item.row.is_active ? 'Aktif' : 'Nonaktif'"
                                        ></span>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="visibleRows.length === 0">
                                <td colspan="5" class="px-4 py-14 text-center">
                                    <div class="mx-auto max-w-md space-y-3">
                                        <div class="empty-title">Obat tidak ditemukan</div>
                                        <p class="content-copy">Coba ubah kata kunci pencarian untuk menampilkan obat yang ingin diisi saldo awalnya.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </form>
        </section>
    </div>
</x-app-layout>
