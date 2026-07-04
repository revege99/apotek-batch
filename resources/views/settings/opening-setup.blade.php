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
        })"
        class="space-y-5"
    >
        <section class="panel-surface overflow-visible p-0">
            <div class="border-b border-slate-200/80 px-4 py-3">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex flex-wrap items-center gap-2 text-[0.72rem]">
                        <div class="rounded-full bg-slate-100 px-3 py-2 font-semibold text-slate-700">
                            Rp {{ number_format($openingStockStats['value'], 0, ',', '.') }}
                        </div>

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

                <div class="border-b border-slate-200/80 px-4 py-3">
                    <input type="hidden" id="notes" name="notes" value="{{ old('notes') }}">

                    <div class="flex flex-wrap items-center gap-2.5 xl:flex-nowrap">
                        <div class="min-w-0 flex-1">
                            <input
                                x-model="searchTerm"
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
                                    @click="confirmCompanionRows()"
                                    title="Tambahkan baris batch lanjutan untuk obat yang sudah diisi"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 11l3 3L22 4" />
                                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                                    </svg>
                                </button>
                                <a href="{{ route('setup-saldo-awal.stok.riwayat') }}" class="ui-action-btn ui-action-btn--neutral w-full px-4 text-[0.74rem] sm:w-auto">
                                    Riwayat
                                </a>
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
                                <th class="w-[27%] px-1.5 py-2">Obat</th>
                                <th class="w-[10%] px-1 py-2 text-center">Batch</th>
                                <th class="w-[21%] px-1 py-2 text-center">Expired</th>
                                <th class="w-[8%] px-1 py-2 text-center">Qty Awal</th>
                                <th class="w-[16%] px-1 py-2 text-right">Harga Beli</th>
                                <th class="w-[18%] px-1.5 py-2 text-right">Nilai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 bg-white">
                            <template x-for="item in filteredRows()" :key="item.row.key">
                                <tr class="align-middle" :class="isCompanionPlaceholder(item.row) ? 'bg-emerald-50/35' : ''">
                                    <td class="px-1.5 py-1.5">
                                        <input type="hidden" :name="`items[${item.index}][medicine_id]`" x-model="item.row.medicine_id">
                                        <input type="hidden" :name="`items[${item.index}][storage_location_id]`" x-model="item.row.storage_location_id">
                                        <div>
                                            <div class="flex min-h-[35px] items-center rounded-xl border border-slate-200 bg-slate-50 px-2 text-[0.72rem] font-semibold text-slate-900">
                                                <span x-text="item.row.medicine_name || '-'"></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-1 py-1.5 text-center">
                                        <input
                                            :name="`items[${item.index}][batch_number]`"
                                            x-model="item.row.batch_number"
                                            type="text"
                                            class="ui-control mx-auto h-[35px] px-2 text-[0.72rem]"
                                            style="width: 4.6rem; min-width: 4.6rem; max-width: 4.6rem;"
                                            placeholder="No batch"
                                        >
                                    </td>
                                    <td class="px-1 py-1.5 text-center">
                                        <input
                                            :name="`items[${item.index}][expiry_date]`"
                                            x-model="item.row.expiry_date"
                                            type="date"
                                            class="ui-control mx-auto h-[35px] px-2 text-[0.72rem]"
                                            style="width: 8rem; min-width: 8rem; max-width: 8rem;"
                                        >
                                    </td>
                                    <td class="px-1 py-1.5 text-center">
                                        <input
                                            :name="`items[${item.index}][quantity]`"
                                            x-model="item.row.quantity"
                                            @input="handleNumericInput(item.index, 'quantity')"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="ui-control number-input-no-spinner mx-auto h-[35px] px-1.5 text-center text-[0.72rem]"
                                            style="width: 2.5rem; min-width: 2.5rem; max-width: 2.5rem;"
                                            placeholder="0"
                                        >
                                    </td>
                                    <td class="px-1 py-1.5 text-right">
                                        <input
                                            :name="`items[${item.index}][purchase_price]`"
                                            x-model="item.row.purchase_price"
                                            @input="handleNumericInput(item.index, 'purchase_price')"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="ui-control number-input-no-spinner ml-auto h-[35px] px-2 text-right text-[0.72rem]"
                                            style="width: 4.8rem; min-width: 4.8rem; max-width: 4.8rem;"
                                            placeholder="0"
                                        >
                                    </td>
                                    <td class="px-1.5 py-1.5 text-right font-semibold text-emerald-700">
                                        <input type="hidden" :name="`items[${item.index}][notes]`" x-model="item.row.notes">
                                        <input type="hidden" :name="`items[${item.index}][selling_price]`" x-model="item.row.selling_price">
                                        <span x-text="currency(rowValue(item.row))"></span>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredRows().length === 0">
                                <td colspan="6" class="px-4 py-14 text-center">
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
