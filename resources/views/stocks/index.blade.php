<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
            <span>{{ $section }}</span>
            <span class="text-slate-300">/</span>
            <span class="text-slate-600">{{ $page['label'] }}</span>
        </div>
    </x-slot>

    <div
        x-data="{
            detailModalOpen: false,
            detailStock: null,
            today: @js(now()->toDateString()),
            detailDateFrom: '',
            detailDateTo: '',
            search: @js($search),
            stockState: @js($stockState ?? 'all'),
            expiryWithinMonths: @js($expiryWithinMonths ?? ''),
            locationId: @js($locationId ?? ''),
            mode: @js($mode),
            searchTimer: null,
            openDetail(detail) {
                this.detailStock = detail;
                this.detailDateFrom = this.today;
                this.detailDateTo = this.today;
                this.detailModalOpen = true;

                this.$nextTick(() => {
                    if (this.$refs.detailPanel) {
                        this.$refs.detailPanel.scrollTop = 0;
                    }
                });
            },
            closeDetail() {
                this.detailModalOpen = false;
                this.detailStock = null;
                this.detailDateFrom = '';
                this.detailDateTo = '';
            },
            queueSearch() {
                clearTimeout(this.searchTimer);
                this.searchTimer = setTimeout(() => this.$refs.searchForm?.requestSubmit(), 320);
            },
            submitFilters() {
                clearTimeout(this.searchTimer);
                this.$refs.searchForm?.requestSubmit();
            },
            resetFilters() {
                if (
                    this.search === ''
                    && this.locationId === ''
                    && (this.mode !== 'medicine' || this.stockState === 'all')
                    && (this.mode !== 'batch' || this.expiryWithinMonths === '')
                ) {
                    return;
                }

                clearTimeout(this.searchTimer);
                this.search = '';
                this.locationId = '';
                if (this.mode === 'medicine') {
                    this.stockState = 'all';
                }
                if (this.mode === 'batch') {
                    this.expiryWithinMonths = '';
                }
                this.$nextTick(() => this.$refs.searchForm?.requestSubmit());
            },
            resolvedMovementRange() {
                if (this.detailDateFrom !== '' && this.detailDateTo !== '' && this.detailDateFrom > this.detailDateTo) {
                    return {
                        from: this.detailDateTo,
                        to: this.detailDateFrom,
                    };
                }

                return {
                    from: this.detailDateFrom,
                    to: this.detailDateTo,
                };
            },
            hasMovementDateFilter() {
                return this.detailDateFrom !== '' || this.detailDateTo !== '';
            },
            resetMovementDateFilter() {
                this.detailDateFrom = '';
                this.detailDateTo = '';
            },
            filteredMovements() {
                const movements = Array.isArray(this.detailStock?.movements) ? this.detailStock.movements : [];
                const range = this.resolvedMovementRange();

                return movements.filter((movement) => {
                    const movementDate = String(movement.movement_date_value ?? '').trim();

                    if (movementDate === '') {
                        return ! this.hasMovementDateFilter();
                    }

                    if (range.from !== '' && movementDate < range.from) {
                        return false;
                    }

                    if (range.to !== '' && movementDate > range.to) {
                        return false;
                    }

                    return true;
                });
            },
            sumFilteredMovement(field) {
                return this.filteredMovements().reduce((total, movement) => {
                    const value = Number(movement[field] ?? 0);

                    return total + (Number.isFinite(value) ? value : 0);
                }, 0);
            },
            formatWholeNumber(value) {
                const parsed = Number(value ?? 0);

                if (! Number.isFinite(parsed)) {
                    return '0';
                }

                return new Intl.NumberFormat('id-ID', {
                    maximumFractionDigits: 0,
                }).format(parsed);
            },
            formatPeriodDate(value) {
                if (! value) {
                    return '';
                }

                const [year, month, day] = String(value).split('-');
                const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                const monthName = monthNames[(Number(month) || 1) - 1] ?? month;

                return `${day} ${monthName} ${year}`;
            },
            movementPeriodLabel() {
                const range = this.resolvedMovementRange();

                if (range.from !== '' && range.to !== '') {
                    return `Periode ${this.formatPeriodDate(range.from)} s.d. ${this.formatPeriodDate(range.to)}`;
                }

                if (range.from !== '') {
                    return `Riwayat mulai ${this.formatPeriodDate(range.from)}`;
                }

                if (range.to !== '') {
                    return `Riwayat sampai ${this.formatPeriodDate(range.to)}`;
                }

                return 'Semua riwayat stok';
            },
        }"
        @keydown.escape.window="closeDetail()"
        class="space-y-5"
    >
        <section class="panel-surface overflow-hidden p-0">
            <div class="border-b border-slate-200/80 px-5 py-4">
                <div class="flex flex-col gap-4">
                    <div>
                        <h3 class="section-title">{{ $page['label'] }}</h3>
                    </div>

                    <form method="GET" action="{{ route($page['route']) }}" x-ref="searchForm" class="flex w-full flex-col gap-2 lg:flex-row lg:items-center">
                        <div class="relative w-full min-w-0 flex-1 lg:min-w-[16rem]">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                    <circle cx="8.5" cy="8.5" r="5.25" />
                                    <path d="M12.5 12.5 17 17" />
                                </svg>
                            </span>

                            <input
                                name="search"
                                type="text"
                                x-model="search"
                                @input="queueSearch()"
                                placeholder="{{ $mode === 'medicine' ? 'Cari kode obat, nama obat, principal, batch, atau lokasi' : 'Cari batch, kode obat, supplier, faktur, atau lokasi' }}"
                                class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-11 text-[0.76rem] text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100"
                            >

                            <button
                                type="button"
                                x-cloak
                                x-show="search !== '' || locationId !== '' || (mode === 'medicine' && stockState !== 'all') || (mode === 'batch' && expiryWithinMonths !== '')"
                                @click="resetFilters()"
                                class="absolute inset-y-0 right-0 inline-flex items-center pr-3 text-[0.64rem] font-semibold uppercase tracking-[0.14em] text-slate-400 transition hover:text-slate-700"
                            >
                                Reset
                            </button>
                        </div>

                        @if ($mode === 'medicine')
                            <div class="inline-flex w-full shrink-0 items-center justify-end gap-2 lg:ml-auto lg:w-auto">
                                <select
                                    name="location_id"
                                    x-model="locationId"
                                    @change="submitFilters()"
                                    class="h-9 min-w-[11.75rem] rounded-xl border border-slate-200 bg-slate-50 px-3 pr-8 text-[0.72rem] font-medium text-slate-700 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100"
                                    style="min-width: 11.75rem; padding-right: 2rem;"
                                >
                                    <option value="">Semua lokasi</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                                <span class="text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">Filter</span>
                                <select
                                    name="stock_state"
                                    x-model="stockState"
                                    @change="submitFilters()"
                                    class="h-9 rounded-xl border border-slate-200 bg-slate-50 px-3 text-[0.72rem] font-medium text-slate-700 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100"
                                >
                                    <option value="all">Semua stok</option>
                                    <option value="available">Ada stok</option>
                                    <option value="low">Stok hampir habis</option>
                                    <option value="empty">Stok kosong</option>
                                </select>
                            </div>
                        @else
                            <div class="inline-flex w-full shrink-0 items-center justify-end gap-2 lg:ml-auto lg:w-auto">
                                <select
                                    name="location_id"
                                    x-model="locationId"
                                    @change="submitFilters()"
                                    class="h-9 min-w-[11.75rem] rounded-xl border border-slate-200 bg-slate-50 px-3 pr-8 text-[0.72rem] font-medium text-slate-700 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100"
                                    style="min-width: 11.75rem; padding-right: 2rem;"
                                >
                                    <option value="">Semua lokasi</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                                <span class="text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">Expired</span>
                                <select
                                    name="expiry_within_months"
                                    x-model="expiryWithinMonths"
                                    @change="submitFilters()"
                                    class="h-9 min-w-[10.5rem] rounded-xl border border-slate-200 bg-slate-50 px-3 text-[0.72rem] font-medium text-slate-700 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100"
                                >
                                    <option value="">Semua masa expired</option>
                                    @foreach (range(1, 12) as $month)
                                        <option value="{{ $month }}">{{ $month }} bulan</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </form>
                </div>
            </div>

            @if ($mode === 'medicine')
                <div class="overflow-x-auto">
                    <table class="min-w-[1010px] w-full divide-y divide-slate-200/80 text-[0.74rem]">
                        <thead class="bg-slate-50/90">
                            <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <th class="px-4 py-3">Kode</th>
                                <th class="px-3 py-3">Nama Obat</th>
                                <th class="px-2.5 py-3 w-[9rem]">Principal</th>
                                <th class="px-2.5 py-3 w-[7.25rem]">Satuan</th>
                                <th class="px-2.5 py-3 w-[4.75rem] text-center">Batch</th>
                                <th class="px-2.5 py-3 w-[7.5rem]">Expired Terdekat</th>
                                <th class="px-2.5 py-3 w-[6.5rem] text-right">Total Stok</th>
                                <th class="px-2.5 py-3 w-[7.5rem] text-right">Nilai Stok</th>
                                <th class="px-2 py-3 w-[4rem] text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 bg-white">
                            @forelse ($rows as $row)
                                <tr class="align-top">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-900">{{ $row->code }}</p>
                                        <p class="mt-1 text-[0.66rem] text-slate-400">ID {{ $row->medicine_id }}</p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <p class="font-semibold text-slate-900">{{ $row->name }}</p>
                                        <p class="mt-1 text-[0.66rem] text-slate-400">{{ $row->is_active ? 'Master aktif' : 'Master nonaktif' }}</p>
                                    </td>
                                    <td class="px-2.5 py-3 text-slate-700">{{ $row->principal_name ?: '-' }}</td>
                                    <td class="px-2.5 py-3 text-slate-700">{{ $row->large_unit ?: '-' }} / {{ $row->small_unit ?: '-' }}</td>
                                    <td class="px-2.5 py-3 text-center font-semibold text-slate-900">{{ number_format((int) $row->batch_count) }}</td>
                                    <td class="px-2.5 py-3 text-slate-700">
                                        {{ $row->nearest_expiry ? \Carbon\Carbon::parse($row->nearest_expiry)->translatedFormat('d M Y') : '-' }}
                                    </td>
                                    <td class="px-2.5 py-3 text-right">
                                        <div class="font-semibold text-slate-900">{{ number_format((float) $row->total_stock, 0, ',', '.') }}</div>
                                        @if ((float) $row->minimum_stock > 0)
                                            <div class="mt-1 text-[0.66rem] text-slate-400">Min {{ number_format((float) $row->minimum_stock, 0, ',', '.') }}</div>
                                        @endif
                                    </td>
                                    <td class="px-2.5 py-3 text-right font-semibold text-emerald-700">Rp {{ number_format((float) $row->stock_value, 0, ',', '.') }}</td>
                                    <td class="px-2 py-3 align-middle">
                                        <div class="table-action-group">
                                            <button
                                                type="button"
                                                @click="openDetail(@js($detailPayloads[$row->medicine_id] ?? null))"
                                                class="table-icon-btn"
                                                title="Lihat kartu stok {{ $row->name }}"
                                                aria-label="Lihat kartu stok {{ $row->name }}"
                                            >
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M8 7h8" />
                                                    <path d="M8 11h8" />
                                                    <path d="M8 15h5" />
                                                    <path d="M6 3h12a2 2 0 0 1 2 2v14l-4-2-4 2-4-2-4 2V5a2 2 0 0 1 2-2Z" />
                                                </svg>
                                                <span class="sr-only">Lihat kartu stok</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-5 py-14 text-center">
                                        <div class="mx-auto max-w-md space-y-3">
                                            <div class="empty-title">{{ $search !== '' || (($stockState ?? 'all') !== 'all') ? 'Obat tidak ditemukan' : 'Belum ada data obat' }}</div>
                                            <p class="content-copy">
                                                {{ $search !== '' || (($stockState ?? 'all') !== 'all') ? 'Coba ubah kata kunci atau filter stok untuk melihat data obat yang sesuai.' : 'Data akan muncul setelah master obat dibuat dan siap dipantau dari halaman stok.' }}
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-[980px] w-full divide-y divide-slate-200/80 text-[0.74rem]">
                        <thead class="bg-slate-50/90">
                            <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <th class="w-[8.5rem] px-3 py-3">Kode</th>
                                <th class="w-[10rem] px-2.5 py-3">Batch</th>
                                <th class="w-[14rem] px-2.5 py-3">Barang</th>
                                <th class="w-[7.5rem] px-2.5 py-3">Expired</th>
                                <th class="w-[6.5rem] px-2.5 py-3 text-right">Stok Sisa</th>
                                <th class="w-[8rem] px-2.5 py-3 text-right">Nilai Stok</th>
                                <th class="w-[4rem] px-2 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 bg-white">
                            @forelse ($rows as $batch)
                                <tr class="align-top">
                                    <td class="px-3 py-3">
                                        <p class="font-semibold text-slate-900">{{ $batch->medicine_code }}</p>
                                        <p class="mt-1 text-[0.66rem] text-slate-400">{{ $batch->invoice_label }}</p>
                                    </td>
                                    <td class="px-2.5 py-3">
                                        <p class="font-semibold text-slate-900">{{ $batch->batch_number }}</p>
                                        <p class="mt-1 text-[0.66rem] text-slate-400">{{ $batch->supplier_label }}</p>
                                    </td>
                                    <td class="px-2.5 py-3">
                                        <p class="font-semibold text-slate-900">{{ $batch->medicine_name }}</p>
                                        <p class="mt-1 text-[0.66rem] text-slate-400">{{ $batch->principal_name }}</p>
                                    </td>
                                    <td class="px-2.5 py-3 text-slate-700">{{ $batch->expiry_date_label }}</td>
                                    <td class="px-2.5 py-3 text-right font-semibold text-slate-900">{{ number_format((float) $batch->quantity_balance, 0, ',', '.') }}</td>
                                    <td class="px-2.5 py-3 text-right font-semibold text-emerald-700">Rp {{ number_format((float) $batch->stock_value, 0, ',', '.') }}</td>
                                    <td class="px-2 py-3 align-middle">
                                        <div class="table-action-group">
                                            <button
                                                type="button"
                                                @click="openDetail(@js($detailPayloads[$batch->row_key] ?? null))"
                                                class="table-icon-btn"
                                                title="Lihat detail batch {{ $batch->batch_number }}"
                                                aria-label="Lihat detail batch {{ $batch->batch_number }}"
                                            >
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M2.06 12.35a1 1 0 0 1 0-.7C3.2 8.38 6.52 5 12 5s8.8 3.38 9.94 6.65a1 1 0 0 1 0 .7C20.8 15.62 17.48 19 12 19s-8.8-3.38-9.94-6.65Z" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>
                                                <span class="sr-only">Lihat detail</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-14 text-center">
                                        <div class="mx-auto max-w-md space-y-3">
                                            <div class="empty-title">{{ $search !== '' || filled($expiryWithinMonths ?? '') ? 'Batch tidak ditemukan' : 'Belum ada stok batch aktif' }}</div>
                                            <p class="content-copy">
                                                {{ $search !== '' || filled($expiryWithinMonths ?? '') ? 'Coba ubah kata kunci atau rentang expired untuk melihat batch yang sesuai.' : 'Data akan muncul setelah penerimaan stok masuk dan masih memiliki saldo batch.' }}
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($rows->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4">
                    {{ $rows->links() }}
                </div>
            @endif
        </section>

        <div
            x-cloak
            x-show="detailModalOpen"
            x-transition.opacity
            class="fixed inset-0 z-40 overflow-y-auto bg-slate-950/50 backdrop-blur-sm"
            @click.self="closeDetail()"
        >
            <div class="flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6">
                <div x-ref="detailPanel" class="panel-surface relative z-50 max-h-[calc(100vh-2rem)] w-full max-w-4xl overflow-y-auto p-0 sm:max-h-[calc(100vh-3rem)]">
                    <div class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/95 px-5 py-4 backdrop-blur sm:px-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div
                                    class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
                                    :class="detailStock?.type === 'batch'
                                        ? 'border-sky-100 bg-sky-50 text-sky-700'
                                        : 'border-emerald-100 bg-emerald-50 text-emerald-700'"
                                    x-text="detailStock?.type === 'batch' ? 'Detail Batch' : 'Kartu Stok'"
                                ></div>

                                <div class="mt-3 flex flex-wrap items-center gap-3">
                                    <div class="min-w-0 flex-1">
                                        <h3 class="truncate text-lg font-semibold text-slate-950" x-text="detailStock?.name"></h3>
                                    </div>

                                    <template x-if="detailStock?.type === 'medicine'">
                                        <div class="inline-flex shrink-0 items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50/80 px-3 py-2 text-[0.78rem] font-semibold text-emerald-900 shadow-sm shadow-emerald-100/40">
                                            <span class="text-[0.62rem] font-semibold uppercase tracking-[0.16em] text-emerald-600">Total stok</span>
                                            <span x-text="detailStock?.total_stock_label"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <button type="button" class="table-icon-btn shrink-0" @click="closeDetail()" aria-label="Tutup detail">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                                    <path d="M6 6l12 12M18 6 6 18" />
                                </svg>
                            </button>
                        </div>

                        <template x-if="detailStock?.type === 'medicine'">
                            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-slate-200/80 pt-4">
                                <span class="text-[0.7rem] font-semibold uppercase tracking-[0.16em] text-slate-500">Filter tanggal</span>

                                <div class="flex flex-wrap items-center gap-3">
                                    <label class="inline-flex items-center gap-2">
                                        <span class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-slate-400">Dari</span>
                                        <input
                                            type="date"
                                            x-model="detailDateFrom"
                                            class="ui-control h-[35px] rounded-xl px-2 text-[0.72rem] shadow-sm focus:ring-2 sm:w-40"
                                        >
                                    </label>

                                    <label class="inline-flex items-center gap-2">
                                        <span class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-slate-400">Sampai</span>
                                        <input
                                            type="date"
                                            x-model="detailDateTo"
                                            class="ui-control h-[35px] rounded-xl px-2 text-[0.72rem] shadow-sm focus:ring-2 sm:w-40"
                                        >
                                    </label>

                                    <button
                                        type="button"
                                        x-show="hasMovementDateFilter()"
                                        x-cloak
                                        @click="resetMovementDateFilter()"
                                        class="inline-flex h-[35px] items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                                    >
                                        Reset
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="px-5 pb-5 pt-5 sm:px-6 sm:pb-6">
                        <template x-if="detailStock?.type === 'medicine'">
                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <h4 class="text-sm font-semibold text-slate-900">Riwayat kartu stok</h4>
                                        <p class="mt-1 text-[0.72rem] text-slate-500">
                                            Catatan masuk, keluar, dan retur per kode obat.
                                        </p>
                                    </div>
                                    <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[0.68rem] font-semibold text-slate-600">
                                        <span x-text="`${filteredMovements().length} riwayat`"></span>
                                    </div>
                                </div>

                                <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200/80">
                                    <table class="min-w-full divide-y divide-slate-200/80 text-[0.76rem]">
                                        <thead class="bg-slate-50/90 text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                            <tr>
                                                <th class="px-3 py-3">Tanggal</th>
                                                <th class="px-3 py-3">Status</th>
                                                <th class="px-3 py-3">Aktivitas</th>
                                                <th class="px-3 py-3">Batch</th>
                                                <th class="px-3 py-3">Referensi</th>
                                                <th class="px-3 py-3 text-right">Masuk</th>
                                                <th class="px-3 py-3 text-right">Keluar</th>
                                                <th class="px-3 py-3 text-right">Saldo</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-200/80 bg-white">
                                            <template x-if="filteredMovements().length === 0">
                                                <tr>
                                                    <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                                                        <span x-text="hasMovementDateFilter() ? 'Tidak ada riwayat pada periode yang dipilih.' : 'Belum ada riwayat mutasi stok untuk kode obat ini.'"></span>
                                                    </td>
                                                </tr>
                                            </template>
                                            <template x-for="movement in filteredMovements()" :key="movement.id">
                                                <tr>
                                                    <td class="px-3 py-3 text-slate-700" x-text="movement.movement_date"></td>
                                                    <td class="px-3 py-3">
                                                        <div class="inline-flex rounded-full border px-2.5 py-1 text-[0.64rem] font-semibold uppercase tracking-[0.14em]" :class="movement.status_class" x-text="movement.status_label"></div>
                                                    </td>
                                                    <td class="px-3 py-3">
                                                        <div class="inline-flex rounded-full border px-2.5 py-1 text-[0.64rem] font-semibold uppercase tracking-[0.14em]" :class="movement.type_class" x-text="movement.type_label"></div>
                                                    </td>
                                                    <td class="px-3 py-3">
                                                        <p class="font-semibold text-slate-900" x-text="movement.batch_number"></p>
                                                        <p class="mt-1 text-[0.66rem] text-slate-400" x-text="movement.location"></p>
                                                    </td>
                                                    <td class="px-3 py-3">
                                                        <p class="font-semibold text-slate-900" x-text="movement.reference"></p>
                                                        <p class="mt-1 text-[0.66rem] text-slate-400" x-text="movement.reference_detail"></p>
                                                    </td>
                                                    <td class="px-3 py-3 text-right font-semibold text-emerald-700" x-text="movement.quantity_in"></td>
                                                    <td class="px-3 py-3 text-right font-semibold text-rose-600" x-text="movement.quantity_out"></td>
                                                    <td class="px-3 py-3 text-right font-semibold text-slate-900" x-text="movement.running_balance"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                        <tfoot class="border-t border-slate-200/80 bg-slate-50/90">
                                            <tr>
                                                <td colspan="5" class="px-3 py-3 align-middle">
                                                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.16em] text-slate-400">Ringkasan periode</p>
                                                    <p class="mt-1 text-sm font-semibold text-slate-900" x-text="movementPeriodLabel()"></p>
                                                </td>
                                                <td class="px-3 py-3 text-right text-sm font-semibold text-emerald-900" x-text="formatWholeNumber(sumFilteredMovement('quantity_in_value'))"></td>
                                                <td class="px-3 py-3 text-right text-sm font-semibold text-rose-900" x-text="formatWholeNumber(sumFilteredMovement('quantity_out_value'))"></td>
                                                <td class="px-3 py-3"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </template>

                        <template x-if="detailStock?.type === 'batch'">
                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">No batch</p>
                                    <p class="mt-2 text-[0.82rem] font-semibold text-slate-900" x-text="detailStock?.batch_number"></p>
                                </div>

                                <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Supplier</p>
                                    <p class="mt-2 text-[0.82rem] font-semibold text-slate-900" x-text="detailStock?.supplier"></p>
                                </div>

                                <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Faktur</p>
                                    <p class="mt-2 text-[0.82rem] font-semibold text-slate-900">
                                        <span x-text="detailStock?.invoice_number"></span>
                                        <span class="mt-1 block text-[0.72rem] font-medium text-slate-500" x-text="detailStock?.invoice_date"></span>
                                    </p>
                                </div>

                                <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Lokasi</p>
                                    <p class="mt-2 text-[0.82rem] font-semibold text-slate-900" x-text="detailStock?.location"></p>
                                </div>

                                <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Tanggal terima</p>
                                    <p class="mt-2 text-[0.82rem] font-semibold text-slate-900" x-text="detailStock?.received_at"></p>
                                </div>

                                <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Expired</p>
                                    <p class="mt-2 text-[0.82rem] font-semibold text-slate-900" x-text="detailStock?.expiry_date"></p>
                                </div>

                                <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Qty masuk</p>
                                    <p class="mt-2 text-[0.82rem] font-semibold text-slate-900" x-text="detailStock?.quantity_in"></p>
                                </div>

                                <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Qty keluar</p>
                                    <p class="mt-2 text-[0.82rem] font-semibold text-slate-900" x-text="detailStock?.quantity_out"></p>
                                </div>

                                <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Saldo stok</p>
                                    <p class="mt-2 text-[0.82rem] font-semibold text-slate-900" x-text="detailStock?.quantity_balance"></p>
                                </div>

                                <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Harga beli</p>
                                    <p class="mt-2 text-[0.82rem] font-semibold text-slate-900" x-text="detailStock?.purchase_price"></p>
                                </div>

                                <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Nilai stok</p>
                                    <p class="mt-2 text-[0.82rem] font-semibold text-emerald-700" x-text="detailStock?.stock_value"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
