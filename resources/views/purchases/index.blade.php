<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $section }}</span>
                <span class="text-slate-300">/</span>
                <span class="text-slate-600">{{ $page['label'] }}</span>
            </div>

            <a
                href="{{ route('pembelian.input-faktur-pembelian') }}"
                class="ui-action-btn ui-action-btn--soft px-4"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                    <path d="M10 4.167v11.666M4.167 10h11.666" />
                </svg>
                Input Faktur
            </a>
        </div>
    </x-slot>

    <div
        x-data="{
            detailModalOpen: false,
            detailInvoice: null,
            deleteModalOpen: false,
            deleteInvoice: null,
            openDetail(invoice) {
                this.detailInvoice = invoice;
                this.detailModalOpen = true;
            },
            closeDetail() {
                this.detailModalOpen = false;
                this.detailInvoice = null;
            },
            openDelete(invoice) {
                this.deleteInvoice = invoice;
                this.$refs.deleteInvoiceForm.setAttribute('action', invoice.delete_url);
                this.deleteModalOpen = true;
                this.$nextTick(() => this.$refs.cancelDeleteButton?.focus());
            },
            closeDelete() {
                this.deleteModalOpen = false;
                this.deleteInvoice = null;
                this.$refs.deleteInvoiceForm.removeAttribute('action');
            },
        }"
        @keydown.escape.window="closeDetail(); closeDelete()"
        class="space-y-5"
    >
        <div class="grid gap-3 md:grid-cols-3">
            <div class="panel-surface px-4 py-3">
                <p class="text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">Total faktur</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ number_format($stats['total']) }}</p>
            </div>

            <div class="panel-surface px-4 py-3">
                <p class="text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">Faktur hari ini</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ number_format($stats['today']) }}</p>
            </div>

            <div class="panel-surface px-4 py-3">
                <p class="text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">Total pembelian</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">
                    Rp {{ number_format($stats['grand_total'], 0, ',', '.') }}
                </p>
            </div>
        </div>

        <section class="panel-surface overflow-hidden p-0">
            <div class="border-b border-slate-200/80 px-5 py-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <h3 class="section-title">Daftar pembelian</h3>

                    <form method="GET" action="{{ route('pembelian.data-pembelian') }}" class="grid gap-2 sm:grid-cols-[minmax(180px,1fr),130px,130px,auto,auto]">
                        <label class="sr-only" for="search">Cari pembelian</label>
                        <input
                            id="search"
                            name="search"
                            type="text"
                            value="{{ $search }}"
                            placeholder="Cari faktur, barang, batch, supplier"
                            class="ui-control text-[0.72rem] placeholder:text-[0.66rem]"
                        >

                        <label class="sr-only" for="date_from">Tanggal awal</label>
                        <input
                            id="date_from"
                            name="date_from"
                            type="date"
                            value="{{ $dateFrom }}"
                            class="ui-control px-2 text-[0.72rem]"
                        >

                        <label class="sr-only" for="date_to">Tanggal akhir</label>
                        <input
                            id="date_to"
                            name="date_to"
                            type="date"
                            value="{{ $dateTo }}"
                            class="ui-control px-2 text-[0.72rem]"
                        >

                        <button
                            type="submit"
                            class="ui-action-btn ui-action-btn--soft text-[0.72rem]"
                        >
                            Terapkan
                        </button>

                        <a
                            href="{{ route('pembelian.data-pembelian') }}"
                            class="ui-action-btn ui-action-btn--neutral text-[0.72rem]"
                        >
                            Reset
                        </a>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[960px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                    <thead class="bg-slate-50/90">
                        <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                            <th class="px-4 py-3">No Faktur</th>
                            <th class="px-3 py-3">Tanggal</th>
                            <th class="px-3 py-3">Barang</th>
                            <th class="px-3 py-3">Batch</th>
                            <th class="px-3 py-3 text-center">Jumlah</th>
                            <th class="px-3 py-3">Supplier</th>
                            <th class="px-3 py-3 text-right">Grand Total</th>
                            <th class="px-3 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($invoices as $invoice)
                            @php
                                $items = $invoice->items;
                                $rowspan = max($items->count(), 1);
                                $detailPayload = [
                                    'invoice_number' => $invoice->invoice_number,
                                    'invoice_date' => $invoice->invoice_date?->translatedFormat('d M Y') ?? '-',
                                    'supplier' => $invoice->supplier?->name ?: '-',
                                    'subtotal' => 'Rp '.number_format((float) $invoice->subtotal, 0, ',', '.'),
                                    'discount' => 'Rp '.number_format((float) $invoice->discount_amount, 0, ',', '.'),
                                    'tax_percentage' => number_format((float) $invoice->tax_percentage, 2, ',', '.'),
                                    'tax_amount' => 'Rp '.number_format((float) $invoice->tax_amount, 0, ',', '.'),
                                    'grand_total' => 'Rp '.number_format((float) $invoice->grand_total, 0, ',', '.'),
                                    'items' => $items->map(fn ($item) => [
                                        'id' => $item->id,
                                        'medicine' => $item->medicine?->name ?: '-',
                                        'medicine_code' => $item->medicine?->code ?: '-',
                                        'batch' => $item->batch_number ?: '-',
                                        'expiry' => $item->expiry_date?->translatedFormat('d M Y') ?? '-',
                                        'hpp' => 'Rp '.rtrim(rtrim(number_format((float) ($item->stockBatch?->purchase_price ?? 0), 2, ',', '.'), '0'), ',')
                                            .' / '.($item->purchase_unit ?: 'unit'),
                                        'quantity' => number_format((float) $item->quantity, 2, ',', '.').' '.($item->purchase_unit ?: ''),
                                        'line_total' => 'Rp '.number_format((float) $item->line_total, 0, ',', '.'),
                                    ])->values()->all(),
                                ];
                            @endphp

                            @forelse ($items as $item)
                                <tr @class([
                                    'align-top',
                                    'border-t-2 border-emerald-100' => $loop->first,
                                ])>
                                    @if ($loop->first)
                                        <td rowspan="{{ $rowspan }}" class="bg-emerald-50/30 px-4 py-3">
                                            <p class="font-semibold text-slate-900">{{ $invoice->invoice_number }}</p>
                                            <p class="mt-1 text-[0.66rem] text-slate-400">{{ $items->count() }} barang</p>
                                        </td>
                                        <td rowspan="{{ $rowspan }}" class="px-3 py-3 text-slate-700">
                                            {{ $invoice->invoice_date?->translatedFormat('d M Y') ?? '-' }}
                                        </td>
                                    @endif

                                    <td class="px-3 py-3">
                                        <p class="font-semibold text-slate-900">{{ $item->medicine?->name ?: '-' }}</p>
                                        <p class="mt-1 text-[0.66rem] text-slate-400">{{ $item->medicine?->code ?: '-' }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-slate-700">
                                        {{ $item->batch_number ?: '-' }}
                                    </td>
                                    <td class="px-3 py-3 text-center font-semibold text-slate-900">
                                        {{ number_format((float) $item->quantity, 2, ',', '.') }}
                                        <span class="text-[0.66rem] font-medium text-slate-500">{{ $item->purchase_unit }}</span>
                                    </td>

                                    @if ($loop->first)
                                        <td rowspan="{{ $rowspan }}" class="px-3 py-3 text-slate-700">
                                            {{ $invoice->supplier?->name ?: '-' }}
                                        </td>
                                        <td rowspan="{{ $rowspan }}" class="px-3 py-3 text-right font-semibold text-emerald-700">
                                            Rp {{ number_format((float) $invoice->grand_total, 0, ',', '.') }}
                                        </td>
                                        <td rowspan="{{ $rowspan }}" class="px-3 py-3 align-middle">
                                            <div class="table-action-group">
                                                <button
                                                    type="button"
                                                    @click="openDetail(@js($detailPayload))"
                                                    class="table-icon-btn"
                                                    title="Lihat detail faktur"
                                                    aria-label="Lihat detail faktur {{ $invoice->invoice_number }}"
                                                >
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M2.06 12.35a1 1 0 0 1 0-.7C3.2 8.38 6.52 5 12 5s8.8 3.38 9.94 6.65a1 1 0 0 1 0 .7C20.8 15.62 17.48 19 12 19s-8.8-3.38-9.94-6.65Z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                    </svg>
                                                    <span class="sr-only">Lihat detail</span>
                                                </button>

                                                <a
                                                    href="{{ route('pembelian.data-pembelian.edit', $invoice) }}"
                                                    class="table-icon-btn"
                                                    title="Ubah faktur"
                                                    aria-label="Ubah faktur {{ $invoice->invoice_number }}"
                                                >
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M12 20h9" />
                                                        <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                                                    </svg>
                                                    <span class="sr-only">Ubah</span>
                                                </a>

                                                <button
                                                    type="button"
                                                    @click="openDelete(@js([
                                                        'invoice_number' => $invoice->invoice_number,
                                                        'delete_url' => route('pembelian.data-pembelian.destroy', $invoice),
                                                    ]))"
                                                    class="table-icon-btn table-icon-btn--danger"
                                                    title="Hapus faktur"
                                                    aria-label="Hapus faktur {{ $invoice->invoice_number }}"
                                                >
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M3 6h18" />
                                                        <path d="M8 6V4.75A1.75 1.75 0 0 1 9.75 3h4.5A1.75 1.75 0 0 1 16 4.75V6" />
                                                        <path d="M19 6l-.82 11.47A2 2 0 0 1 16.19 19H7.81a2 2 0 0 1-1.99-1.53L5 6" />
                                                        <path d="M10 10.5v5" />
                                                        <path d="M14 10.5v5" />
                                                    </svg>
                                                    <span class="sr-only">Hapus</span>
                                                </button>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ $invoice->invoice_number }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $invoice->invoice_date?->translatedFormat('d M Y') ?? '-' }}</td>
                                    <td colspan="3" class="px-3 py-3 text-slate-400">Detail barang belum tersedia.</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $invoice->supplier?->name ?: '-' }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-emerald-700">Rp {{ number_format((float) $invoice->grand_total, 0, ',', '.') }}</td>
                                    <td></td>
                                </tr>
                            @endforelse
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-14 text-center">
                                    <div class="mx-auto max-w-md space-y-3">
                                        <div class="empty-title">Belum ada data pembelian</div>
                                        <p class="content-copy">Input faktur pembelian pertama untuk mulai mencatat transaksi supplier dan penerimaan stok.</p>
                                        <a
                                            href="{{ route('pembelian.input-faktur-pembelian') }}"
                                            class="ui-action-btn ui-action-btn--soft"
                                        >
                                            Input faktur pertama
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($invoices->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4">
                    {{ $invoices->links() }}
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
                <div class="panel-surface relative z-50 max-h-[calc(100vh-2rem)] w-full max-w-4xl overflow-y-auto p-5 sm:max-h-[calc(100vh-3rem)] sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="inline-flex rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                                Detail Faktur
                            </div>
                            <h3 class="mt-3 text-lg font-semibold text-slate-950" x-text="detailInvoice?.invoice_number"></h3>
                            <p class="mt-1 text-xs text-slate-500">
                                <span x-text="detailInvoice?.invoice_date"></span>
                                <span class="px-1 text-slate-300">/</span>
                                <span x-text="detailInvoice?.supplier"></span>
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
                                    <th class="px-3 py-3">HPP / Unit</th>
                                    <th class="px-3 py-3">Expired</th>
                                    <th class="px-3 py-3">Jumlah</th>
                                    <th class="px-3 py-3 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/80 bg-white">
                                <template x-for="item in detailInvoice?.items ?? []" :key="item.id">
                                    <tr>
                                        <td class="px-3 py-3">
                                            <p class="font-semibold text-slate-900" x-text="item.medicine"></p>
                                            <p class="mt-1 text-[0.66rem] text-slate-400" x-text="item.medicine_code"></p>
                                        </td>
                                        <td class="px-3 py-3 text-slate-700" x-text="item.batch"></td>
                                        <td class="px-3 py-3 text-slate-700" x-text="item.hpp"></td>
                                        <td class="px-3 py-3 text-slate-700" x-text="item.expiry"></td>
                                        <td class="px-3 py-3 text-slate-700" x-text="item.quantity"></td>
                                        <td class="px-3 py-3 text-right font-semibold text-slate-900" x-text="item.line_total"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex flex-wrap justify-end gap-x-5 gap-y-2 text-[0.76rem]">
                        <p class="text-slate-500">Subtotal <span class="ml-1 font-semibold text-slate-900" x-text="detailInvoice?.subtotal"></span></p>
                        <p class="text-slate-500">Diskon <span class="ml-1 font-semibold text-slate-900" x-text="detailInvoice?.discount"></span></p>
                        <p class="text-slate-500">PPN <span class="ml-1 font-semibold text-slate-900" x-text="detailInvoice?.tax_amount"></span></p>
                        <p class="text-emerald-700">Grand Total <span class="ml-1 font-semibold" x-text="detailInvoice?.grand_total"></span></p>
                    </div>
                </div>
            </div>
        </div>

        <div
            x-cloak
            x-show="deleteModalOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/55 backdrop-blur-sm"
            @click.self="closeDelete()"
        >
            <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
                <div
                    x-show="deleteModalOpen"
                    x-transition:enter="transition duration-200 ease-out"
                    x-transition:enter-start="translate-y-2 scale-95 opacity-0"
                    x-transition:enter-end="translate-y-0 scale-100 opacity-100"
                    x-transition:leave="transition duration-150 ease-in"
                    x-transition:leave-start="translate-y-0 scale-100 opacity-100"
                    x-transition:leave-end="translate-y-2 scale-95 opacity-0"
                    class="panel-surface relative z-50 w-full max-w-md overflow-hidden p-0"
                >
                    <div class="border-b border-rose-100 bg-gradient-to-br from-rose-50 via-white to-amber-50 px-5 py-5">
                        <div class="flex items-start gap-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-rose-200 bg-white text-rose-600 shadow-sm">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 9v4" />
                                    <path d="M12 17h.01" />
                                    <path d="M10.3 3.83 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.83a2 2 0 0 0-3.4 0Z" />
                                </svg>
                            </div>

                            <div>
                                <p class="text-[0.66rem] font-semibold uppercase tracking-[0.18em] text-rose-500">Konfirmasi Hapus</p>
                                <h3 class="mt-1 text-base font-semibold text-slate-950">Hapus faktur pembelian?</h3>
                                <p class="mt-1.5 text-xs leading-5 text-slate-600">
                                    Faktur <span class="font-semibold text-slate-900" x-text="deleteInvoice?.invoice_number"></span> dan stok penerimaan terkait akan dihapus.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="px-5 py-4">
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-[0.72rem] leading-5 text-amber-800">
                            Penghapusan akan otomatis ditolak jika stok sudah digunakan, disesuaikan, memiliki retur, atau faktur sudah memiliki pembayaran.
                        </div>

                        <div class="mt-4 flex justify-end gap-2">
                            <button
                                x-ref="cancelDeleteButton"
                                type="button"
                                class="ui-action-btn ui-action-btn--neutral"
                                @click="closeDelete()"
                            >
                                Batal
                            </button>

                            <form x-ref="deleteInvoiceForm" method="POST">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="search" value="{{ $search }}">
                                <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                                <input type="hidden" name="date_to" value="{{ $dateTo }}">

                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-rose-600 bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:border-rose-700 hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-200"
                                >
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 6h18" />
                                        <path d="M8 6V4.75A1.75 1.75 0 0 1 9.75 3h4.5A1.75 1.75 0 0 1 16 4.75V6" />
                                        <path d="M19 6l-.82 11.47A2 2 0 0 1 16.19 19H7.81a2 2 0 0 1-1.99-1.53L5 6" />
                                    </svg>
                                    Ya, hapus faktur
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
