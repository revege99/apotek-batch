@php
    $supplierLookupOptions = $supplierOptions
        ->map(function ($supplier) {
            return [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'city' => $supplier->city,
                'label' => $supplier->name.($supplier->city ? ' - '.$supplier->city : ''),
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

    <div x-data="purchaseInvoiceForm(@js($initialForm), @js($supplierLookupOptions))" @keydown.escape.window="closePaymentModal()" class="-mt-2 flex h-full min-h-0 flex-col space-y-2 overflow-hidden">
        @if ($supplierOptions->isEmpty())
            <div class="rounded-[1.35rem] border border-amber-200 bg-amber-50 px-4 py-3 text-[0.78rem] text-amber-800">
                Supplier aktif belum tersedia. Tambahkan supplier lebih dulu dari menu master data sebelum input faktur pembelian.
            </div>
        @endif

        <form x-ref="purchaseInvoiceForm" method="POST" action="{{ $formAction }}" class="flex min-h-0 flex-1 flex-col space-y-2 overflow-hidden">
            @csrf
            @if ($formMethod)
                @method($formMethod)
            @endif

            <section class="panel-surface rounded-[1.2rem] px-4 py-2.5">
                <div class="flex flex-nowrap items-start gap-2 overflow-x-auto">
                    <div class="min-w-0 shrink-0 space-y-1" style="flex: 0 0 17rem;">
                        <div class="flex items-center gap-2">
                            <label for="invoice_number" class="shrink-0 text-[0.72rem] font-semibold text-slate-700">Nomor faktur</label>
                            <input
                                id="invoice_number"
                                name="invoice_number"
                                type="text"
                                x-model="invoice_number"
                                @input="invoice_number = String(invoice_number ?? '').toUpperCase()"
                                placeholder="INV-BELI-0001"
                                class="ui-control min-w-0 flex-1 px-2.5 text-[0.72rem] uppercase placeholder:text-[0.68rem]"
                            >
                        </div>
                        @error('invoice_number')
                            <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="min-w-0 shrink-0 space-y-1" style="flex: 0 0 10.5rem;">
                        <div class="flex items-center gap-2">
                            <label for="invoice_date" class="shrink-0 text-[0.72rem] font-semibold text-slate-700">Tanggal</label>
                            <input
                                id="invoice_date"
                                name="invoice_date"
                                type="date"
                                x-model="invoice_date"
                                class="ui-control min-w-0 flex-1 px-2 text-[0.72rem]"
                            >
                        </div>
                        @error('invoice_date')
                            <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="min-w-0 shrink-0 space-y-1" style="flex: 0 0 12rem;">
                        <div class="flex items-center gap-2">
                            <label for="supplier_id" class="shrink-0 text-[0.72rem] font-semibold text-slate-700">Supplier</label>
                            <select
                                id="supplier_id"
                                name="supplier_id"
                                x-model="supplier_id"
                                class="ui-select-control min-w-0 flex-1 px-2.5 text-[0.72rem]"
                            >
                                <option value="">Pilih supplier</option>
                                @foreach ($supplierOptions as $supplier)
                                    <option value="{{ $supplier->id }}">
                                        {{ $supplier->name }}{{ $supplier->city ? ' - '.$supplier->city : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('supplier_id')
                            <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="min-w-0 shrink-0 ml-auto space-y-1" style="flex: 0 0 27rem;">
                        <div class="flex items-center gap-2">
                            <label class="shrink-0 text-[0.72rem] font-semibold text-slate-700">Cari obat</label>
                            <div class="flex min-w-0 flex-1 items-center justify-end gap-1.5">
                                <div class="relative min-w-0 flex-1" style="max-width: 21rem;">
                                    <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                                        <circle cx="11" cy="11" r="6" />
                                        <path d="m16 16 4 4" />
                                    </svg>
                                    <input
                                        type="search"
                                        :value="searchTerm"
                                        @input.debounce.180ms="setSearchTerm($event.target.value)"
                                        placeholder="Cari kode, nama, atau kandungan obat"
                                        class="ui-control pl-8 pr-2.5 text-[0.72rem] placeholder:text-[0.66rem]"
                                    >
                                </div>

                                <button
                                    type="button"
                                    @click="rapikanRows()"
                                    class="ui-action-btn ui-action-btn--neutral shrink-0 px-3"
                                    title="Tambahkan baris batch lanjutan untuk obat yang sudah diisi"
                                    aria-label="Rapikan baris obat"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 11l3 3L22 4" />
                                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel-surface rounded-[1.2rem] flex min-h-0 flex-1 flex-col overflow-hidden p-0">
                <div class="min-h-0 flex-1 overflow-auto">
                    <table class="min-w-[1120px] w-full divide-y divide-slate-200/80 text-[0.72rem]">
                        <thead class="sticky top-0 z-10 bg-slate-50/95 shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur">
                            <tr class="text-left text-[0.62rem] font-semibold uppercase tracking-[0.12em] text-slate-400">
                                <th class="px-2 py-1.5">Obat</th>
                                <th class="px-2 py-1.5">Satuan</th>
                                <th class="px-1 py-1.5 text-center">Isi</th>
                                <th class="px-1 py-1.5 text-center">Total Qty</th>
                                <th class="px-2 py-1.5 text-center">Batch</th>
                                <th class="px-0.5 py-1.5 text-center">Expired</th>
                                <th class="px-0.5 py-1.5 text-center">Lokasi</th>
                                <th class="px-0.5 py-1.5 text-center">Qty</th>
                                <th class="px-0.5 py-1.5 text-center">Harga</th>
                                <th class="px-0.5 py-1.5 text-center" title="Update harga beli master obat">U</th>
                                <th class="px-0.5 py-1.5 text-center">Disc %</th>
                                <th class="px-0.5 py-1.5 text-center">Disc Rp</th>
                                <th class="px-2 py-1.5 text-right">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 bg-white text-[0.78rem]">
                            <template x-for="(row, index) in rows" :key="row.key">
                                <tr x-show="rowMatchesSearch(row)" class="align-middle" :class="{ 'bg-emerald-50/30': rowIsUsed(row) }">
                                    <td class="px-2 py-1.5">
                                        <input type="hidden" :name="rowIsUsed(row) ? `items[${index}][medicine_id]` : null" :value="row.medicine_id">
                                        <input type="hidden" :name="rowIsUsed(row) ? `items[${index}][discount_mode]` : null" :value="row.discount_mode">
                                        <p class="min-w-[130px] max-w-[170px] truncate font-semibold text-slate-900" x-text="row.medicine_name" :title="row.medicine_name"></p>
                                        <p class="mt-0.5 text-[0.68rem] text-slate-500" x-text="row.medicine_code"></p>
                                    </td>

                                    <td class="px-2 py-1.5 font-medium text-slate-700">
                                        <span x-text="row.purchase_unit"></span>
                                    </td>

                                    <td class="px-1 py-1.5 text-center">
                                        <input
                                            :name="rowIsUsed(row) ? `items[${index}][unit_content]` : null"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            x-model="row.unit_content"
                                            class="ui-control purchase-detail-control number-input-no-spinner mx-auto block w-14 rounded-lg px-1.5 text-center text-[0.78rem] font-medium"
                                            @input.debounce.150ms="handleUnitContentInput(row)"
                                        >
                                    </td>

                                    <td class="px-1 py-1.5 text-center">
                                        <p class="font-semibold text-slate-900" x-text="formatQuantity(row.stock_quantity)"></p>
                                        <p class="mt-0.5 text-[0.66rem] text-slate-400">qty x isi</p>
                                    </td>

                                    <td class="px-2 py-1.5 text-center">
                                        <input
                                            :name="rowIsUsed(row) ? `items[${index}][batch_number]` : null"
                                            type="text"
                                            x-model="row.batch_number"
                                            placeholder="No batch"
                                            class="ui-control purchase-detail-control mx-auto block w-20 rounded-lg px-2 text-center text-[0.78rem] uppercase placeholder:text-[0.68rem]"
                                            @input.debounce.150ms="handleBatchInput(row)"
                                        >
                                    </td>

                                    <td class="px-0.5 py-1.5 text-center">
                                        <input
                                            :name="rowIsUsed(row) ? `items[${index}][expiry_date]` : null"
                                            type="date"
                                            x-model="row.expiry_date"
                                            class="ui-control purchase-detail-control mx-auto block w-[7rem] rounded-lg px-1.5 text-center text-[0.72rem]"
                                            @input.debounce.150ms="handleRowMetaInput(row)"
                                        >
                                    </td>

                                    <td class="px-0.5 py-1.5 text-center">
                                        <select
                                            :name="rowIsUsed(row) ? `items[${index}][storage_location_id]` : null"
                                            x-model="row.storage_location_id"
                                            class="ui-select-control purchase-detail-control mx-auto block w-[7.4rem] rounded-lg px-2 text-center text-[0.72rem]"
                                            @change="handleRowMetaInput(row)"
                                        >
                                            <option value="">Pilih lokasi</option>
                                            @foreach ($locationOptions as $location)
                                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td class="px-0.5 py-1.5 text-center">
                                        <input
                                            :name="rowIsUsed(row) ? `items[${index}][quantity]` : null"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            x-model="row.quantity"
                                            class="ui-control purchase-detail-control number-input-no-spinner mx-auto block w-14 rounded-lg px-1.5 text-center text-[0.78rem]"
                                            @input.debounce.150ms="refreshRow(row)"
                                        >
                                    </td>

                                    <td class="px-0.5 py-1.5 text-center">
                                        <input
                                            type="hidden"
                                            :name="rowIsUsed(row) ? `items[${index}][unit_price]` : null"
                                            :value="row.unit_price"
                                        >
                                        <input
                                            type="text"
                                            inputmode="decimal"
                                            :value="row.unit_price_display"
                                            class="ui-control purchase-detail-control mx-auto block w-[5.4rem] rounded-lg px-1.5 text-center text-[0.78rem]"
                                            @input="handleMoneyInput(row, 'unit_price', $event)"
                                        >
                                    </td>

                                    <td class="px-0.5 py-1.5 text-center">
                                        <input
                                            type="hidden"
                                            :name="rowIsUsed(row) ? `items[${index}][update_master_purchase_price]` : null"
                                            value="0"
                                        >
                                        <input
                                            type="checkbox"
                                            :name="rowIsUsed(row) ? `items[${index}][update_master_purchase_price]` : null"
                                            value="1"
                                            :checked="row.update_master_purchase_price"
                                            class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-200"
                                            title="Centang untuk update harga beli ke master obat"
                                            @change="row.update_master_purchase_price = $event.target.checked"
                                        >
                                    </td>

                                    <td class="px-0.5 py-1.5 text-center">
                                        <input
                                            :name="rowIsUsed(row) ? `items[${index}][discount_percentage]` : null"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            x-model="row.discount_percentage"
                                            class="ui-control purchase-detail-control number-input-no-spinner mx-auto block w-14 rounded-lg px-1.5 text-center text-[0.78rem]"
                                            @input.debounce.150ms="applyPercent(row)"
                                        >
                                    </td>

                                    <td class="px-0.5 py-1.5 text-center">
                                        <input
                                            type="hidden"
                                            :name="rowIsUsed(row) ? `items[${index}][discount_amount]` : null"
                                            :value="row.discount_amount"
                                        >
                                        <input
                                            type="text"
                                            inputmode="decimal"
                                            :value="row.discount_amount_display"
                                            class="ui-control purchase-detail-control mx-auto block w-[5.4rem] rounded-lg px-1.5 text-center text-[0.78rem]"
                                            @input="handleMoneyInput(row, 'discount_amount', $event)"
                                        >
                                    </td>

                                    <td class="min-w-[7.5rem] whitespace-nowrap px-2 py-1.5 text-right">
                                        <p class="font-semibold text-slate-900" x-text="currency(row.gross_total)"></p>
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
                            <label class="flex items-center gap-1 text-slate-500">
                                <span>PPN</span>
                                <input
                                    name="tax_percentage"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    x-model.number="tax_percentage"
                                    class="ui-control number-input-no-spinner w-11 rounded-lg px-1 text-center text-[0.7rem] font-semibold"
                                >
                                <span>%</span>
                                <span class="ml-1 font-semibold text-slate-900" x-text="currency(taxAmount())"></span>
                            </label>
                            <p class="text-slate-500">
                                Bruto
                                <span class="ml-1 font-semibold text-slate-900" x-text="currency(grossSubtotal())"></span>
                            </p>
                            <p class="text-slate-500">
                                Diskon
                                <span class="ml-1 font-semibold text-slate-900" x-text="currency(totalDiscount())"></span>
                            </p>
                            <p class="text-emerald-700">
                                Grand total
                                <span class="ml-1 font-semibold" x-text="currency(grandTotal())"></span>
                            </p>
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row">
                            <a
                                href="{{ route('pembelian.data-pembelian') }}"
                                class="ui-action-btn ui-action-btn--soft px-3 text-[0.72rem]"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 6h18" />
                                    <path d="M3 12h18" />
                                    <path d="M3 18h18" />
                                </svg>
                                Data Pembelian
                            </a>

                            <button
                                type="button"
                                @click="openPaymentModal()"
                                class="ui-action-btn shrink-0 border border-emerald-300 bg-emerald-500 px-3.5 text-[0.72rem] font-semibold text-white hover:bg-emerald-600"
                                :disabled="!canSubmit()"
                                :class="{ 'cursor-not-allowed opacity-60 hover:bg-emerald-500': !canSubmit() }"
                            >
                                {{ $formMethod ? 'Simpan perubahan' : 'Simpan faktur pembelian' }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <input type="hidden" name="payment_method" :value="payment_method">
        </form>

        <div
            x-cloak
            x-show="paymentModalOpen"
            x-transition.opacity
            class="fixed inset-0 z-40 overflow-y-auto bg-slate-950/50 backdrop-blur-sm"
            @click.self="closePaymentModal()"
        >
            <div class="flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6">
                <div class="panel-surface relative z-50 w-full max-w-xl p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="inline-flex rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                                Konfirmasi Pembayaran
                            </div>
                            <h3 class="mt-3 text-lg font-semibold text-slate-950">Pilih metode bayar pembelian</h3>
                            <p class="mt-1 text-xs text-slate-500">Tentukan dulu faktur ini kredit atau cash sebelum transaksi supplier disimpan.</p>
                        </div>

                        <button type="button" class="table-icon-btn" @click="closePaymentModal()" aria-label="Tutup konfirmasi pembayaran">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                                <path d="M6 6l12 12M18 6 6 18" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Nomor faktur</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900" x-text="invoice_number || '-'"></p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Supplier</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900" x-text="selectedSupplier()?.name || '-'"></p>
                        </div>

                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                            <p class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-emerald-700">Grand total</p>
                            <p class="mt-1 text-sm font-semibold text-emerald-700" x-text="currency(grandTotal())"></p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-4">
                        <div>
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Jenis pembayaran</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <button
                                    type="button"
                                    @click="setPaymentKind('credit')"
                                    class="rounded-2xl border px-4 py-3 text-left transition"
                                    :class="payment_kind === 'credit'
                                        ? 'border-amber-300 bg-amber-50 text-amber-700'
                                        : 'border-slate-200 bg-white text-slate-700 hover:border-amber-200 hover:bg-amber-50/60'"
                                >
                                    <p class="text-sm font-semibold">Kredit</p>
                                    <p class="mt-1 text-[0.72rem] text-current/80">Faktur disimpan sebagai hutang supplier yang belum lunas.</p>
                                </button>

                                <button
                                    type="button"
                                    @click="setPaymentKind('cash')"
                                    class="rounded-2xl border px-4 py-3 text-left transition"
                                    :class="payment_kind === 'cash'
                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                        : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200 hover:bg-emerald-50/60'"
                                >
                                    <p class="text-sm font-semibold">Cash</p>
                                    <p class="mt-1 text-[0.72rem] text-current/80">Faktur dianggap langsung dibayar penuh saat disimpan.</p>
                                </button>
                            </div>
                        </div>

                        <div x-show="payment_kind === 'cash'" x-transition>
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Metode bayar</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-4">
                                <button
                                    type="button"
                                    @click="selectPaymentMethod('cash')"
                                    class="ui-action-btn justify-center rounded-2xl px-3 text-[0.76rem] font-semibold transition"
                                    :class="payment_method === 'cash'
                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                        : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200 hover:bg-emerald-50/60'"
                                >
                                    Tunai
                                </button>

                                <button
                                    type="button"
                                    @click="selectPaymentMethod('qris')"
                                    class="ui-action-btn justify-center rounded-2xl px-3 text-[0.76rem] font-semibold transition"
                                    :class="payment_method === 'qris'
                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                        : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200 hover:bg-emerald-50/60'"
                                >
                                    QRIS
                                </button>

                                <button
                                    type="button"
                                    @click="selectPaymentMethod('transfer')"
                                    class="ui-action-btn justify-center rounded-2xl px-3 text-[0.76rem] font-semibold transition"
                                    :class="payment_method === 'transfer'
                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                        : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200 hover:bg-emerald-50/60'"
                                >
                                    Transfer
                                </button>

                                <button
                                    type="button"
                                    @click="selectPaymentMethod('debit')"
                                    class="ui-action-btn justify-center rounded-2xl px-3 text-[0.76rem] font-semibold transition"
                                    :class="payment_method === 'debit'
                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                        : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-200 hover:bg-emerald-50/60'"
                                >
                                    Debit
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 sm:grid-cols-3">
                            <div>
                                <p class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Jenis</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900" x-text="isCreditPayment() ? 'Kredit' : 'Cash'"></p>
                            </div>

                            <div>
                                <p class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Metode</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900" x-text="paymentMethodLabel()"></p>
                            </div>

                            <div>
                                <p class="text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Total bayar</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900" x-text="isCreditPayment() ? currency(0) : currency(grandTotal())"></p>
                            </div>
                        </div>

                        <div x-show="payment_kind === 'credit'" x-transition class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-[0.76rem] text-amber-800">
                            Pembelian akan disimpan sebagai kredit. Grand total tetap tercatat penuh, sementara status faktur menjadi belum lunas untuk tindak lanjut pembayaran supplier.
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            @click="closePaymentModal()"
                            class="ui-action-btn ui-action-btn--neutral"
                        >
                            Batal
                        </button>

                        <button
                            x-ref="confirmPurchasePaymentButton"
                            type="button"
                            @click="submitInvoice()"
                            class="ui-action-btn border border-emerald-300 bg-emerald-500 px-4 text-[0.76rem] font-semibold text-white hover:bg-emerald-600"
                            :disabled="!canConfirmPayment()"
                            :class="{ 'cursor-not-allowed opacity-60 hover:bg-emerald-500': !canConfirmPayment() }"
                        >
                            Simpan transaksi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
