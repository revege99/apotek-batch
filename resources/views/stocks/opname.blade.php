<x-app-layout>
    @php
        $currentLocationId = $locationId ?? null;
        $worksheetRows = $rows->values()->map(function (array $row, int $index): array {
            $row['original_index'] = $index;
            $row['physical_quantity'] = old('items.'.$index.'.physical_quantity');

            return $row;
        });
    @endphp

    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
            <span>{{ $section }}</span>
            <span class="text-slate-300">/</span>
            <span class="text-slate-600">{{ $page['label'] }}</span>
        </div>
    </x-slot>

    <div
        x-data="{
            rows: @js($worksheetRows),
            searchTerm: @js($search),
            saveConfirmOpen: false,
            deleteModalOpen: false,
            deleteFormAction: '',
            deleteTarget: null,
            submitFilter() {
                this.$refs.filterForm?.requestSubmit();
            },
            normalizedSearchTerm() {
                return String(this.searchTerm ?? '').trim().toLocaleLowerCase('id-ID');
            },
            rowMatchesSearch(row) {
                const query = this.normalizedSearchTerm();

                if (query === '') {
                    return true;
                }

                return [
                    row.medicine_code,
                    row.medicine_name,
                    row.principal_name,
                    row.batch_summary,
                    row.location_name,
                ].some((value) => String(value ?? '').toLocaleLowerCase('id-ID').includes(query));
            },
            filteredRows() {
                return this.rows.filter((row) => this.rowMatchesSearch(row));
            },
            openSaveConfirm() {
                this.saveConfirmOpen = true;

                this.$nextTick(() => {
                    this.$refs.confirmSaveButton?.focus();
                });
            },
            closeSaveConfirm() {
                this.saveConfirmOpen = false;
            },
            submitOpnameForm() {
                this.saveConfirmOpen = false;

                if (typeof this.$refs.stockOpnameForm?.requestSubmit === 'function') {
                    this.$refs.stockOpnameForm.requestSubmit();
                    return;
                }

                this.$refs.stockOpnameForm?.submit();
            },
            formatNumber(value) {
                const parsed = Number(value ?? 0);
                if (! Number.isFinite(parsed)) {
                    return '0';
                }

                return new Intl.NumberFormat('id-ID', {
                    maximumFractionDigits: 0,
                }).format(parsed);
            },
            formatCurrency(value) {
                const parsed = Number(value ?? 0);
                if (! Number.isFinite(parsed)) {
                    return 'Rp 0';
                }

                return `Rp ${new Intl.NumberFormat('id-ID', {
                    maximumFractionDigits: 0,
                }).format(parsed)}`;
            },
            isCounted(row) {
                return row?.physical_quantity !== '' && row?.physical_quantity !== null && row?.physical_quantity !== undefined;
            },
            physicalValue(row) {
                const value = row?.physical_quantity;
                if (value === null || value === '' || value === undefined) {
                    return '';
                }

                return value;
            },
            systemValue(row) {
                return Number(row?.system_quantity ?? 0);
            },
            difference(row) {
                const physical = Number(row?.physical_quantity ?? NaN);
                if (! Number.isFinite(physical)) {
                    return 0;
                }

                return physical - this.systemValue(row);
            },
            more(row) {
                return Math.max(this.difference(row), 0);
            },
            less(row) {
                return Math.max(this.difference(row) * -1, 0);
            },
            adjustment(row) {
                const difference = this.difference(row);
                const purchasePrice = Number(row?.purchase_price ?? 0);

                return difference * purchasePrice;
            },
            countedRows() {
                return this.rows.filter((row) => this.isCounted(row)).length;
            },
            totalMore() {
                return this.rows.reduce((total, row) => total + this.more(row), 0);
            },
            totalLess() {
                return this.rows.reduce((total, row) => total + this.less(row), 0);
            },
            rapikanRows() {
                this.rows = [...this.rows].sort((firstRow, secondRow) => {
                    const firstCounted = this.isCounted(firstRow) ? 0 : 1;
                    const secondCounted = this.isCounted(secondRow) ? 0 : 1;

                    if (firstCounted !== secondCounted) {
                        return firstCounted - secondCounted;
                    }

                    return Number(firstRow.original_index ?? 0) - Number(secondRow.original_index ?? 0);
                });
            },
            focusNextPhysicalInput(index) {
                const inputs = Array.from(this.$root.querySelectorAll('[data-opname-physical-input]'));
                const targetIndex = Math.min(index + 1, Math.max(inputs.length - 1, 0));
                const target = inputs[targetIndex] ?? null;

                if (! target) {
                    return;
                }

                target.focus();
                target.select?.();
            },
            openDeleteDialog(payload = {}) {
                this.deleteTarget = {
                    title: payload.title ?? 'Hapus hasil stok opname ini?',
                    description: payload.description ?? 'Dokumen stok opname ini akan dihapus dari riwayat.',
                    warning: payload.warning ?? 'Hapus hanya jika hasil audit ini memang sudah tidak dipakai lagi sebagai pembanding.',
                    confirm_label: payload.confirm_label ?? 'Ya, hapus hasil opname',
                    name: payload.name ?? '',
                    code: payload.code ?? '',
                };
                this.deleteFormAction = payload.action ?? '';
                this.deleteModalOpen = true;

                this.$nextTick(() => {
                    this.$refs.cancelDeleteButton?.focus();
                });
            },
            closeDeleteDialog() {
                this.deleteModalOpen = false;
                this.deleteFormAction = '';
                this.deleteTarget = null;
            },
        }"
        @keydown.escape.window="closeDeleteDialog(); closeSaveConfirm()"
        class="space-y-5"
    >
        @if ($errors->has('items'))
            <section class="panel-surface px-4 py-3">
                <p class="text-[0.78rem] font-medium text-rose-700">{{ $errors->first('items') }}</p>
            </section>
        @endif

        <section class="panel-surface overflow-visible p-0">
            <div class="border-b border-slate-200/80 px-4 py-3">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex flex-wrap items-center gap-2 text-[0.72rem]">
                        <div class="rounded-full bg-emerald-50 px-3 py-2 font-semibold text-emerald-700">
                            Dicek <span x-text="formatNumber(countedRows())"></span>
                        </div>
                        <div class="rounded-full bg-sky-50 px-3 py-2 font-semibold text-sky-700">
                            Lebih <span x-text="formatNumber(totalMore())"></span>
                        </div>
                        <div class="rounded-full bg-rose-50 px-3 py-2 font-semibold text-rose-700">
                            Hilang <span x-text="formatNumber(totalLess())"></span>
                        </div>
                    </div>

                    <form x-ref="filterForm" id="stock-opname-filter-form" method="GET" action="{{ route('stok-batch.stok-opname') }}" class="flex flex-wrap items-center justify-end gap-2">
                        <a
                            href="{{ route('stok-batch.stok-opname.draft') }}"
                            class="ui-action-btn ui-action-btn--soft px-3 text-[0.74rem]"
                        >
                            Draft terbaru
                        </a>

                        <div class="w-[16rem]">
                            <label for="notes" class="sr-only">Catatan</label>
                            <input
                                id="notes"
                                name="notes"
                                form="stock-opname-form"
                                type="text"
                                value="{{ old('notes') }}"
                                placeholder="Catatan umum stok opname"
                                class="ui-control px-3 text-[0.74rem]"
                            >
                        </div>

                        <div class="w-[10rem]">
                            <label for="location_id" class="sr-only">Lokasi</label>
                            <select id="location_id" name="location_id" @change="submitFilter()" class="ui-select-control px-3 text-[0.74rem]">
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected($currentLocationId === $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <form x-ref="stockOpnameForm" id="stock-opname-form" method="POST" action="{{ route('stok-batch.stok-opname.store') }}" class="flex flex-col">
                @csrf

                <div class="border-b border-slate-200/80 px-4 py-3">
                    <div class="flex flex-wrap items-center gap-2.5 xl:flex-nowrap">
                        <div class="flex w-full items-center gap-2 sm:w-auto">
                            <label for="opname_number" class="shrink-0 text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">No opname</label>
                            <input
                                id="opname_number"
                                name="opname_number"
                                type="text"
                                value="{{ old('opname_number', $defaultOpnameNumber) }}"
                                class="ui-control w-full px-3 text-[0.74rem] sm:w-[11.5rem]"
                            >
                        </div>

                        <div class="flex w-full items-center gap-2 sm:w-auto">
                            <label for="opname_date" class="shrink-0 text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">Tanggal</label>
                            <input
                                id="opname_date"
                                name="opname_date"
                                type="date"
                                value="{{ old('opname_date', $defaultOpnameDate) }}"
                                class="ui-control w-full px-3 text-[0.74rem] sm:w-[9.25rem]"
                            >
                        </div>

                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <label for="search" class="shrink-0 text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">Cari obat</label>
                            <div class="flex min-w-0 flex-1 items-center gap-1.5">
                                <input
                                    id="search"
                                    type="text"
                                    x-model="searchTerm"
                                    placeholder="Cari obat, batch, principal, lokasi"
                                    class="ui-control min-w-0 flex-1 px-3 text-[0.74rem]"
                                >
                                <button
                                    type="button"
                                    @click="rapikanRows()"
                                    class="ui-action-btn ui-action-btn--neutral shrink-0 px-3"
                                    title="Rapikan baris yang sudah diisi"
                                    aria-label="Rapikan baris stok opname"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 11l3 3L22 4" />
                                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="w-full sm:w-auto">
                            <button type="button" @click="openSaveConfirm()" class="ui-action-btn ui-action-btn--soft w-full px-4 text-[0.74rem] sm:w-auto">
                                Simpan Draft
                            </button>
                        </div>
                    </div>

                    @if ($errors->has('opname_number') || $errors->has('opname_date'))
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                            @error('opname_number')
                                <p class="text-[0.68rem] text-rose-600">{{ $message }}</p>
                            @enderror
                            @error('opname_date')
                                <p class="text-[0.68rem] text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                </div>

                <div>
                    <table class="min-w-[1040px] w-full divide-y divide-slate-200/80 text-[0.72rem]">
                        <thead class="bg-slate-50/95">
                            <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <th class="sticky z-10 bg-slate-50/95 px-3 py-2 shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur" style="top: -20px;">Kode</th>
                                <th class="sticky z-10 bg-slate-50/95 px-2.5 py-2 shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur" style="top: -20px;">Obat</th>
                                <th class="sticky z-10 bg-slate-50/95 px-2.5 py-2 shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur" style="top: -20px;">Ringkasan Batch</th>
                                <th class="sticky z-10 bg-slate-50/95 px-2.5 py-2 shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur" style="top: -20px;">Lokasi</th>
                                <th class="sticky z-10 bg-slate-50/95 px-2.5 py-2 text-center shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur" style="top: -20px;">Stok Sistem</th>
                                <th class="sticky z-10 bg-slate-50/95 px-2.5 py-2 text-center shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur" style="top: -20px;">Stok Fisik</th>
                                <th class="sticky z-10 bg-slate-50/95 px-2.5 py-2 text-center shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur" style="top: -20px;">Lebih</th>
                                <th class="sticky z-10 bg-slate-50/95 px-2.5 py-2 text-center shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur" style="top: -20px;">Hilang</th>
                                <th class="sticky z-10 bg-slate-50/95 px-2.5 py-2 text-right shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur" style="top: -20px;">Nilai Selisih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 bg-white">
                            <template x-if="filteredRows().length === 0">
                                <tr>
                                    <td colspan="9" class="px-5 py-14 text-center">
                                        <div class="mx-auto max-w-md space-y-3">
                                            <div class="empty-title">Obat stok tidak ditemukan</div>
                                            <p class="content-copy">
                                                Coba ubah kata kunci atau filter lokasi untuk menampilkan lembar kerja stok opname per obat.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <template x-for="(row, index) in filteredRows()" :key="`${row.original_index}-${row.medicine_id}`">
                                <tr class="align-middle" :class="Number(row.system_quantity ?? 0) <= 0 ? 'bg-amber-50/40' : ''">
                                    <td class="px-3 py-2">
                                        <div class="font-semibold text-slate-900" x-text="row.medicine_code"></div>
                                    </td>
                                    <td class="px-2.5 py-2">
                                        <div class="font-semibold text-slate-900" x-text="row.medicine_name"></div>
                                    </td>
                                    <td class="px-2.5 py-2">
                                        <div class="font-semibold text-slate-900" x-text="`${row.batch_count} batch`"></div>
                                    </td>
                                    <td class="px-2.5 py-2 text-slate-700" x-text="row.location_name"></td>
                                    <td class="px-2.5 py-2 text-center">
                                        <div class="font-semibold text-slate-900" x-text="row.system_quantity_label"></div>
                                    </td>
                                    <td class="px-2.5 py-2">
                                        <input type="hidden" :name="`items[${row.original_index}][stock_batch_id]`" :value="row.stock_batch_id">
                                        <input type="hidden" :name="`items[${row.original_index}][medicine_id]`" :value="row.medicine_id">
                                        <input type="hidden" :name="`items[${row.original_index}][storage_location_id]`" :value="row.storage_location_id">
                                        <input type="hidden" :name="`items[${row.original_index}][system_quantity]`" :value="row.system_quantity">
                                        <input type="hidden" :name="`items[${row.original_index}][average_unit_cost]`" :value="row.purchase_price">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            :name="`items[${row.original_index}][physical_quantity]`"
                                            x-model="row.physical_quantity"
                                            data-opname-physical-input
                                            class="ui-control number-input-no-spinner mx-auto h-8 w-24 px-2 text-center text-[0.72rem]"
                                            @keydown.enter.prevent="focusNextPhysicalInput(index)"
                                        >
                                    </td>
                                    <td class="px-2.5 py-2 text-center font-semibold text-sky-700" x-text="formatNumber(more(row))"></td>
                                    <td class="px-2.5 py-2 text-center font-semibold text-rose-700" x-text="formatNumber(less(row))"></td>
                                    <td class="px-2.5 py-2 text-right font-semibold" :class="adjustment(row) < 0 ? 'text-rose-700' : 'text-emerald-700'" x-text="formatCurrency(adjustment(row))"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </form>
        </section>

        <div
            x-cloak
            x-show="saveConfirmOpen"
            x-transition.opacity
            class="fixed inset-0 z-40 overflow-y-auto bg-slate-950/50 backdrop-blur-sm"
            @click.self="closeSaveConfirm()"
        >
            <div class="flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6">
                <div class="panel-surface relative z-50 w-full max-w-lg p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="inline-flex rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                                Konfirmasi Simpan
                            </div>
                            <h3 class="mt-3 text-lg font-semibold text-slate-950">Data stok opname sudah benar?</h3>
                            <p class="mt-1 text-xs text-slate-500">Periksa lagi stok fisik yang sudah diisi sebelum draft stok opname disimpan.</p>
                        </div>

                        <button type="button" class="table-icon-btn" @click="closeSaveConfirm()" aria-label="Tutup konfirmasi simpan">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                                <path d="M6 6l12 12M18 6 6 18" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-[0.78rem] text-slate-700">
                        Draft akan menyimpan hasil cek sementara sesuai lokasi yang sedang dipilih.
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="ui-action-btn ui-action-btn--neutral px-4" @click="closeSaveConfirm()">
                            Cek lagi
                        </button>
                        <button x-ref="confirmSaveButton" type="button" class="ui-action-btn border border-emerald-300 bg-emerald-500 px-4 text-white hover:bg-emerald-600" @click="submitOpnameForm()">
                            Ya, simpan draft
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <x-master-delete-modal />
    </div>
</x-app-layout>
