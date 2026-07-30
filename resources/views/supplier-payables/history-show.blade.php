<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $section }}</span><span>/</span>
                <a href="{{ route('keuangan.riwayat-pembayaran-hutang', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="text-slate-500 hover:text-emerald-700">{{ $page['label'] }}</a>
                <span>/</span><span class="text-slate-600">Riwayat Faktur</span>
            </div>
            <a href="{{ route('keuangan.riwayat-pembayaran-hutang', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="ui-action-btn ui-action-btn--neutral px-4">Kembali</a>
        </div>
    </x-slot>

    <div
        x-data="{
            detailModalOpen: false,
            detailTarget: null,
            deleteModalOpen: false,
            deleteFormAction: '',
            deleteTarget: null,
            openDetail(invoice) {
                this.detailTarget = invoice;
                this.detailModalOpen = true;
            },
            closeDetail() {
                this.detailModalOpen = false;
                this.detailTarget = null;
            },
            openDeleteDialog(payload = {}) {
                this.deleteTarget = payload;
                this.deleteFormAction = payload.action ?? '';
                this.deleteModalOpen = true;
                this.$nextTick(() => this.$refs.cancelDeleteButton?.focus());
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
            <div class="flex flex-wrap gap-2.5">
                <div class="rounded-full bg-slate-100 px-4 py-2 text-[0.78rem]">Nama supplier <strong>{{ $detail['supplier_name'] }}</strong></div>
                <div class="rounded-full bg-slate-100 px-4 py-2 text-[0.78rem]">Jumlah faktur <strong>{{ $detail['invoice_count'] }}</strong></div>
                <div class="rounded-full bg-emerald-50 px-4 py-2 text-[0.78rem] text-emerald-800">Total pembayaran <strong>{{ $detail['total_amount'] }}</strong></div>
            </div>
        </div>

        <section class="panel-surface overflow-hidden p-0">
            <div class="border-b border-slate-200/80 px-5 py-4">
                <h3 class="section-title">Daftar riwayat faktur hutang</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                    <thead class="bg-slate-50/90 text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                        <tr>
                            <th class="px-3 py-3 text-left">No Faktur</th>
                            <th class="px-3 py-3 text-left">Tanggal Faktur</th>
                            <th class="px-3 py-3 text-left">No Bayar</th>
                            <th class="px-3 py-3 text-left">Tgl Bayar</th>
                            <th class="px-3 py-3 text-left">Metode</th>
                            <th class="px-3 py-3 text-right">Total</th>
                            <th class="px-3 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($detail['invoices'] as $invoice)
                            <tr>
                                <td class="px-3 py-3 font-semibold text-slate-900">{{ $invoice['invoice_number'] }}</td>
                                <td class="px-3 py-3 text-slate-700">{{ $invoice['invoice_date'] }}</td>
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-slate-900">{{ $invoice['payment_number'] }}</p>
                                    <p class="mt-1 text-[0.66rem] text-slate-400">{{ $invoice['reference_number'] }}</p>
                                </td>
                                <td class="px-3 py-3 text-slate-700">{{ $invoice['payment_date'] }}</td>
                                <td class="px-3 py-3 text-slate-700">{{ $invoice['payment_method'] }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-emerald-700">{{ $invoice['amount_paid'] }}</td>
                                <td class="px-3 py-3">
                                    <div class="table-action-group justify-center">
                                        <button type="button" @click='openDetail(@json($invoice))' class="table-icon-btn" title="Lihat detail barang faktur {{ $invoice['invoice_number'] }}" aria-label="Lihat detail barang faktur {{ $invoice['invoice_number'] }}">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M2.06 12.35a1 1 0 0 1 0-.7C3.2 8.38 6.52 5 12 5s8.8 3.38 9.94 6.65a1 1 0 0 1 0 .7C20.8 15.62 17.48 19 12 19s-8.8-3.38-9.94-6.65Z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </button>
                                        <button
                                            type="button"
                                            @click="openDeleteDialog({
                                                action: @js($invoice['delete_url']),
                                                title: 'Hapus pembayaran hutang ini?',
                                                description: 'Pembayaran {{ $invoice['payment_number'] }} untuk faktur {{ $invoice['invoice_number'] }} akan dihapus.',
                                                warning: 'Saldo hutang faktur akan dikembalikan dan faktur kembali muncul pada daftar Hutang Supplier.',
                                                name: @js($detail['supplier_name']),
                                                code: @js($invoice['payment_number']),
                                                confirm_label: 'Ya, hapus pembayaran',
                                            })"
                                            class="table-icon-btn table-icon-btn--danger"
                                            title="Hapus pembayaran {{ $invoice['payment_number'] }}"
                                            aria-label="Hapus pembayaran {{ $invoice['payment_number'] }}"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M3 6h18"/><path d="M8 6V4.75A1.75 1.75 0 0 1 9.75 3h4.5A1.75 1.75 0 0 1 16 4.75V6"/><path d="M19 6l-.82 11.47A2 2 0 0 1 16.19 19H7.81a2 2 0 0 1-1.99-1.53L5 6"/><path d="M10 10.5v5M14 10.5v5"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-14 text-center"><div class="empty-title">Belum ada faktur pada periode ini</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div x-cloak x-show="detailModalOpen" x-transition.opacity class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/55 backdrop-blur-sm" @click.self="closeDetail()">
            <div class="flex min-h-full items-center justify-center p-3 sm:p-4">
                <div class="panel-surface relative z-50 w-full max-w-4xl overflow-hidden p-0">
                    <div class="border-b border-sky-100 bg-gradient-to-br from-sky-50 via-white to-slate-50 px-5 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[0.66rem] font-semibold uppercase tracking-[0.18em] text-sky-500">Detail Barang Faktur</p>
                                <h3 class="mt-1 text-base font-semibold text-slate-950" x-text="detailTarget?.invoice_number"></h3>
                                <p class="mt-1 text-[0.72rem] text-slate-600">Pembayaran <span class="font-semibold text-slate-900" x-text="detailTarget?.amount_paid"></span></p>
                            </div>
                            <button type="button" class="table-icon-btn" @click="closeDetail()" aria-label="Tutup detail barang">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M6 6l12 12M18 6 6 18"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="px-5 py-4">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200/80 text-[0.76rem]">
                                <thead class="bg-slate-50/90 text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                    <tr>
                                        <th class="px-3 py-3 text-left">Barang</th>
                                        <th class="px-3 py-3 text-left">Batch</th>
                                        <th class="px-3 py-3 text-center">Qty</th>
                                        <th class="px-3 py-3 text-left">Satuan</th>
                                        <th class="px-3 py-3 text-right">Harga</th>
                                        <th class="px-3 py-3 text-right">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200/80 bg-white">
                                    <template x-if="(detailTarget?.items?.length ?? 0) > 0">
                                        <template x-for="(item, index) in detailTarget.items" :key="`${detailTarget?.id}-${index}`">
                                            <tr>
                                                <td class="px-3 py-3 font-semibold text-slate-900" x-text="item.medicine_name"></td>
                                                <td class="px-3 py-3 text-slate-700" x-text="item.batch_number"></td>
                                                <td class="px-3 py-3 text-center font-semibold text-slate-900" x-text="item.quantity"></td>
                                                <td class="px-3 py-3 text-slate-700" x-text="item.unit"></td>
                                                <td class="px-3 py-3 text-right text-slate-900" x-text="item.unit_price"></td>
                                                <td class="px-3 py-3 text-right font-semibold text-emerald-700" x-text="item.line_total"></td>
                                            </tr>
                                        </template>
                                    </template>
                                    <template x-if="(detailTarget?.items?.length ?? 0) === 0">
                                        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">Belum ada detail barang untuk faktur ini.</td></tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-master-delete-modal />
    </div>
</x-app-layout>
