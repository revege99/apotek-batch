<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $section }}</span>
                <span class="text-slate-300">/</span>
                <a href="{{ route('keuangan.riwayat-pembayaran') }}" class="text-slate-500 transition hover:text-emerald-700">{{ $page['label'] }}</a>
                <span class="text-slate-300">/</span>
                <span class="text-slate-600">Detail Pembayaran</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('keuangan.riwayat-pembayaran', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="ui-action-btn ui-action-btn--neutral px-4"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m15 18-6-6 6-6" />
                    </svg>
                    Kembali
                </a>

                <a
                    href="{{ $detail['print_url'] }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="ui-action-btn ui-action-btn--soft px-4"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 9V4.75A1.75 1.75 0 0 1 7.75 3h8.5A1.75 1.75 0 0 1 18 4.75V9" />
                        <path d="M6 18H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-1" />
                        <path d="M8 14h8v7H8z" />
                        <path d="M17 12h.01" />
                    </svg>
                    Cetak Bukti
                </a>
            </div>
        </div>
    </x-slot>

    <div
        x-data="{
            detailModalOpen: false,
            detailTarget: null,
            deleteModalOpen: false,
            deleteFormAction: '',
            deleteTarget: null,
            openDetail(payment) {
                this.detailTarget = payment;
                this.detailModalOpen = true;
            },
            closeDetail() {
                this.detailModalOpen = false;
                this.detailTarget = null;
            },
            openDeleteDialog(payload = {}) {
                this.deleteTarget = {
                    title: payload.title ?? 'Hapus pembayaran ini?',
                    description: payload.description ?? 'Pembayaran yang dihapus akan mengurangi nominal bayar pada faktur kredit terkait.',
                    warning: payload.warning ?? 'Pastikan pembayaran ini memang perlu dibatalkan sebelum dihapus.',
                    confirm_label: payload.confirm_label ?? 'Ya, hapus pembayaran',
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
                    <span class="font-medium">Nama pelanggan</span>
                    <span class="font-semibold text-slate-900">{{ $detail['customer_name'] }}</span>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] text-slate-700">
                    <span class="font-medium">Jumlah bayar</span>
                    <span class="font-semibold text-slate-900">{{ $detail['payment_count'] }}</span>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] text-slate-700">
                    <span class="font-medium">Total pembayaran</span>
                    <span class="font-semibold text-slate-900">{{ $detail['total_amount'] }}</span>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] text-slate-700">
                    <span class="font-medium">Periode</span>
                    <span class="font-semibold text-slate-900">{{ $detail['period_label'] }}</span>
                </div>
            </div>
        </div>

        <section class="panel-surface overflow-hidden p-0">
            <div class="border-b border-slate-200/80 px-5 py-4">
                <h3 class="section-title">Daftar pembayaran piutang</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[960px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                    <thead class="bg-slate-50/90 text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                        <tr>
                            <th class="px-3 py-3">No Bayar</th>
                            <th class="px-3 py-3">No Faktur</th>
                            <th class="px-3 py-3">Tanggal Faktur</th>
                            <th class="px-3 py-3">Tgl Bayar</th>
                            <th class="px-3 py-3">Metode</th>
                            <th class="px-3 py-3 text-right">Total</th>
                            <th class="px-3 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($detail['payments'] as $payment)
                            <tr>
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-slate-900">{{ $payment['payment_number'] }}</p>
                                    <p class="mt-1 text-[0.66rem] text-slate-400">{{ $payment['reference_number'] }}</p>
                                </td>
                                <td class="px-3 py-3 text-slate-700">{{ $payment['sale_number'] }}</td>
                                <td class="px-3 py-3 text-slate-700">{{ $payment['sale_date'] }}</td>
                                <td class="px-3 py-3 text-slate-700">{{ $payment['payment_date'] }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex rounded-full border border-sky-100 bg-sky-50 px-2.5 py-1 text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-sky-700">{{ $payment['payment_method'] }}</span>
                                </td>
                                <td class="px-3 py-3 text-right font-semibold text-emerald-700">{{ $payment['amount_paid'] }}</td>
                                <td class="px-3 py-3 align-middle">
                                    <div class="table-action-group justify-center">
                                        <button
                                            type="button"
                                            @click='openDetail(@json($payment))'
                                            class="table-icon-btn"
                                            title="Detail obat {{ $payment['sale_number'] }}"
                                            aria-label="Detail obat {{ $payment['sale_number'] }}"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M2.06 12.35a1 1 0 0 1 0-.7C3.2 8.38 6.52 5 12 5s8.8 3.38 9.94 6.65a1 1 0 0 1 0 .7C20.8 15.62 17.48 19 12 19s-8.8-3.38-9.94-6.65Z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                            <span class="sr-only">Detail obat</span>
                                        </button>

                                        <button
                                            type="button"
                                            @click="openDeleteDialog({
                                                action: @js($payment['delete_url']),
                                                title: 'Hapus pembayaran piutang ini?',
                                                description: 'Pembayaran {{ $payment['payment_number'] }} akan dihapus dan nominal bayar pada faktur {{ $payment['sale_number'] }} akan dikurangi kembali.',
                                                warning: 'Setelah dihapus, saldo piutang pelanggan akan bertambah lagi sesuai nominal pembayaran ini.',
                                                name: @js($detail['customer_name']),
                                                code: @js($payment['payment_number']),
                                                confirm_label: 'Ya, hapus pembayaran',
                                            })"
                                            class="table-icon-btn table-icon-btn--danger"
                                            title="Hapus pembayaran"
                                            aria-label="Hapus pembayaran"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M3 6h18" />
                                                <path d="M8 6V4.75A1.75 1.75 0 0 1 9.75 3h4.5A1.75 1.75 0 0 1 16 4.75V6" />
                                                <path d="M19 6l-.82 11.47A2 2 0 0 1 16.19 19H7.81a2 2 0 0 1-1.99-1.53L5 6" />
                                                <path d="M10 10.5v5" />
                                                <path d="M14 10.5v5" />
                                            </svg>
                                            <span class="sr-only">Hapus pembayaran</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-14 text-center">
                                    <div class="mx-auto max-w-md space-y-3">
                                        <div class="empty-title">Belum ada pembayaran pada periode ini</div>
                                        <p class="content-copy">Coba ubah periode untuk melihat riwayat pembayaran pelanggan ini.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div
            x-cloak
            x-show="detailModalOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/55 backdrop-blur-sm"
            @click.self="closeDetail()"
        >
            <div class="flex min-h-full items-center justify-center p-3 sm:p-4">
                <div class="panel-surface relative z-50 w-full max-w-4xl overflow-hidden p-0">
                    <div class="border-b border-sky-100 bg-gradient-to-br from-sky-50 via-white to-slate-50 px-4 py-4 sm:px-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[0.66rem] font-semibold uppercase tracking-[0.18em] text-sky-500">Detail Obat Faktur</p>
                                <h3 class="mt-1 text-sm font-semibold text-slate-950 sm:text-base" x-text="detailTarget?.sale_number"></h3>
                                <p class="mt-1 text-[0.72rem] leading-5 text-slate-600">
                                    Pembayaran
                                    <span class="font-semibold text-slate-900" x-text="detailTarget?.amount_paid"></span>
                                </p>
                            </div>

                            <button type="button" class="table-icon-btn" @click="closeDetail()" aria-label="Tutup detail obat">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                                    <path d="M6 6l12 12M18 6 6 18" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="px-4 py-4 sm:px-5">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200/80 text-[0.76rem]">
                                <thead class="bg-slate-50/90 text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                    <tr>
                                        <th class="px-3 py-3">Obat</th>
                                        <th class="px-3 py-3">Batch</th>
                                        <th class="px-3 py-3 text-center">Qty</th>
                                        <th class="px-3 py-3">Satuan</th>
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
                                                <td class="px-3 py-3 text-right font-semibold text-slate-900" x-text="item.unit_price"></td>
                                                <td class="px-3 py-3 text-right font-semibold text-emerald-700" x-text="item.line_total"></td>
                                            </tr>
                                        </template>
                                    </template>
                                    <template x-if="(detailTarget?.items?.length ?? 0) === 0">
                                        <tr>
                                            <td colspan="6" class="px-5 py-10 text-center text-[0.76rem] text-slate-500">
                                                Belum ada detail item untuk faktur ini.
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-[0.72rem] font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                                @click="closeDetail()"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-master-delete-modal />
    </div>
</x-app-layout>
