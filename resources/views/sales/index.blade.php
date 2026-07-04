<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $section }}</span>
                <span class="text-slate-300">/</span>
                <span class="text-slate-600">{{ $page['label'] }}</span>
            </div>

            <a
                href="{{ route('penjualan.kasir-penjualan') }}"
                class="ui-action-btn ui-action-btn--soft px-4"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                    <path d="M10 4.167v11.666M4.167 10h11.666" />
                </svg>
                Kasir Penjualan
            </a>
        </div>
    </x-slot>

    <div
        x-data="{
            detailModalOpen: false,
            detailSale: null,
            deleteModalOpen: false,
            deleteFormAction: '',
            deleteTarget: null,
            openDetail(detail) {
                this.detailSale = detail;
                this.detailModalOpen = true;
            },
            closeDetail() {
                this.detailModalOpen = false;
                this.detailSale = null;
            },
            openDeleteDialog(payload = {}) {
                this.deleteTarget = {
                    title: payload.title ?? 'Hapus penjualan ini?',
                    description: payload.description ?? 'Penjualan yang dihapus akan mengembalikan stok ke batch asal.',
                    warning: payload.warning ?? 'Pastikan transaksi ini memang perlu dibatalkan sebelum dihapus.',
                    confirm_label: payload.confirm_label ?? 'Ya, hapus penjualan',
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
        <div class="panel-surface px-5 py-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] text-slate-700">
                    <span class="font-medium">Total transaksi</span>
                    <span class="font-semibold text-slate-900">{{ number_format($stats['total']) }}</span>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] text-slate-700">
                    <span class="font-medium">Hari ini</span>
                    <span class="font-semibold text-slate-900">{{ number_format($stats['today']) }}</span>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] text-slate-700">
                    <span class="font-medium">Total penjualan</span>
                    <span class="font-semibold text-slate-900">Rp {{ number_format($stats['grand_total'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <section class="panel-surface overflow-hidden p-0">
            <div class="border-b border-slate-200/80 px-5 py-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <h3 class="section-title">Riwayat penjualan</h3>

                    <form method="GET" action="{{ route('penjualan.data-penjualan') }}" class="flex flex-nowrap items-center gap-2">
                        <label class="sr-only" for="search">Cari penjualan</label>
                        <input
                            id="search"
                            name="search"
                            type="text"
                            value="{{ $search }}"
                            placeholder="Cari no jual, pelanggan, batch, atau obat"
                            class="ui-control min-w-0 flex-1 px-2.5 text-[0.72rem] placeholder:text-[0.68rem]"
                        >

                        <input
                            name="date_from"
                            type="date"
                            value="{{ $dateFrom }}"
                            class="ui-control w-[160px] shrink-0 px-2.5 text-[0.72rem]"
                        >

                        <input
                            name="date_to"
                            type="date"
                            value="{{ $dateTo }}"
                            class="ui-control w-[160px] shrink-0 px-2.5 text-[0.72rem]"
                        >

                        <button
                            type="submit"
                            class="ui-action-btn ui-action-btn--soft shrink-0 px-3 text-[0.72rem]"
                        >
                            Terapkan
                        </button>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                    <thead class="bg-slate-50/90">
                        <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                            <th class="px-4 py-3">No Jual</th>
                            <th class="px-3 py-3">Tanggal</th>
                            <th class="px-3 py-3">Pelanggan</th>
                            <th class="px-3 py-3">Golongan</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3 text-right">Total</th>
                            <th class="px-3 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($sales as $sale)
                            <tr class="align-top">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ $sale->sale_number }}</p>
                                </td>
                                <td class="px-3 py-3 text-slate-700">{{ $sale->sale_date?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-slate-900">{{ $sale->customer_name ?: '-' }}</p>
                                    <p class="mt-1 text-[0.66rem] text-slate-400">{{ $sale->customer_phone ?: 'Tanpa nomor' }}</p>
                                </td>
                                <td class="px-3 py-3 text-slate-700">
                                    {{ $sale->customerGroup?->name ?: '-' }}
                                    <p class="mt-1 text-[0.66rem] text-emerald-600">{{ number_format((float) $sale->customer_group_markup_percentage, 2, ',', '.') }}%</p>
                                </td>
                                <td class="px-3 py-3">
                                    <span @class([
                                        'inline-flex rounded-full border px-2.5 py-1 text-[0.66rem] font-semibold uppercase tracking-[0.14em]',
                                        $sale->payment_status_tone,
                                    ])>
                                        {{ $sale->payment_status_label }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-right font-semibold text-emerald-700">Rp {{ number_format((float) $sale->grand_total, 0, ',', '.') }}</td>
                                <td class="px-3 py-3 align-middle">
                                    <div
                                        x-data="floatingActionMenu()"
                                        @keydown.escape.window="close()"
                                        @click.window="if (open && ! $refs.trigger.contains($event.target) && ! ($refs.panel && $refs.panel.contains($event.target))) close()"
                                        class="relative flex min-h-8 items-center justify-center"
                                    >
                                        <button
                                            x-ref="trigger"
                                            type="button"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                                            title="Aksi penjualan"
                                            aria-label="Aksi {{ $sale->sale_number }}"
                                            @click="toggleMenu()"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                                                <circle cx="5" cy="12" r="1.75" />
                                                <circle cx="12" cy="12" r="1.75" />
                                                <circle cx="19" cy="12" r="1.75" />
                                            </svg>
                                            <span class="sr-only">Aksi</span>
                                        </button>

                                        <template x-teleport="body">
                                            <div
                                                x-cloak
                                                x-show="open"
                                                x-ref="panel"
                                                x-transition.opacity.duration.120ms
                                                x-bind:style="menuStyles"
                                                class="fixed z-[70] w-44 overflow-hidden rounded-xl border border-slate-200 bg-white py-1.5 shadow-xl shadow-slate-200/70"
                                            >
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-[0.8rem] font-medium text-slate-700 transition hover:bg-slate-50 hover:text-emerald-700"
                                                    @click="close(); openDetail(@js($detailPayloads[$sale->id] ?? null))"
                                                >
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M2.06 12.35a1 1 0 0 1 0-.7C3.2 8.38 6.52 5 12 5s8.8 3.38 9.94 6.65a1 1 0 0 1 0 .7C20.8 15.62 17.48 19 12 19s-8.8-3.38-9.94-6.65Z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                    </svg>
                                                    <span>Detail</span>
                                                </button>

                                                <a
                                                    href="{{ route('penjualan.data-penjualan.edit', $sale) }}"
                                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-[0.8rem] font-medium text-slate-700 transition hover:bg-slate-50 hover:text-emerald-700"
                                                    @click="close()"
                                                >
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M12 20h9" />
                                                        <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                                                    </svg>
                                                    <span>Edit</span>
                                                </a>

                                                <a
                                                    href="{{ route('penjualan.data-penjualan.print', $sale) }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-[0.8rem] font-medium text-slate-700 transition hover:bg-slate-50 hover:text-emerald-700"
                                                    @click="close()"
                                                >
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M6 9V4.75A1.75 1.75 0 0 1 7.75 3h8.5A1.75 1.75 0 0 1 18 4.75V9" />
                                                        <path d="M6 18H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-1" />
                                                        <path d="M8 14h8v7H8z" />
                                                        <path d="M17 12h.01" />
                                                    </svg>
                                                    <span>Print PDF</span>
                                                </a>

                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-[0.8rem] font-medium text-rose-700 transition hover:bg-rose-50 hover:text-rose-800"
                                                    @click="close(); openDeleteDialog(@js([
                                                        'action' => route('penjualan.data-penjualan.destroy', $sale),
                                                        'title' => 'Hapus transaksi penjualan ini?',
                                                        'description' => 'Penjualan '.$sale->sale_number.' akan dihapus dan stok obat akan dikembalikan ke batch asal.',
                                                        'warning' => 'Pastikan transaksi ini memang perlu dibatalkan. Jika sudah ada retur penjualan, penghapusan akan ditolak.',
                                                        'name' => $sale->customer_name ?: $sale->sale_number,
                                                        'code' => $sale->sale_number,
                                                        'confirm_label' => 'Ya, hapus penjualan',
                                                    ]))"
                                                >
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M3 6h18" />
                                                        <path d="M8 6V4.75A1.75 1.75 0 0 1 9.75 3h4.5A1.75 1.75 0 0 1 16 4.75V6" />
                                                        <path d="M19 6l-.82 11.47A2 2 0 0 1 16.19 19H7.81a2 2 0 0 1-1.99-1.53L5 6" />
                                                        <path d="M10 10.5v5" />
                                                        <path d="M14 10.5v5" />
                                                    </svg>
                                                    <span>Hapus</span>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-14 text-center">
                                    <div class="mx-auto max-w-md space-y-3">
                                        <div class="empty-title">{{ $search !== '' || $dateFrom !== '' || $dateTo !== '' ? 'Penjualan tidak ditemukan' : 'Belum ada transaksi penjualan' }}</div>
                                        <p class="content-copy">
                                            {{ $search !== '' || $dateFrom !== '' || $dateTo !== '' ? 'Coba ubah kata kunci atau periode pencarian untuk melihat transaksi penjualan.' : 'Histori transaksi kasir akan tampil di sini setelah penjualan pertama disimpan.' }}
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($sales->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4">
                    {{ $sales->links() }}
                </div>
            @endif
        </section>

        <x-master-delete-modal />

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
                            <div class="inline-flex rounded-full border border-rose-100 bg-rose-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">
                                Detail Penjualan
                            </div>
                            <h3 class="mt-3 text-lg font-semibold text-slate-950" x-text="detailSale?.sale_number"></h3>
                            <p class="mt-1 text-xs text-slate-500">
                                <span x-text="detailSale?.sale_date"></span>
                                <span class="px-1 text-slate-300">/</span>
                                <span x-text="detailSale?.customer"></span>
                                <span class="px-1 text-slate-300">/</span>
                                <span x-text="detailSale?.group_name"></span>
                                <span class="px-1 text-slate-300">/</span>
                                <span x-text="detailSale?.payment_status"></span>
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
                                    <th class="px-3 py-3">Qty</th>
                                    <th class="px-3 py-3 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/80 bg-white">
                                <template x-for="item in detailSale?.items ?? []" :key="item.id">
                                    <tr>
                                        <td class="px-3 py-3">
                                            <p class="font-semibold text-slate-900" x-text="item.medicine"></p>
                                            <p class="mt-1 text-[0.66rem] text-slate-400" x-text="item.medicine_code"></p>
                                        </td>
                                        <td class="px-3 py-3 text-slate-700" x-text="item.batch_number"></td>
                                        <td class="px-3 py-3 text-slate-700" x-text="item.expiry_date"></td>
                                        <td class="px-3 py-3 text-slate-700">
                                            <p class="font-semibold text-slate-900" x-text="item.quantity"></p>
                                            <p class="mt-1 text-[0.66rem] text-slate-400" x-text="item.unit_price"></p>
                                        </td>
                                        <td class="px-3 py-3 text-right font-semibold text-slate-900" x-text="item.line_total"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex flex-wrap justify-end gap-x-5 gap-y-2 text-[0.76rem]">
                        <p class="text-slate-500">Item <span class="ml-1 font-semibold text-slate-900" x-text="detailSale?.item_count"></span></p>
                        <p class="text-slate-500">Subtotal <span class="ml-1 font-semibold text-slate-900" x-text="detailSale?.subtotal"></span></p>
                        <p x-show="detailSale?.social_amount && detailSale.social_amount !== 'Rp 0'" class="text-sky-700">Sosial <span class="ml-1 font-semibold" x-text="detailSale?.social_amount"></span></p>
                        <p class="text-slate-500">Bayar <span class="ml-1 font-semibold text-slate-900" x-text="detailSale?.paid_amount"></span></p>
                        <p class="text-slate-500">Kembali <span class="ml-1 font-semibold text-slate-900" x-text="detailSale?.change_amount"></span></p>
                        <p class="text-emerald-700">Total <span class="ml-1 font-semibold" x-text="detailSale?.total_amount"></span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
