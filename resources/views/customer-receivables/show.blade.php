<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $section }}</span>
                <span class="text-slate-300">/</span>
                <a href="{{ route('keuangan.piutang-pelanggan') }}" class="text-slate-500 transition hover:text-emerald-700">{{ $page['label'] }}</a>
                <span class="text-slate-300">/</span>
                <span class="text-slate-600">Detail Piutang</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('keuangan.piutang-pelanggan') }}"
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
                    Print Piutang
                </a>
            </div>
        </div>
    </x-slot>

    <div
        x-data="{
            paymentModalOpen: false,
            detailModalOpen: false,
            paymentTarget: null,
            detailTarget: null,
            paymentFormAction: '',
            paymentForm: {
                payment_date: @js($todayDate),
                payment_method: 'cash',
                amount: '',
                reference_number: '',
                notes: '',
            },
            openPayment(payment) {
                this.paymentTarget = payment;
                this.paymentFormAction = payment.action ?? '';
                this.paymentForm = {
                    payment_date: @js($todayDate),
                    payment_method: 'cash',
                    amount: String(payment.outstanding_value ?? ''),
                    reference_number: '',
                    notes: '',
                };
                this.paymentModalOpen = true;

                this.$nextTick(() => {
                    this.$refs.paymentAmountInput?.focus();
                });
            },
            openDetail(sale) {
                this.detailTarget = sale;
                this.detailModalOpen = true;
            },
            closePayment() {
                this.paymentModalOpen = false;
                this.paymentTarget = null;
                this.paymentFormAction = '';
                this.paymentForm = {
                    payment_date: @js($todayDate),
                    payment_method: 'cash',
                    amount: '',
                    reference_number: '',
                    notes: '',
                };
            },
            closeDetail() {
                this.detailModalOpen = false;
                this.detailTarget = null;
            },
        }"
        @keydown.escape.window="closePayment(); closeDetail();"
        class="space-y-5"
    >
        <div class="panel-surface px-5 py-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] text-slate-700">
                    <span class="font-medium">Nama pelanggan</span>
                    <span class="font-semibold text-slate-900">{{ $detail['customer_name'] }}</span>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] text-slate-700">
                    <span class="font-medium">Jumlah nota</span>
                    <span class="font-semibold text-slate-900">{{ $detail['invoice_count'] }}</span>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] text-slate-700">
                    <span class="font-medium">Total piutang</span>
                    <span class="font-semibold text-slate-900">{{ $detail['total_receivable'] }}</span>
                </div>
            </div>
        </div>

        <section class="panel-surface overflow-hidden p-0">
            <div class="border-b border-slate-200/80 px-5 py-4">
                <h3 class="section-title">Daftar faktur kredit</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[960px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                    <thead class="bg-slate-50/90 text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                        <tr>
                            <th class="px-3 py-3">No Jual</th>
                            <th class="px-3 py-3">Tanggal</th>
                            <th class="px-3 py-3 text-right">Total Nota</th>
                            <th class="px-3 py-3 text-right">Sudah Bayar</th>
                            <th class="px-3 py-3 text-right">Sisa Piutang</th>
                            <th class="px-3 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($detail['sales'] as $sale)
                            <tr>
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-slate-900">{{ $sale['sale_number'] }}</p>
                                    <p class="mt-1 text-[0.66rem] text-slate-400">{{ $sale['payment_count'] }} pembayaran</p>
                                </td>
                                <td class="px-3 py-3 text-slate-700">
                                    <p>{{ $sale['sale_date'] }}</p>
                                    <p class="mt-1 text-[0.66rem] text-slate-400">
                                        {{ $sale['last_payment_date'] === '-' ? 'Belum ada pembayaran' : 'Bayar terakhir '.$sale['last_payment_date'] }}
                                    </p>
                                </td>
                                <td class="px-3 py-3 text-right font-semibold text-slate-900">{{ $sale['grand_total'] }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-sky-700">{{ $sale['paid_amount'] }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-amber-700">{{ $sale['outstanding_amount'] }}</td>
                                <td class="px-3 py-3 align-middle">
                                    <div class="table-action-group justify-center">
                                        <button
                                            type="button"
                                            @click='openDetail(@json($sale))'
                                            class="table-icon-btn"
                                            title="Lihat detail obat {{ $sale['sale_number'] }}"
                                            aria-label="Lihat detail obat {{ $sale['sale_number'] }}"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M2.06 12.35a1 1 0 0 1 0-.7C3.2 8.38 6.52 5 12 5s8.8 3.38 9.94 6.65a1 1 0 0 1 0 .7C20.8 15.62 17.48 19 12 19s-8.8-3.38-9.94-6.65Z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                            <span class="sr-only">Detail obat</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click='openPayment(@json($sale))'
                                            class="table-icon-btn"
                                            title="Bayar cepat {{ $sale['sale_number'] }}"
                                            aria-label="Bayar cepat {{ $sale['sale_number'] }}"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h13A2.5 2.5 0 0 1 21 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 16.5z" />
                                                <path d="M3 9h18" />
                                                <path d="M16 14h2" />
                                                <path d="M8 14h3" />
                                            </svg>
                                            <span class="sr-only">Bayar cepat</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-14 text-center">
                                    <div class="mx-auto max-w-md space-y-3">
                                        <div class="empty-title">Tidak ada piutang aktif</div>
                                        <p class="content-copy">Pelanggan ini belum memiliki faktur kredit yang masih tersisa.</p>
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
            x-show="paymentModalOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/55 backdrop-blur-sm"
            @click.self="closePayment()"
        >
            <div class="flex min-h-full items-center justify-center p-3 sm:p-4">
                <div class="panel-surface relative z-50 w-full max-w-2xl overflow-hidden p-0">
                    <div class="border-b border-emerald-100 bg-gradient-to-br from-emerald-50 via-white to-sky-50 px-4 py-4 sm:px-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[0.66rem] font-semibold uppercase tracking-[0.18em] text-emerald-500">Bayar Piutang</p>
                                <h3 class="mt-1 text-sm font-semibold text-slate-950 sm:text-base" x-text="paymentTarget?.sale_number"></h3>
                                <p class="mt-1 text-[0.72rem] leading-5 text-slate-600">
                                    Sisa piutang
                                    <span class="font-semibold text-slate-900" x-text="paymentTarget?.outstanding_amount"></span>
                                </p>
                            </div>

                            <button type="button" class="table-icon-btn" @click="closePayment()" aria-label="Tutup pembayaran">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                                    <path d="M6 6l12 12M18 6 6 18" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <form :action="paymentFormAction" method="POST" class="px-4 py-4 sm:px-5">
                        @csrf

                        <div class="grid gap-3">
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <label for="payment_date" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-600">Tanggal bayar</label>
                                    <input
                                        id="payment_date"
                                        name="payment_date"
                                        type="date"
                                        x-model="paymentForm.payment_date"
                                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
                                    >
                                </div>

                                <div>
                                    <label for="payment_method" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-600">Metode bayar</label>
                                    <select
                                        id="payment_method"
                                        name="payment_method"
                                        x-model="paymentForm.payment_method"
                                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
                                    >
                                        @foreach ($paymentMethods as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="amount" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-600">Nominal bayar</label>
                                    <input
                                        x-ref="paymentAmountInput"
                                        id="amount"
                                        name="amount"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        x-model="paymentForm.amount"
                                        :max="paymentTarget?.outstanding_value ?? ''"
                                        readonly
                                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
                                    >
                                    <p class="mt-1 text-[0.7rem] text-slate-500">Nominal otomatis mengikuti sisa piutang nota.</p>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-[0.95fr,1.05fr]">
                                <div>
                                    <label for="reference_number" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-600">No referensi</label>
                                    <input
                                        id="reference_number"
                                        name="reference_number"
                                        type="text"
                                        x-model="paymentForm.reference_number"
                                        placeholder="Opsional, no transfer / bukti"
                                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
                                    >
                                </div>

                                <div>
                                    <label for="notes" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-600">Catatan</label>
                                    <textarea
                                        id="notes"
                                        name="notes"
                                        x-model="paymentForm.notes"
                                        rows="2"
                                        placeholder="Opsional, misalnya pelunasan faktur kredit"
                                        class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-[0.72rem] font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                                @click="closePayment()"
                            >
                                Batal
                            </button>

                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-xl border border-emerald-300 bg-emerald-500 px-3.5 py-2 text-[0.72rem] font-semibold text-white shadow-sm transition hover:bg-emerald-600"
                            >
                                Simpan pembayaran
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

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
                                    Total nota
                                    <span class="font-semibold text-slate-900" x-text="detailTarget?.grand_total"></span>
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
    </div>
</x-app-layout>
