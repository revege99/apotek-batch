@php
    $customerLookupOptions = $customerOptions
        ->map(function ($customer) {
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'group_name' => $customer->customerGroup?->name ?: 'Tanpa golongan',
                'markup_percentage' => (string) ($customer->customerGroup?->markup_percentage ?? 0),
                'label' => $customer->name,
            ];
        })
        ->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
            <span>{{ $section }}</span>
            <span class="text-slate-300">/</span>
            <span class="text-slate-600">{{ $page['label'] }}</span>
        </div>
    </x-slot>

    <div x-data="saleForm(@js($initialForm), @js($customerLookupOptions))" @keydown.escape.window="closePaymentModal()" class="-mt-2 flex h-full min-h-0 flex-col space-y-2 overflow-x-hidden overflow-y-visible">
        @if ($customerOptions->isEmpty())
            <div class="rounded-[1.35rem] border border-amber-200 bg-amber-50 px-4 py-3 text-[0.78rem] text-amber-800">
                Pelanggan aktif belum tersedia. Tambahkan pelanggan lebih dulu dari master data agar harga jual bisa mengikuti golongan markup.
            </div>
        @endif

        @if (count($initialForm['items']) === 0)
            <div class="rounded-[1.35rem] border border-amber-200 bg-amber-50 px-4 py-3 text-[0.78rem] text-amber-800">
                Obat dengan stok aktif belum tersedia. Input pembelian lebih dulu agar kasir penjualan bisa diproses.
            </div>
        @endif

        <form x-ref="saleForm" method="POST" action="{{ $editingSale ? route('penjualan.data-penjualan.update', $editingSale) : route('penjualan.kasir-penjualan.store') }}" class="flex min-h-0 flex-1 flex-col space-y-2 overflow-x-hidden overflow-y-visible">
            @csrf
            @if ($editingSale)
                @method('PATCH')
            @endif

            <section class="panel-surface relative z-30 overflow-visible rounded-[1.2rem] px-4 py-2.5">
                <div class="flex flex-col gap-2.5 md:flex-row md:flex-wrap md:items-start">
                    <div class="space-y-1 md:w-[11rem]">
                        <div class="flex items-center gap-2">
                            <label for="sale_number" class="shrink-0 text-[0.72rem] font-semibold text-slate-700">Nomor jual</label>
                            <input
                                id="sale_number"
                                name="sale_number"
                                type="text"
                                x-model="sale_number"
                                placeholder="PJL-0001"
                                class="ui-control min-w-0 flex-1 px-2.5 text-[0.72rem] placeholder:text-[0.68rem]"
                            >
                        </div>
                        @error('sale_number')
                            <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1 md:w-[11rem]">
                        <div class="flex items-center gap-2">
                            <label for="sale_date" class="shrink-0 text-[0.72rem] font-semibold text-slate-700">Tanggal</label>
                            <input
                                id="sale_date"
                                name="sale_date"
                                type="datetime-local"
                                x-model="sale_date"
                                class="ui-control min-w-0 flex-1 px-2.5 text-[0.72rem]"
                            >
                        </div>
                    </div>

                    <div class="space-y-1 md:min-w-[17rem] md:flex-1">
                        <div class="flex items-start gap-2">
                            <label for="customer_search" class="shrink-0 pt-1.5 text-[0.72rem] font-semibold text-slate-700">Pelanggan</label>
                            <div class="relative min-w-0 flex-1" @click.outside="closeCustomerDropdown()">
                                <input type="hidden" name="customer_id" :value="customer_id">
                                <input
                                    id="customer_search"
                                    type="search"
                                    x-model="customerSearch"
                                    @focus="openCustomerDropdown()"
                                    @input.debounce.120ms="handleCustomerSearchInput($event.target.value)"
                                    @keydown.enter.prevent="selectFirstFilteredCustomer()"
                                    @keydown.escape.stop="closeCustomerDropdown()"
                                    autocomplete="off"
                                    placeholder="Cari pelanggan, kontak, atau golongan"
                                    class="ui-control w-full px-2.5 text-[0.72rem] placeholder:text-[0.68rem]"
                                >

                                <div
                                    x-cloak
                                    x-show="customerDropdownOpen"
                                    x-transition.opacity.duration.100ms
                                    class="absolute left-0 right-0 top-[calc(100%+0.35rem)] z-[70] overflow-hidden rounded-[1rem] border border-slate-200 bg-white shadow-[0_24px_50px_-28px_rgba(15,23,42,0.35)]"
                                >
                                    <div class="max-h-56 overflow-y-auto py-1.5">
                                        <template x-if="filteredCustomers().length === 0">
                                            <div class="px-3 py-2 text-[0.72rem] text-slate-500">
                                                Pelanggan tidak ditemukan.
                                            </div>
                                        </template>

                                        <template x-for="customer in filteredCustomers()" :key="customer.id">
                                            <button
                                                type="button"
                                                @click="selectCustomer(customer)"
                                                class="flex w-full items-start justify-between gap-3 px-3 py-2 text-left transition hover:bg-emerald-50/70"
                                                :class="String(customer.id) === String(customer_id) ? 'bg-emerald-50/90' : ''"
                                            >
                                                <div class="min-w-0">
                                                    <p class="truncate text-[0.76rem] font-semibold text-slate-900" x-text="customer.name"></p>
                                                    <p class="mt-0.5 truncate text-[0.68rem] text-slate-500" x-text="customer.group_name"></p>
                                                </div>
                                                <span class="shrink-0 text-[0.66rem] text-slate-400" x-text="customer.phone || '-'"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="inline-flex h-[35px] shrink-0 items-center gap-1.5 rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-2.5">
                                <span class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-emerald-700">Markup Default</span>
                                <span class="text-[0.72rem] font-semibold text-emerald-700" x-text="`${formatQuantity(currentMarkup())}%`"></span>
                            </div>

                            <div class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-2 text-[0.72rem]">
                                <span class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Kontak</span>
                                <span class="font-semibold text-slate-900" x-text="selectedCustomerPhone() || '-'"></span>
                            </div>
                        </div>
                        @error('customer_id')
                            <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>

            <section class="panel-surface rounded-[1.2rem] flex min-h-0 flex-1 flex-col overflow-hidden p-0">
                <div class="shrink-0 border-b border-slate-200/80 px-4 py-2">
                    <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="section-title">Detail penjualan</h3>
                        </div>

                        <div class="flex w-full max-w-md items-center gap-2">
                            <div class="relative min-w-0 flex-1">
                                <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                                    <circle cx="11" cy="11" r="6" />
                                    <path d="m16 16 4 4" />
                                </svg>
                                <input
                                    type="search"
                                    :value="searchTerm"
                                    @input.debounce.180ms="setSearchTerm($event.target.value)"
                                    placeholder="Cari kode, nama, principal, atau kandungan"
                                    class="ui-control w-full pl-8 pr-2.5 text-[0.72rem] placeholder:text-[0.66rem]"
                                >
                            </div>

                            <button
                                type="button"
                                @click="rapikanRows()"
                                class="ui-action-btn ui-action-btn--neutral shrink-0 px-3"
                                title="Tambahkan baris batch lanjutan untuk obat yang sudah diisi"
                                aria-label="Rapikan baris penjualan"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 11l3 3L22 4" />
                                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-auto">
                    <table class="min-w-[940px] w-full divide-y divide-slate-200/80 text-[0.72rem]">
                        <thead class="sticky top-0 z-10 bg-slate-50/95 shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur">
                            <tr class="text-left text-[0.62rem] font-semibold uppercase tracking-[0.12em] text-slate-400">
                                <th class="px-2 py-1.5">Obat</th>
                                <th class="px-1.5 py-1.5">Batch</th>
                                <th class="px-1 py-1.5">Expired</th>
                                <th class="px-1 py-1.5 text-right">Harga Dasar</th>
                                <th class="px-1.5 py-1.5 text-center">Markup %</th>
                                <th class="px-1.5 py-1.5 text-right">Stok Batch</th>
                                <th class="px-1.5 py-1.5 text-right">Harga Jual</th>
                                <th class="px-1.5 py-1.5 text-center">Qty</th>
                                <th class="px-1.5 py-1.5 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 bg-white text-[0.78rem]">
                            <template x-for="(row, index) in rows" :key="row.key">
                                <tr x-show="rowMatchesSearch(row)" class="align-middle" :class="{ 'bg-emerald-50/30': rowIsUsed(row) }">
                                    <td class="px-2 py-1.5">
                                        <input type="hidden" :name="rowIsUsed(row) ? `items[${index}][medicine_id]` : null" :value="row.medicine_id">
                                        <input type="hidden" :name="rowIsUsed(row) ? `items[${index}][stock_batch_id]` : null" :value="row.stock_batch_id">
                                        <input type="hidden" :name="rowIsUsed(row) ? `items[${index}][unit_cost]` : null" :value="row.base_unit_cost">
                                        <input type="hidden" :name="rowIsUsed(row) ? `items[${index}][markup_percentage]` : null" :value="row.markup_percentage">
                                        <input type="hidden" :name="rowIsUsed(row) ? `items[${index}][unit_price]` : null" :value="row.unit_price">
                                        <p class="min-w-[116px] max-w-[152px] truncate font-semibold text-slate-900" x-text="row.medicine_name" :title="row.medicine_name"></p>
                                        <p class="mt-0.5 text-[0.68rem] text-slate-500" x-text="`${row.medicine_code} / ${row.principal_name}`"></p>
                                    </td>

                                    <td class="px-1.5 py-1.5">
                                        <select
                                            x-model="row.stock_batch_id"
                                            @change="handleBatchChange(row)"
                                            class="w-36 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[0.72rem] text-slate-900 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100"
                                        >
                                            <template x-for="batch in row.batches" :key="batch.id">
                                                <option :value="String(batch.id)" x-text="batch.label"></option>
                                            </template>
                                        </select>
                                        <p class="mt-0.5 text-[0.68rem] text-slate-500" x-text="`Sisa ${formatQuantity(row.stock_quantity)} ${row.small_unit}`"></p>
                                    </td>

                                    <td class="whitespace-nowrap px-1 py-1.5 text-left text-slate-700" x-text="batchExpiryLabel(row)"></td>

                                    <td class="whitespace-nowrap px-1 py-1.5 text-right font-semibold text-slate-700" x-text="currency(row.base_unit_cost)"></td>

                                    <td class="px-1.5 py-1.5 text-center">
                                        <div class="mx-auto w-[5.75rem]">
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                x-model="row.markup_percentage"
                                                class="number-input-no-spinner block w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-center text-[0.78rem] text-slate-900 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100"
                                                @input.debounce.150ms="handleMarkupInput(row)"
                                            >
                                            <p class="mt-0.5 text-[0.66rem] text-slate-500">persen</p>
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-1.5 py-1.5 text-right font-semibold text-slate-900">
                                        <p x-text="formatQuantity(row.stock_quantity)"></p>
                                        <p class="mt-0.5 text-[0.68rem] text-slate-500" x-text="row.small_unit"></p>
                                    </td>

                                    <td class="whitespace-nowrap px-1.5 py-1.5 text-right font-semibold text-slate-900" x-text="currency(row.unit_price)"></td>

                                    <td class="px-1.5 py-1.5 text-center">
                                        <input
                                            :name="rowIsUsed(row) ? `items[${index}][quantity]` : null"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            x-model="row.quantity"
                                            class="number-input-no-spinner mx-auto block w-14 rounded-lg border border-slate-200 bg-slate-50 px-1.5 py-1 text-center text-[0.78rem] text-slate-900 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100"
                                            @input.debounce.150ms="refreshRow(row)"
                                        >
                                    </td>

                                    <td class="min-w-[5.25rem] whitespace-nowrap px-1 py-1.5 text-right">
                                        <p class="font-semibold text-slate-900" x-text="currency(row.line_total)"></p>
                                        <p class="mt-0.5 text-[0.68rem] text-slate-500" x-text="row.composition"></p>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div x-show="visibleRowCount() === 0" class="border-t border-slate-200/80 px-4 py-5 text-center text-xs text-slate-500">
                    Obat tidak ditemukan.
                </div>

                <div class="sticky bottom-0 z-20 shrink-0 border-t border-slate-200/80 bg-white/95 px-4 py-2.5 backdrop-blur">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[0.7rem]">
                            <p class="text-slate-500">
                                Obat dipilih
                                <span class="ml-1 font-semibold text-slate-900" x-text="activeRowCount()"></span>
                                <span class="text-slate-400">/ <span x-text="rows.length"></span></span>
                            </p>
                            <p class="text-slate-500">
                                Total penjualan
                                <span class="ml-1 font-semibold text-slate-900" x-text="currency(grandTotal())"></span>
                            </p>
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row">
                            <a
                                href="{{ route('penjualan.data-penjualan') }}"
                                class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-[0.72rem] font-semibold text-slate-700 shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 6h18" />
                                    <path d="M3 12h18" />
                                    <path d="M3 18h18" />
                                </svg>
                                {{ $editingSale ? 'Kembali ke Data Penjualan' : 'Data Penjualan' }}
                            </a>

                            <button
                                type="button"
                                @click="openPaymentModal()"
                                class="inline-flex shrink-0 items-center justify-center rounded-xl border border-emerald-300 bg-emerald-500 px-3.5 py-1.5 text-[0.72rem] font-semibold text-white shadow-sm transition hover:bg-emerald-600"
                                :disabled="!canSubmit()"
                                :class="{ 'cursor-not-allowed opacity-60 hover:bg-emerald-500': !canSubmit() }"
                            >
                                {{ $editingSale ? 'Update penjualan' : 'Simpan penjualan' }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <input type="hidden" name="notes" :value="notes">
            <input type="hidden" name="payment_kind" :value="payment_kind">
            <input type="hidden" name="payment_method" :value="payment_method">
            <input type="hidden" name="paid_amount" :value="effectivePaidAmount()">
        </form>

        <div
            x-cloak
            x-show="paymentModalOpen"
            x-transition.opacity
            class="fixed inset-0 z-40 overflow-y-auto bg-slate-950/50 backdrop-blur-sm"
            @click.self="closePaymentModal()"
        >
            <div class="flex min-h-full items-start justify-center p-3 sm:items-center sm:p-4">
                <div class="panel-surface relative z-50 w-full max-w-4xl p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="inline-flex rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                                Konfirmasi Pembayaran
                            </div>
                            <h3 class="mt-2 text-base font-semibold text-slate-950 sm:text-lg">{{ $editingSale ? 'Konfirmasi perubahan penjualan' : 'Pilih metode bayar penjualan' }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ $editingSale ? 'Periksa kembali metode bayar dan total transaksi sebelum perubahan disimpan.' : 'Tentukan apakah transaksi ini lunas, sosial, atau langsung disimpan sebagai piutang.' }}</p>
                        </div>

                        <button type="button" class="table-icon-btn" @click="closePaymentModal()" aria-label="Tutup konfirmasi pembayaran">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                                <path d="M6 6l12 12M18 6 6 18" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-4 grid gap-2.5 sm:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3.5 py-2.5">
                            <p class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Nomor jual</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900" x-text="sale_number || '-'"></p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3.5 py-2.5">
                            <p class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Pelanggan</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900" x-text="selectedCustomer()?.name || '-'"></p>
                        </div>

                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5">
                            <p class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-emerald-700">Total penjualan</p>
                            <p class="mt-1 text-sm font-semibold text-emerald-700" x-text="currency(grandTotal())"></p>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label for="sale_notes_modal" class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Catatan</label>
                        <input
                            id="sale_notes_modal"
                            type="text"
                            x-model="notes"
                            placeholder="Opsional, misalnya titipan resep atau catatan pelanggan"
                            class="ui-control mt-2 px-3 text-[0.72rem] placeholder:text-[0.68rem]"
                        >
                    </div>

                    <div class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1.08fr)_minmax(19rem,0.92fr)] lg:items-start">
                        <div class="space-y-3">
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Jenis pembayaran</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                <button
                                    type="button"
                                    @click="setPaymentKind('cash')"
                                    class="rounded-2xl border px-3.5 py-2.5 text-left transition"
                                    :class="payment_kind === 'cash'
                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                        : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200 hover:bg-emerald-50/60'"
                                >
                                    <p class="text-sm font-semibold">Cash</p>
                                    <p class="mt-1 text-[0.72rem] text-current/80">Bayar langsung saat transaksi disimpan.</p>
                                </button>

                                <button
                                    type="button"
                                    @click="setPaymentKind('social')"
                                    class="rounded-2xl border px-3.5 py-2.5 text-left transition"
                                    :class="payment_kind === 'social'
                                        ? 'border-sky-300 bg-sky-50 text-sky-700'
                                        : 'border-slate-200 bg-white text-slate-700 hover:border-sky-200 hover:bg-sky-50/60'"
                                >
                                    <p class="text-sm font-semibold">Sosial</p>
                                    <p class="mt-1 text-[0.72rem] text-current/80">Pelanggan bayar tidak penuh, sisanya dicatat sebagai sosial.</p>
                                </button>

                                <button
                                    type="button"
                                    @click="setPaymentKind('credit')"
                                    class="rounded-2xl border px-3.5 py-2.5 text-left transition"
                                    :class="payment_kind === 'credit'
                                        ? 'border-amber-300 bg-amber-50 text-amber-700'
                                        : 'border-slate-200 bg-white text-slate-700 hover:border-amber-200 hover:bg-amber-50/60'"
                                >
                                    <p class="text-sm font-semibold">Kredit</p>
                                    <p class="mt-1 text-[0.72rem] text-current/80">Tagihan disimpan sebagai piutang pelanggan.</p>
                                </button>
                            </div>
                            <div x-show="payment_kind !== 'credit'" x-transition class="space-y-2">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-slate-500" x-text="payment_kind === 'social' ? 'Metode pembayaran sosial' : 'Metode bayar'"></p>
                                <div class="grid gap-2 sm:grid-cols-4">
                                    <button
                                        type="button"
                                        @click="selectPaymentMethod('cash')"
                                        class="rounded-2xl border px-3 py-2 text-center text-[0.74rem] font-semibold transition"
                                        :class="payment_method === 'cash'
                                            ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                            : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200 hover:bg-emerald-50/60'"
                                    >
                                        Tunai
                                    </button>

                                    <button
                                        type="button"
                                        @click="selectPaymentMethod('qris')"
                                        class="rounded-2xl border px-3 py-2 text-center text-[0.74rem] font-semibold transition"
                                        :class="payment_method === 'qris'
                                            ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                            : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200 hover:bg-emerald-50/60'"
                                    >
                                        QRIS
                                    </button>

                                    <button
                                        type="button"
                                        @click="selectPaymentMethod('transfer')"
                                        class="rounded-2xl border px-3 py-2 text-center text-[0.74rem] font-semibold transition"
                                        :class="payment_method === 'transfer'
                                            ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                            : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200 hover:bg-emerald-50/60'"
                                    >
                                        Transfer
                                    </button>

                                    <button
                                        type="button"
                                        @click="selectPaymentMethod('debit')"
                                        class="rounded-2xl border px-3 py-2 text-center text-[0.74rem] font-semibold transition"
                                        :class="payment_method === 'debit'
                                            ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                            : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200 hover:bg-emerald-50/60'"
                                    >
                                        Debit
                                    </button>
                                </div>
                            </div>

                            <div x-show="isSocialPayment()" x-transition class="space-y-2">
                            <input
                                x-ref="paymentCashAmountInput"
                                id="payment_paid_amount"
                                type="text"
                                inputmode="decimal"
                                :value="paid_amount_display"
                                @input="handlePaidAmountInput($event)"
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100"
                                :placeholder="payment_kind === 'social' ? 'Masukkan nominal yang benar-benar dibayar' : 'Masukkan nominal bayar tunai'"
                            >
                                <p x-show="paymentShortfall() > 0" class="text-xs font-medium" :class="payment_kind === 'social' ? 'text-sky-700' : 'text-rose-600'">
                                    <span x-text="payment_kind === 'social' ? 'Nilai sosial ' : 'Kurang bayar '"></span><span x-text="currency(paymentShortfall())"></span>
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="grid gap-2.5 rounded-2xl border border-slate-200 bg-slate-50 px-3.5 py-3 sm:grid-cols-2 lg:grid-cols-1">
                                <div>
                                    <p class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Jumlah bayar</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900" x-text="currency(effectivePaidAmount())"></p>
                                </div>

                                <div>
                                    <p class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-slate-500" x-text="payment_kind === 'social' ? 'Sosial' : 'Kembalian'"></p>
                                    <p
                                        class="mt-1 text-sm font-semibold"
                                        :class="payment_kind === 'social' ? 'text-sky-700' : 'text-emerald-700'"
                                        x-text="currency(payment_kind === 'social' ? paymentShortfall() : changeAmount())"
                                    ></p>
                                </div>
                            </div>

                            <div x-show="payment_kind === 'social'" x-transition class="rounded-2xl border border-sky-200 bg-sky-50 px-3.5 py-3 text-[0.74rem] leading-5 text-sky-800">
                                Penjualan sosial tetap menyimpan total jual asli. Selisih antara total asli dan nominal yang dibayar akan dicatat sebagai sosial tanpa masuk ke piutang.
                            </div>

                            <div x-show="payment_kind === 'credit'" x-transition class="rounded-2xl border border-amber-200 bg-amber-50 px-3.5 py-3 text-[0.74rem] leading-5 text-amber-800">
                                Penjualan akan disimpan sebagai kredit. Nilai bayar dan kembalian otomatis nol, sementara total penjualan tetap tercatat penuh.
                            </div>

                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end lg:pt-1">
                                <button
                                    type="button"
                                    type="button"
                                    @click="closePaymentModal()"
                                    class="ui-action-btn ui-action-btn--neutral"
                                >
                                    Batal
                                </button>

                                <button
                                    x-ref="confirmPaymentButton"
                                    type="button"
                                    @click="submitSale()"
                                    class="inline-flex items-center justify-center rounded-xl border border-emerald-300 bg-emerald-500 px-4 py-2 text-[0.76rem] font-semibold text-white shadow-sm transition hover:bg-emerald-600"
                                    :disabled="!canConfirmPayment()"
                                    :class="{ 'cursor-not-allowed opacity-60 hover:bg-emerald-500': !canConfirmPayment() }"
                                >
                                    {{ $editingSale ? 'Simpan perubahan' : 'Simpan transaksi' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
