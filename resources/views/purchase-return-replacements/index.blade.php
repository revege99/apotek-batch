@php
    $showFormSection = $showFormSection ?? true;
    $showHistorySection = $showHistorySection ?? false;
    $isHistoryPage = $showHistorySection && ! $showFormSection;
    $moduleConfig = $moduleConfig ?? [
        'form_route' => 'pembelian.realisasi-pengganti-retur',
        'history_route' => 'pembelian.riwayat-realisasi-pengganti-retur',
        'store_route' => 'pembelian.realisasi-pengganti-retur.store',
        'destroy_route' => 'pembelian.realisasi-pengganti-retur.destroy',
        'switch_to_form_label' => 'Form Realisasi',
        'switch_to_history_label' => 'Riwayat Realisasi',
        'form_title' => 'Form realisasi pengganti retur',
        'history_title' => 'Riwayat realisasi pengganti retur',
        'detail_title' => 'Detail Realisasi',
        'entry_lower' => 'realisasi pengganti retur',
        'submit_label' => 'Simpan realisasi pengganti',
        'delete_title' => 'Hapus realisasi pengganti retur ini?',
    ];

    $returnLookupOptions = $showFormSection
        ? $returnOptions
            ->map(function ($purchaseReturn) {
                $supplierName = $purchaseReturn->supplier?->name ?: '-';

                return [
                    'id' => $purchaseReturn->id,
                    'invoice_number' => $purchaseReturn->return_number,
                    'supplier_name' => $supplierName,
                    'invoice_date' => $purchaseReturn->return_date?->translatedFormat('d M Y') ?? '-',
                    'label' => $purchaseReturn->return_number.' - '.$supplierName,
                ];
            })
            ->values()
        : collect();

    $selectedReturnLookupLabel = $showFormSection && $selectedReturn
        ? $selectedReturn->return_number.' - '.($selectedReturn->supplier?->name ?: '-')
        : '';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $section }}</span>
                <span class="text-slate-300">/</span>
                <span class="text-slate-600">{{ $page['label'] }}</span>
            </div>

            <a
                href="{{ $isHistoryPage ? route($moduleConfig['form_route'], $selectedReturnId ? ['purchase_return_id' => $selectedReturnId] : []) : route($moduleConfig['history_route'], $selectedReturnId ? ['purchase_return_id' => $selectedReturnId] : []) }}"
                class="ui-action-btn ui-action-btn--soft px-4"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                    <path d="M3.333 5.833h13.334M3.333 10h13.334M3.333 14.167h13.334" />
                </svg>
                {{ $isHistoryPage ? $moduleConfig['switch_to_form_label'] : $moduleConfig['switch_to_history_label'] }}
            </a>
        </div>
    </x-slot>

    <div
        x-data="{
            detailModalOpen: false,
            detailReplacement: null,
            deleteModalOpen: false,
            deleteFormAction: '',
            deleteTarget: null,
            openDetail(detail) {
                this.detailReplacement = detail;
                this.detailModalOpen = true;
            },
            closeDetail() {
                this.detailModalOpen = false;
                this.detailReplacement = null;
            },
            openDeleteDialog(payload = {}) {
                this.deleteTarget = {
                    title: payload.title ?? @js($moduleConfig['delete_title']),
                    description: payload.description ?? 'Realisasi yang dihapus akan menarik kembali stok pengganti dari batch asal.',
                    warning: payload.warning ?? 'Pastikan stok pengganti dari realisasi ini memang belum dipakai ke transaksi lain.',
                    confirm_label: payload.confirm_label ?? 'Ya, hapus realisasi',
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
        @keydown.escape.window="closeDetail(); closeDeleteDialog()"
        class="space-y-5"
    >
        @if ($showFormSection)
        <section class="panel-surface overflow-hidden p-0">
            <div class="border-b border-slate-200/80 px-4 py-3">
                <div class="flex flex-col gap-2.5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="section-title">{{ $moduleConfig['form_title'] }}</h3>
                        <p class="mt-0.5 text-[0.72rem] text-slate-500">
                            Cari nomor retur, tampilkan item yang belum selesai diganti, lalu tambahkan stok kembali ke batch asal dalam satuan kecil.
                        </p>
                    </div>

                    @if ($returnOptions->isNotEmpty())
                        <div class="flex w-full max-w-2xl flex-col gap-2 sm:flex-row sm:items-start sm:justify-end">
                            <form
                                x-data="purchaseReturnInvoicePicker({
                                    options: @js($returnLookupOptions),
                                    selectedId: @js($selectedReturnId),
                                    selectedLabel: @js($selectedReturnLookupLabel),
                                })"
                                method="GET"
                                action="{{ route($moduleConfig['form_route']) }}"
                                @click.outside="close()"
                                class="relative w-full max-w-xl"
                            >
                                <label class="sr-only" for="purchase_return_search">Cari nomor retur pembelian</label>
                                <input type="hidden" name="purchase_return_id" x-model="selectedId">

                                <div class="relative">
                                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                                        <circle cx="11" cy="11" r="6" />
                                        <path d="m16 16 4 4" />
                                    </svg>

                                    <input
                                        id="purchase_return_search"
                                        x-ref="searchInput"
                                        type="search"
                                        x-model="query"
                                        autocomplete="off"
                                        placeholder="Cari no retur atau supplier"
                                        @focus="open()"
                                        @click="open()"
                                        @input="handleInput()"
                                        @keydown.arrow-down.prevent="highlightNext()"
                                        @keydown.arrow-up.prevent="highlightPrevious()"
                                        @keydown.enter.prevent="confirmHighlighted()"
                                        class="ui-control pl-9 pr-9 text-[0.76rem]"
                                    >

                                    <button
                                        x-cloak
                                        x-show="query !== ''"
                                        type="button"
                                        @click="clearSelection()"
                                        class="absolute right-2 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                                        aria-label="Bersihkan pencarian nomor retur"
                                    >
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                                            <path d="M6 6l12 12M18 6 6 18" />
                                        </svg>
                                    </button>
                                </div>

                                <div
                                    x-cloak
                                    x-show="isOpen"
                                    class="absolute inset-x-0 z-20 mt-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60"
                                >
                                    <div class="max-h-72 overflow-y-auto py-2">
                                        <template x-if="filteredOptions().length === 0">
                                            <div class="px-3 py-4 text-[0.72rem] text-slate-500">
                                                Nomor retur tidak ditemukan.
                                            </div>
                                        </template>

                                        <template x-for="(option, index) in filteredOptions()" :key="option.id">
                                            <button
                                                type="button"
                                                @mousedown.prevent="selectOption(option)"
                                                @mousemove="highlightedIndex = index"
                                                class="flex w-full items-start gap-3 px-3 py-2 text-left transition"
                                                :class="index === highlightedIndex ? 'bg-emerald-50 text-emerald-900' : 'text-slate-700 hover:bg-slate-50'"
                                            >
                                                <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-[0.62rem] font-semibold uppercase tracking-[0.12em] text-slate-500">
                                                    RTB
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block truncate text-[0.76rem] font-semibold" x-text="option.invoice_number"></span>
                                                    <span class="mt-0.5 block truncate text-[0.68rem] text-slate-500" x-text="`${option.supplier_name} / ${option.invoice_date}`"></span>
                                                </span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </form>

                            @if ($selectedReturnId)
                                <a
                                    href="{{ route($moduleConfig['form_route']) }}"
                                    class="ui-action-btn ui-action-btn--neutral"
                                >
                                    Reset
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            @if ($returnOptions->isEmpty())
                <div class="px-5 py-14 text-center">
                    <div class="mx-auto max-w-md space-y-3">
                        <div class="empty-title">Belum ada retur yang menunggu pengganti</div>
                        <p class="content-copy">
                            Buat retur pembelian lebih dulu atau pastikan masih ada item retur yang belum selesai direalisasikan penggantinya.
                        </p>
                    </div>
                </div>
            @elseif ($selectedReturn === null)
                <div class="px-5 py-14 text-center">
                    <div class="mx-auto max-w-md space-y-3">
                        <div class="empty-title">Cari dan pilih nomor retur terlebih dahulu</div>
                        <p class="content-copy">
                            Setelah nomor retur dipilih dari pencarian di atas, item yang masih punya sisa pengganti akan langsung tampil dan siap diisi kembali ke stok batch asal.
                        </p>
                    </div>
                </div>
            @else
                <div x-data="purchaseReturnForm(@js($initialForm))" class="space-y-0">
                    <div class="border-b border-slate-200/80 px-4 py-2.5">
                        <div class="flex flex-wrap items-center gap-2.5 text-[0.76rem]">
                            <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-2">
                                <span class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-slate-500">No realisasi</span>
                                <span class="font-semibold text-slate-900" x-text="return_number"></span>
                            </div>

                            <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-2">
                                <span class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-slate-500">No retur</span>
                                <span class="font-semibold text-slate-900">{{ $selectedReturn->return_number }}</span>
                                <span class="text-[0.68rem] text-slate-400">{{ $selectedReturn->return_date?->translatedFormat('d M Y') ?? '-' }}</span>
                            </div>

                            <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-2">
                                <span class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Faktur</span>
                                <span class="font-semibold text-slate-900">{{ $selectedReturn->purchaseInvoice?->invoice_number ?: '-' }}</span>
                                <span class="text-[0.68rem] text-slate-400">{{ $selectedReturn->purchaseInvoice?->invoice_date?->translatedFormat('d M Y') ?? '-' }}</span>
                            </div>

                            <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-2">
                                <span class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Supplier</span>
                                <span class="font-semibold text-slate-900">{{ $selectedReturn->supplier?->name ?: '-' }}</span>
                            </div>

                            @if (($moduleConfig['show_invoice_tax'] ?? true))
                                <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-2">
                                    <span class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-slate-500">PPN</span>
                                    <span class="font-semibold text-slate-900">{{ number_format((float) ($selectedReturn->purchaseInvoice?->tax_percentage ?? 0), 2, ',', '.') }}%</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <form method="POST" action="{{ route($moduleConfig['store_route']) }}" class="space-y-0">
                        @csrf
                        <input type="hidden" name="purchase_return_id" value="{{ $selectedReturn->id }}">

                        <div class="border-b border-slate-200/80 px-4 py-2.5">
                            <div class="grid gap-2.5 lg:grid-cols-[220px,minmax(0,1fr)]">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <label for="replacement_date" class="shrink-0 text-[0.72rem] font-semibold text-slate-700">Tanggal realisasi</label>
                                        <input
                                            id="replacement_date"
                                            name="replacement_date"
                                            type="date"
                                            x-model="return_date"
                                            class="ui-control min-w-0 flex-1 px-2.5 text-[0.72rem]"
                                        >
                                    </div>
                                    @error('replacement_date')
                                        <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <label for="notes" class="shrink-0 text-[0.72rem] font-semibold text-slate-700">Catatan</label>
                                        <input
                                            id="notes"
                                            name="notes"
                                            type="text"
                                            value="{{ old('notes') }}"
                                            placeholder="Opsional, misalnya pengganti diterima bertahap"
                                            class="ui-control min-w-0 flex-1 px-2.5 text-[0.72rem] placeholder:text-[0.66rem]"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if (count($initialForm['rows']) > 0)
                            <section class="flex min-h-0 flex-col overflow-hidden p-0">
                                <div class="shrink-0 border-b border-slate-200/80 px-4 py-2">
                                    <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                        <div>
                                            <h3 class="section-title">Detail item pengganti</h3>
                                        </div>

                                        <div class="relative w-full max-w-xs">
                                            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                                                <circle cx="11" cy="11" r="6" />
                                                <path d="m16 16 4 4" />
                                            </svg>
                                            <input
                                                type="search"
                                                x-model="searchTerm"
                                                placeholder="Cari obat, principal, atau batch"
                                                class="ui-control pl-8 pr-2.5 text-[0.72rem] placeholder:text-[0.66rem]"
                                            >
                                        </div>
                                    </div>
                                </div>

                                <div class="max-h-[calc(100vh-18rem)] min-h-[12rem] overflow-auto">
                                    <table class="min-w-[1140px] w-full divide-y divide-slate-200/80 text-[0.72rem]">
                                        <thead class="sticky top-0 z-10 bg-slate-50/95 shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur">
                                            <tr class="text-left text-[0.62rem] font-semibold uppercase tracking-[0.12em] text-slate-400">
                                                <th class="px-2 py-1.5">Obat</th>
                                                <th class="px-2 py-1.5">Batch</th>
                                                <th class="px-2 py-1.5 text-center">Expired</th>
                                                <th class="px-2 py-1.5 text-right">Qty Retur</th>
                                                <th class="px-2 py-1.5 text-right">Sisa Ganti</th>
                                                @if (($moduleConfig['show_unit_price'] ?? true))
                                                    <th class="px-2 py-1.5 text-right">Harga Masuk</th>
                                                @endif
                                                <th class="px-2 py-1.5 text-center">Qty Ganti</th>
                                                @if (($moduleConfig['show_line_total'] ?? true))
                                                    <th class="px-2 py-1.5 text-right">Total Ganti</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-200/80 bg-white text-[0.78rem]">
                                            <template x-for="(row, index) in rows" :key="row.key">
                                                <tr x-show="rowMatchesSearch(row)" class="align-middle" :class="{ 'bg-emerald-50/30': rowIsUsed(row) }">
                                                    <td class="px-2 py-1.5">
                                                        <input type="hidden" :name="rowIsUsed(row) ? `items[${index}][purchase_return_item_id]` : null" :value="row.purchase_return_item_id">
                                                        <p class="min-w-[130px] max-w-[190px] truncate font-semibold text-slate-900" x-text="row.medicine_name" :title="row.medicine_name"></p>
                                                        <p class="mt-0.5 truncate text-[0.68rem] text-slate-500">
                                                            <span x-text="row.medicine_code"></span>
                                                            <span class="px-1 text-slate-300">/</span>
                                                            <span x-text="row.principal_name"></span>
                                                        </p>
                                                    </td>
                                                    <td class="px-2 py-1.5">
                                                        <p class="font-semibold text-slate-900" x-text="row.batch_number"></p>
                                                        <p class="mt-0.5 text-[0.68rem] text-slate-500" x-text="row.small_unit"></p>
                                                    </td>
                                                    <td class="px-2 py-1.5 text-center text-slate-700" x-text="row.expiry_label"></td>
                                                    <td class="px-2 py-1.5 text-right">
                                                        <p class="font-semibold text-slate-900" x-text="row.returned_quantity_label"></p>
                                                        <p class="mt-0.5 text-[0.68rem] text-slate-500">Qty yang diretur</p>
                                                    </td>
                                                    <td class="px-2 py-1.5 text-right">
                                                        <p class="font-semibold text-emerald-700" x-text="row.available_quantity_label"></p>
                                                        <p class="mt-0.5 text-[0.68rem] text-slate-500">Sisa yang bisa diganti</p>
                                                    </td>
                                                    @if (($moduleConfig['show_unit_price'] ?? true))
                                                        <td class="whitespace-nowrap px-2 py-1.5 text-right font-semibold text-slate-900" x-text="currency(row.unit_price_display)"></td>
                                                    @endif
                                                    <td class="px-2 py-1.5 text-center">
                                                        <input
                                                            :name="rowIsUsed(row) ? `items[${index}][quantity]` : null"
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            x-model="row.quantity"
                                                            @input.debounce.150ms="clampQuantity(row)"
                                                            class="number-input-no-spinner mx-auto block w-16 rounded-lg border border-slate-200 bg-slate-50 px-1.5 py-1 text-center text-[0.78rem] text-slate-900 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100"
                                                        >
                                                        <p class="mt-0.5 text-[0.68rem] text-slate-500">Satuan kecil</p>
                                                    </td>
                                                    @if (($moduleConfig['show_line_total'] ?? true))
                                                        <td class="whitespace-nowrap px-2 py-1.5 text-right font-semibold text-emerald-700" x-text="currency(row.landed_total)"></td>
                                                    @endif
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                <div x-show="visibleRowCount() === 0" class="border-t border-slate-200/80 px-4 py-5 text-center text-xs text-slate-500">
                                    Batch atau obat tidak ditemukan.
                                </div>

                                <div class="sticky bottom-0 z-20 shrink-0 border-t border-slate-200/80 bg-white/95 px-4 py-2.5 backdrop-blur">
                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[0.7rem]">
                                            <p class="text-slate-500">
                                                Item dipilih
                                                <span class="ml-1 font-semibold text-slate-900" x-text="activeRowCount()"></span>
                                                <span class="text-slate-400">/ <span x-text="rows.length"></span></span>
                                            </p>
                                            @if (($moduleConfig['show_value_summary'] ?? true))
                                                <p class="text-slate-500">
                                                    DPP
                                                    <span class="ml-1 font-semibold text-slate-900" x-text="currency(subtotal())"></span>
                                                </p>
                                                <p class="text-slate-500">
                                                    PPN
                                                    <span class="ml-1 font-semibold text-slate-900" x-text="currency(taxAmount())"></span>
                                                    <span class="ml-1 text-slate-400" x-text="`(${tax_percentage}%)`"></span>
                                                </p>
                                                <p class="text-emerald-700">
                                                    Total realisasi
                                                    <span class="ml-1 font-semibold" x-text="currency(grandTotal())"></span>
                                                </p>
                                            @endif
                                        </div>

                                        <button
                                            type="submit"
                                            class="inline-flex shrink-0 items-center justify-center rounded-xl border border-emerald-300 bg-emerald-500 px-3.5 py-1.5 text-[0.72rem] font-semibold text-white shadow-sm transition hover:bg-emerald-600"
                                        >
                                            {{ $moduleConfig['submit_label'] }}
                                        </button>
                                    </div>
                                </div>
                            </section>
                        @else
                            <div class="px-5 py-12 text-center">
                                <div class="mx-auto max-w-md space-y-3">
                                    <div class="empty-title">Semua item dari retur ini sudah selesai direalisasikan</div>
                                    <p class="content-copy">
                                        Pilih nomor retur lain yang masih memiliki sisa qty pengganti untuk diproses ke stok batch asal.
                                    </p>
                                </div>
                            </div>
                        @endif
                    </form>
                </div>
            @endif
        </section>
        @endif

        @if ($showHistorySection)
        <section id="riwayat-realisasi" class="panel-surface overflow-hidden p-0 scroll-mt-24">
                <div class="border-b border-slate-200/80 px-5 py-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <h3 class="section-title">{{ $moduleConfig['history_title'] }}</h3>

                    <form method="GET" action="{{ route($moduleConfig['history_route']) }}" class="grid gap-2 sm:grid-cols-[minmax(200px,1fr),auto,auto]">
                        @if ($selectedReturnId)
                            <input type="hidden" name="purchase_return_id" value="{{ $selectedReturnId }}">
                        @endif

                        <label class="sr-only" for="history_search">Cari {{ $moduleConfig['entry_lower'] }}</label>
                        <input
                            id="history_search"
                            name="history_search"
                            type="text"
                            value="{{ $historySearch }}"
                            placeholder="Cari no realisasi, no retur, faktur, supplier, batch, obat"
                            class="ui-control"
                        >

                        <button
                            type="submit"
                            class="ui-action-btn ui-action-btn--soft"
                        >
                            Terapkan
                        </button>

                        <a
                            href="{{ route($moduleConfig['history_route'], $selectedReturnId ? ['purchase_return_id' => $selectedReturnId] : []) }}"
                            class="ui-action-btn ui-action-btn--neutral"
                        >
                            Reset
                        </a>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1120px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                    <thead class="bg-slate-50/90">
                        <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                            <th class="px-4 py-3">No Realisasi</th>
                            <th class="px-3 py-3">Tanggal</th>
                            <th class="px-3 py-3">No Retur</th>
                            <th class="px-3 py-3">No Faktur</th>
                            <th class="px-3 py-3">Supplier</th>
                            <th class="px-3 py-3 text-center">Item Diganti</th>
                            @if (($moduleConfig['show_value_summary'] ?? true))
                                <th class="px-3 py-3 text-right">Subtotal</th>
                                <th class="px-3 py-3 text-right">PPN</th>
                                <th class="px-3 py-3 text-right">Total</th>
                            @endif
                            <th class="px-3 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($replacements as $replacement)
                            <tr class="align-top">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ $replacement->replacement_number }}</p>
                                    <p class="mt-1 text-[0.66rem] text-slate-400">{{ $replacement->items->count() }} item</p>
                                </td>
                                <td class="px-3 py-3 text-slate-700">{{ $replacement->replacement_date?->translatedFormat('d M Y') ?? '-' }}</td>
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-slate-900">{{ $replacement->purchaseReturn?->return_number ?: '-' }}</p>
                                    @if (($moduleConfig['show_invoice_tax'] ?? true))
                                        <p class="mt-1 text-[0.66rem] text-slate-400">{{ number_format((float) ($replacement->purchaseReturn?->purchaseInvoice?->tax_percentage ?? 0), 2, ',', '.') }}% PPN</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-slate-700">{{ $replacement->purchaseReturn?->purchaseInvoice?->invoice_number ?: '-' }}</td>
                                <td class="px-3 py-3 text-slate-700">{{ $replacement->supplier?->name ?: '-' }}</td>
                                <td class="px-3 py-3 text-center font-semibold text-slate-900">{{ number_format($replacement->items->count()) }}</td>
                                @if (($moduleConfig['show_value_summary'] ?? true))
                                    <td class="px-3 py-3 text-right font-semibold text-slate-900">Rp {{ number_format((float) $replacement->subtotal, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-slate-900">Rp {{ number_format((float) $replacement->tax_amount, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-emerald-700">Rp {{ number_format((float) $replacement->total_amount, 0, ',', '.') }}</td>
                                @endif
                                <td class="px-3 py-3 align-middle">
                                    <div class="table-action-group">
                                        <button
                                            type="button"
                                            @click="openDetail(@js($detailPayloads[$replacement->id] ?? null))"
                                            class="table-icon-btn"
                                            title="Lihat detail realisasi"
                                            aria-label="Lihat detail realisasi {{ $replacement->replacement_number }}"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M2.06 12.35a1 1 0 0 1 0-.7C3.2 8.38 6.52 5 12 5s8.8 3.38 9.94 6.65a1 1 0 0 1 0 .7C20.8 15.62 17.48 19 12 19s-8.8-3.38-9.94-6.65Z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                            <span class="sr-only">Lihat detail</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="openDeleteDialog(@js([
                                                'action' => route($moduleConfig['destroy_route'], $replacement),
                                                'title' => $moduleConfig['delete_title'],
                                                'description' => 'Realisasi '.$replacement->replacement_number.' akan dihapus dan stok pengganti pada batch asal akan ditarik kembali.',
                                                'warning' => 'Setelah dihapus, qty pengganti pada batch terkait akan berkurang lagi sesuai transaksi realisasi ini.',
                                                'name' => $replacement->purchaseReturn?->return_number ?: ($replacement->supplier?->name ?: $replacement->replacement_number),
                                                'code' => $replacement->replacement_number,
                                                'confirm_label' => 'Ya, hapus realisasi',
                                            ]))"
                                            class="table-icon-btn table-icon-btn--danger"
                                            title="Hapus {{ $moduleConfig['entry_lower'] }} {{ $replacement->replacement_number }}"
                                            aria-label="Hapus {{ $moduleConfig['entry_lower'] }} {{ $replacement->replacement_number }}"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M3 6h18" />
                                                <path d="M8 6V4.75A1.75 1.75 0 0 1 9.75 3h4.5A1.75 1.75 0 0 1 16 4.75V6" />
                                                <path d="M19 6l-.82 11.47A2 2 0 0 1 16.19 19H7.81a2 2 0 0 1-1.99-1.53L5 6" />
                                                <path d="M10 10.5v5" />
                                                <path d="M14 10.5v5" />
                                            </svg>
                                            <span class="sr-only">Hapus {{ $moduleConfig['entry_lower'] }}</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ($moduleConfig['show_value_summary'] ?? true) ? 10 : 7 }}" class="px-5 py-14 text-center">
                                    <div class="mx-auto max-w-md space-y-3">
                                        <div class="empty-title">{{ $historySearch !== '' ? ucfirst($moduleConfig['entry_lower']).' tidak ditemukan' : 'Belum ada '.strtolower($page['label']) }}</div>
                                        <p class="content-copy">
                                            {{ $historySearch !== '' ? 'Coba kata kunci lain untuk mencari histori '.strtolower($page['label']).'.' : 'Histori realisasi akan tampil di sini setelah stok pengganti dari retur pembelian diinput kembali.' }}
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($replacements->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4">
                    {{ $replacements->links() }}
                </div>
            @endif
        </section>

        <x-master-delete-modal />
        @endif

        @if ($showHistorySection)
        <div
            x-cloak
            x-show="detailModalOpen"
            x-transition.opacity
            class="fixed inset-0 z-40 overflow-y-auto bg-slate-950/50 backdrop-blur-sm"
            @click.self="closeDetail()"
        >
            <div class="flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6">
                <div class="panel-surface relative z-50 max-h-[calc(100vh-2rem)] w-full max-w-4xl overflow-y-auto p-5 sm:max-h-[calc(100vh-3rem)] sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="inline-flex rounded-full border border-teal-100 bg-teal-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-teal-700">
                                {{ $moduleConfig['detail_title'] }}
                            </div>
                            <h3 class="mt-3 text-lg font-semibold text-slate-950" x-text="detailReplacement?.replacement_number"></h3>
                            <p class="mt-1 text-xs text-slate-500">
                                <span x-text="detailReplacement?.replacement_date"></span>
                                <span class="px-1 text-slate-300">/</span>
                                <span x-text="detailReplacement?.supplier"></span>
                                <span class="px-1 text-slate-300">/</span>
                                <span x-text="detailReplacement?.return_number"></span>
                                <span class="px-1 text-slate-300">/</span>
                                <span x-text="detailReplacement?.invoice_number"></span>
                            </p>
                        </div>

                        <button type="button" class="table-icon-btn" @click="closeDetail()" aria-label="Tutup detail">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                                <path d="M6 6l12 12M18 6 6 18" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200/80">
                        <table class="min-w-full divide-y divide-slate-200/80 text-[0.76rem]">
                            <thead class="bg-slate-50/90 text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <tr>
                                    <th class="px-3 py-3">Barang</th>
                                    <th class="px-3 py-3">Batch</th>
                                    <th class="px-3 py-3">Expired</th>
                                    <th class="px-3 py-3">Qty Realisasi</th>
                                    @if (($moduleConfig['show_line_total'] ?? true))
                                        <th class="px-3 py-3 text-right">Total Realisasi</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/80 bg-white">
                                <template x-for="item in detailReplacement?.items ?? []" :key="item.id">
                                    <tr>
                                        <td class="px-3 py-3">
                                            <p class="font-semibold text-slate-900" x-text="item.medicine"></p>
                                            <p class="mt-1 text-[0.66rem] text-slate-400" x-text="item.medicine_code"></p>
                                        </td>
                                        <td class="px-3 py-3 text-slate-700" x-text="item.batch_number"></td>
                                        <td class="px-3 py-3 text-slate-700" x-text="item.expiry_date"></td>
                                        <td class="px-3 py-3 text-slate-700">
                                            <p class="font-semibold text-slate-900" x-text="item.quantity"></p>
                                        </td>
                                        @if (($moduleConfig['show_line_total'] ?? true))
                                            <td class="px-3 py-3 text-right font-semibold text-slate-900" x-text="item.line_total"></td>
                                        @endif
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    @if (($moduleConfig['show_value_summary'] ?? true))
                        <div class="mt-4 flex flex-wrap justify-end gap-x-5 gap-y-2 text-[0.76rem]">
                            <p class="text-slate-500">DPP <span class="ml-1 font-semibold text-slate-900" x-text="detailReplacement?.subtotal"></span></p>
                            <p class="text-slate-500">PPN <span class="ml-1 font-semibold text-slate-900" x-text="detailReplacement?.tax_amount"></span></p>
                            <p class="text-slate-500">Tarif <span class="ml-1 font-semibold text-slate-900" x-text="`${detailReplacement?.tax_percentage ?? '0'}%`"></span></p>
                            <p class="text-emerald-700">Total <span class="ml-1 font-semibold" x-text="detailReplacement?.total_amount"></span></p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>
</x-app-layout>
