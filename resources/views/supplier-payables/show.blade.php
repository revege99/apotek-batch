<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $section }}</span><span>/</span>
                <a href="{{ route('keuangan.pembayaran-hutang') }}" class="hover:text-emerald-700">{{ $page['label'] }}</a>
                <span>/</span><span class="text-slate-600">Detail Hutang</span>
            </div>
            <a href="{{ route('keuangan.pembayaran-hutang') }}" class="ui-action-btn ui-action-btn--neutral px-4">Kembali</a>
        </div>
    </x-slot>

    <div
        x-data="{
            paymentModalOpen: false,
            detailModalOpen: false,
            target: null,
            detailTarget: null,
            formAction: '',
            openPayment(invoice) {
                this.target = invoice;
                this.formAction = invoice.action;
                this.paymentModalOpen = true;
            },
            closePayment() { this.paymentModalOpen = false; this.target = null; this.formAction = ''; },
            openDetail(invoice) { this.detailTarget = invoice; this.detailModalOpen = true; },
            closeDetail() { this.detailModalOpen = false; this.detailTarget = null; },
        }"
        @keydown.escape.window="closePayment(); closeDetail();"
        class="space-y-5"
    >
        <div class="panel-surface px-5 py-3">
            <div class="flex flex-wrap gap-2.5">
                <div class="rounded-full bg-slate-100 px-4 py-2 text-[0.78rem]">Supplier <strong>{{ $detail['supplier_name'] }}</strong></div>
                <div class="rounded-full bg-slate-100 px-4 py-2 text-[0.78rem]">Jumlah faktur <strong>{{ $detail['invoice_count'] }}</strong></div>
                <div class="rounded-full bg-amber-50 px-4 py-2 text-[0.78rem] text-amber-800">Total hutang <strong>{{ $detail['total_payable'] }}</strong></div>
            </div>
        </div>

        <section class="panel-surface overflow-hidden p-0">
            <div class="border-b border-slate-200/80 px-5 py-4"><h3 class="section-title">Faktur pembelian kredit</h3></div>
            <div class="overflow-x-auto">
                <table class="min-w-[900px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                    <thead class="bg-slate-50/90 text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                        <tr>
                            <th class="px-3 py-3 text-left">Nomor Faktur</th>
                            <th class="px-3 py-3 text-left">Tanggal</th>
                            <th class="px-3 py-3 text-left">Jatuh Tempo</th>
                            <th class="px-3 py-3 text-right">Total</th>
                            <th class="px-3 py-3 text-right">Sudah Bayar</th>
                            <th class="px-3 py-3 text-right">Sisa Hutang</th>
                            <th class="px-3 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($detail['invoices'] as $invoice)
                            <tr>
                                <td class="px-3 py-3 font-semibold text-slate-900">{{ $invoice['invoice_number'] }}</td>
                                <td class="px-3 py-3 text-slate-700">{{ $invoice['invoice_date'] }}</td>
                                <td class="px-3 py-3 text-slate-700">{{ $invoice['due_date'] }}</td>
                                <td class="px-3 py-3 text-right font-semibold">{{ $invoice['grand_total'] }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-sky-700">{{ $invoice['paid_amount'] }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-amber-700">{{ $invoice['outstanding_amount'] }}</td>
                                <td class="px-3 py-3">
                                    <div class="table-action-group justify-center">
                                        <button type="button" @click='openDetail(@json($invoice))' class="table-icon-btn" title="Lihat detail barang" aria-label="Lihat detail barang faktur {{ $invoice['invoice_number'] }}">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M2.06 12.35a1 1 0 0 1 0-.7C3.2 8.38 6.52 5 12 5s8.8 3.38 9.94 6.65a1 1 0 0 1 0 .7C20.8 15.62 17.48 19 12 19s-8.8-3.38-9.94-6.65Z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                        </button>
                                        <button type="button" @click='openPayment(@json($invoice))' class="table-icon-btn" title="Lunasi hutang" aria-label="Lunasi hutang faktur {{ $invoice['invoice_number'] }}">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="5.5" width="18" height="13" rx="2"/>
                                                <path d="M3 9.5h18"/>
                                                <path d="M7 14h3"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-14 text-center"><div class="empty-title">Semua hutang supplier sudah lunas</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div x-cloak x-show="paymentModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/55 backdrop-blur-sm" @click.self="closePayment()">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="panel-surface w-full max-w-xl p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-600">Pelunasan Hutang</p>
                            <h3 class="mt-2 font-semibold text-slate-950" x-text="target?.invoice_number"></h3>
                            <p class="mt-1 text-sm text-slate-600">Sisa hutang <strong x-text="target?.outstanding_amount"></strong></p>
                        </div>
                        <button type="button" class="table-icon-btn" @click="closePayment()">×</button>
                    </div>
                    <form :action="formAction" method="POST" class="mt-5 space-y-4">
                        @csrf
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold">Tanggal bayar</label>
                                <input name="payment_date" type="date" value="{{ $todayDate }}" class="ui-control mt-1.5">
                            </div>
                            <div>
                                <label class="text-xs font-semibold">Metode bayar</label>
                                <select name="payment_method" class="ui-select-control mt-1.5">
                                    @foreach ($paymentMethods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-semibold">Nominal pelunasan</label>
                                <input name="amount" type="number" step="0.01" readonly :value="target?.outstanding_value ?? ''" class="ui-control mt-1.5">
                            </div>
                            <div>
                                <label class="text-xs font-semibold">No referensi</label>
                                <input name="reference_number" type="text" class="ui-control mt-1.5" placeholder="Opsional">
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold">Catatan</label>
                            <textarea name="notes" rows="2" class="ui-control mt-1.5 h-auto py-2" placeholder="Opsional"></textarea>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="ui-action-btn ui-action-btn--neutral" @click="closePayment()">Batal</button>
                            <button type="submit" class="ui-action-btn border-emerald-300 bg-emerald-500 text-white hover:bg-emerald-600">Simpan pelunasan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div x-cloak x-show="detailModalOpen" x-transition.opacity class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/55 backdrop-blur-sm" @click.self="closeDetail()">
            <div class="flex min-h-full items-center justify-center p-3 sm:p-4">
                <div class="panel-surface relative z-50 w-full max-w-4xl overflow-hidden p-0">
                    <div class="flex items-start justify-between gap-4 border-b border-sky-100 bg-gradient-to-br from-sky-50 via-white to-slate-50 px-4 py-4 sm:px-5">
                        <div>
                            <p class="text-[0.66rem] font-semibold uppercase tracking-[0.18em] text-sky-500">Detail Obat Faktur Pembelian</p>
                            <h3 class="mt-1 text-sm font-semibold text-slate-950 sm:text-base" x-text="detailTarget?.invoice_number"></h3>
                            <p class="mt-1 text-[0.72rem] leading-5 text-slate-600">
                                Total faktur
                                <span class="font-semibold text-slate-900" x-text="detailTarget?.grand_total"></span>
                            </p>
                        </div>
                        <button type="button" class="table-icon-btn" @click="closeDetail()" aria-label="Tutup detail obat">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                                <path d="M6 6l12 12M18 6 6 18"/>
                            </svg>
                        </button>
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
                                <template x-for="(item, index) in (detailTarget?.items ?? [])" :key="index">
                                    <tr>
                                        <td class="px-3 py-3 font-semibold text-slate-900" x-text="item.medicine_name"></td>
                                        <td class="px-3 py-3 text-slate-700" x-text="item.batch_number"></td>
                                        <td class="px-3 py-3 text-center font-semibold text-slate-900" x-text="item.quantity"></td>
                                        <td class="px-3 py-3 text-slate-700" x-text="item.unit"></td>
                                        <td class="px-3 py-3 text-right font-semibold text-slate-900" x-text="item.unit_price"></td>
                                        <td class="px-3 py-3 text-right font-semibold text-emerald-700" x-text="item.line_total"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button type="button" class="ui-action-btn ui-action-btn--neutral px-3.5" @click="closeDetail()">Tutup</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
