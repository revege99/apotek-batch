<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $section }}</span>
                <span class="text-slate-300">/</span>
                <span class="text-slate-600">{{ $page['label'] }}</span>
            </div>

            <a
                href="{{ route('stok-batch.penyesuaian-stok') }}"
                class="ui-action-btn ui-action-btn--soft px-4"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                    <path d="M3.333 5.833h13.334M3.333 10h13.334M3.333 14.167h13.334" />
                </svg>
                Penyesuaian Stok
            </a>
        </div>
    </x-slot>

    @php
        $initialDetailPayload = $reopenDetailKey ? ($detailPayloads[$reopenDetailKey] ?? null) : null;
    @endphp

    <div
        x-data="{
            detailModalOpen: false,
            detailItem: null,
            deleteModalOpen: false,
            deleteFormAction: '',
            deleteTarget: null,
            paymentModalOpen: false,
            paymentTarget: null,
            paymentFormAction: '',
            paymentForm: {
                payment_date: @js($todayDate),
                payment_method: 'cash',
                amount: '',
                reference_number: '',
                notes: '',
            },
            openDetail(detail) {
                this.detailItem = detail;
                this.detailModalOpen = true;
            },
            closeDetail() {
                this.detailModalOpen = false;
                this.detailItem = null;
            },
            openDeleteDialog(payload = {}) {
                this.deleteTarget = {
                    title: payload.title ?? 'Hapus pembayaran tagihan ini?',
                    description: payload.description ?? 'Pembayaran yang dihapus akan mengurangi nominal bayar pada tagihan internal terkait.',
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
            openPayment(target) {
                this.paymentTarget = target;
                this.paymentFormAction = target.action ?? '';
                this.paymentForm = {
                    payment_date: @js($todayDate),
                    payment_method: 'cash',
                    amount: String(target.outstanding_value ?? ''),
                    reference_number: '',
                    notes: '',
                };
                this.paymentModalOpen = true;

                this.$nextTick(() => {
                    this.$refs.paymentAmountInput?.focus();
                });
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
        }"
        x-init="
            if (@js($initialDetailPayload)) {
                openDetail(@js($initialDetailPayload));
            }
        "
        @keydown.escape.window="closeDetail(); closeDeleteDialog(); closePayment()"
        class="space-y-5"
    >
        <div class="grid gap-3 md:grid-cols-3">
            <div class="panel-surface px-4 py-3">
                <p class="text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">Total dokumen</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ number_format($stats['total']) }}</p>
            </div>
            <div class="panel-surface px-4 py-3">
                <p class="text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">Sudah dibayar</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">Rp {{ number_format($stats['paid_total'], 0, ',', '.') }}</p>
            </div>
            <div class="panel-surface px-4 py-3">
                <p class="text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">Sisa tagihan</p>
                <p class="mt-1 text-sm font-semibold text-amber-700">Rp {{ number_format($stats['outstanding_total'], 0, ',', '.') }}</p>
            </div>
        </div>

        <section class="panel-surface overflow-hidden p-0">
            <div class="border-b border-slate-200/80 px-5 py-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="section-title">Riwayat tagihan internal</h3>

                    <form method="GET" action="{{ route('keuangan.riwayat-tagihan-internal') }}" class="flex w-full max-w-4xl flex-wrap items-center gap-2 lg:w-auto lg:flex-nowrap">
                        <label class="sr-only" for="search">Cari tagihan internal</label>
                        <input
                            id="search"
                            name="search"
                            type="text"
                            value="{{ $search }}"
                            placeholder="Cari penanggung jawab, no tindak lanjut, atau obat"
                            class="ui-control min-w-[15rem] flex-1 px-3 text-[0.74rem] lg:w-80 lg:flex-none"
                        >

                        <select name="status" class="ui-select-control w-[9rem] px-3 text-[0.74rem]">
                            <option value="all" @selected($status === 'all')>Semua status</option>
                            <option value="unpaid" @selected($status === 'unpaid')>Belum lunas</option>
                            <option value="partial" @selected($status === 'partial')>Sebagian</option>
                            <option value="paid" @selected($status === 'paid')>Lunas</option>
                        </select>

                        <button type="submit" class="ui-action-btn ui-action-btn--soft px-3 text-[0.74rem]">
                            Terapkan
                        </button>

                        <a href="{{ route('keuangan.riwayat-tagihan-internal') }}" class="ui-action-btn ui-action-btn--neutral px-3 text-[0.74rem]">
                            Reset
                        </a>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1100px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                    <thead class="bg-slate-50/90">
                        <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                            <th class="px-4 py-3 text-center">No</th>
                            <th class="px-3 py-3">No Opname</th>
                            <th class="px-3 py-3">Penanggung Jawab</th>
                            <th class="px-3 py-3 text-center">Item</th>
                            <th class="px-3 py-3 text-right">Nilai Tagihan</th>
                            <th class="px-3 py-3 text-right">Sudah Dibayar</th>
                            <th class="px-3 py-3 text-right">Sisa</th>
                            <th class="px-3 py-3 text-center">Status</th>
                            <th class="px-3 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($rows as $index => $row)
                            @php
                                $statusClass = match ($row->status) {
                                    'paid' => 'border-emerald-100 bg-emerald-50 text-emerald-700',
                                    'partial' => 'border-sky-100 bg-sky-50 text-sky-700',
                                    default => 'border-amber-100 bg-amber-50 text-amber-700',
                                };
                                $statusLabel = match ($row->status) {
                                    'paid' => 'Lunas',
                                    'partial' => 'Sebagian',
                                    default => 'Belum Lunas',
                                };
                            @endphp
                            <tr class="align-top">
                                <td class="px-4 py-3 text-center font-semibold text-slate-900">{{ $rows->firstItem() + $index }}</td>
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-slate-900">{{ $row->opname_number ?: '-' }}</p>
                                    <p class="mt-1 text-[0.66rem] text-slate-400">{{ $row->opname_date?->translatedFormat('d M Y') ?? '-' }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-slate-900">{{ $row->employee_names ?: '-' }}</p>
                                    <p class="mt-1 text-[0.66rem] text-slate-400">{{ implode(', ', array_slice($row->adjustment_numbers, 0, 2)) ?: '-' }}{{ count($row->adjustment_numbers) > 2 ? ' +' : '' }}</p>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <p class="font-semibold text-slate-900">{{ number_format((int) $row->item_count) }}</p>
                                    <p class="mt-1 text-[0.66rem] text-slate-400">item tagihan</p>
                                </td>
                                <td class="px-3 py-3 text-right font-semibold text-slate-900">{{ $row->replacement_amount }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-emerald-700">{{ $row->paid_amount }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-amber-700">{{ $row->outstanding_amount }}</td>
                                <td class="px-3 py-3 text-center">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[0.64rem] font-semibold uppercase tracking-[0.14em] {{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 align-middle">
                                    <div class="table-action-group justify-center">
                                        <button
                                            type="button"
                                            @click="openDetail(@js($detailPayloads[$row->key] ?? null))"
                                            class="table-icon-btn"
                                            title="Lihat detail tagihan {{ $row->opname_number }}"
                                            aria-label="Lihat detail tagihan {{ $row->opname_number }}"
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
                                <td colspan="9" class="px-5 py-14 text-center">
                                    <div class="mx-auto max-w-md space-y-3">
                                        <div class="empty-title">{{ $search !== '' || $status !== 'all' ? 'Tagihan internal tidak ditemukan' : 'Belum ada tagihan internal' }}</div>
                                        <p class="content-copy">
                                            {{ $search !== '' || $status !== 'all' ? 'Coba ubah pencarian atau filter status untuk melihat tagihan internal yang dicari.' : 'Tagihan dari proses ganti uang stok opname akan otomatis muncul di halaman ini.' }}
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

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
                <div class="panel-surface relative z-50 max-h-[calc(100vh-2rem)] w-full max-w-4xl overflow-y-auto p-5 sm:max-h-[calc(100vh-3rem)] sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="inline-flex rounded-full border border-amber-100 bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">
                                Detail Tagihan Internal
                            </div>
                            <h3 class="mt-3 text-lg font-semibold text-slate-950" x-text="detailItem?.employee_name"></h3>
                            <p class="mt-1 text-xs text-slate-500">
                                <span x-text="detailItem?.opname_number"></span>
                                <span class="px-1 text-slate-300">/</span>
                                <span x-text="detailItem?.opname_date"></span>
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                x-show="(detailItem?.payment_target?.outstanding_value ?? 0) > 0.001"
                                type="button"
                                class="ui-action-btn ui-action-btn--soft px-3 text-[0.74rem]"
                                @click="openPayment(detailItem?.payment_target)"
                            >
                                Bayar semua
                            </button>

                            <button type="button" class="table-icon-btn" @click="closeDetail()" aria-label="Tutup detail">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                                    <path d="M6 6l12 12M18 6 6 18" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-4">
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-[0.74rem]">
                            <p class="text-slate-500">Nilai tagihan</p>
                            <p class="mt-1 font-semibold text-slate-900" x-text="detailItem?.replacement_amount"></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-[0.74rem]">
                            <p class="text-slate-500">Sudah dibayar</p>
                            <p class="mt-1 font-semibold text-emerald-700" x-text="detailItem?.paid_amount"></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-[0.74rem]">
                            <p class="text-slate-500">Sisa tagihan</p>
                            <p class="mt-1 font-semibold text-amber-700" x-text="detailItem?.outstanding_amount"></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-[0.74rem]">
                            <p class="text-slate-500">Status</p>
                            <div class="mt-1">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-[0.64rem] font-semibold uppercase tracking-[0.14em]" :class="detailItem?.status_class" x-text="detailItem?.status_label"></span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200/80">
                        <table class="min-w-full divide-y divide-slate-200/80 text-[0.76rem]">
                            <thead class="bg-slate-50/90 text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <tr>
                                    <th class="px-3 py-3">No Tindak Lanjut</th>
                                    <th class="px-3 py-3">Obat</th>
                                    <th class="px-3 py-3 text-right">Tagihan</th>
                                    <th class="px-3 py-3 text-right">Dibayar</th>
                                    <th class="px-3 py-3 text-right">Sisa</th>
                                    <th class="px-3 py-3 text-center">Status</th>
                                    <th class="px-3 py-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/80 bg-white">
                                <template x-for="item in detailItem?.items ?? []" :key="`${item.adjustment_number}-${item.medicine_code}`">
                                    <tr>
                                        <td class="px-3 py-3 font-semibold text-slate-900" x-text="item.adjustment_number"></td>
                                        <td class="px-3 py-3">
                                            <p class="font-semibold text-slate-900" x-text="item.medicine_name"></p>
                                            <p class="mt-1 text-[0.66rem] text-slate-400" x-text="item.medicine_code"></p>
                                        </td>
                                        <td class="px-3 py-3 text-right font-semibold text-slate-900" x-text="item.replacement_amount"></td>
                                        <td class="px-3 py-3 text-right font-semibold text-emerald-700" x-text="item.paid_amount"></td>
                                        <td class="px-3 py-3 text-right font-semibold text-amber-700" x-text="item.outstanding_amount"></td>
                                        <td class="px-3 py-3 text-center">
                                            <span class="inline-flex rounded-full border px-2.5 py-1 text-[0.64rem] font-semibold uppercase tracking-[0.14em]" :class="item.status_class" x-text="item.status_label"></span>
                                        </td>
                                        <td class="px-3 py-3 text-center">
                                            <button
                                                x-show="(item.outstanding_value ?? 0) > 0.001"
                                                type="button"
                                                class="table-icon-btn"
                                                @click="openPayment(item.payment_target)"
                                                title="Bayar item"
                                                aria-label="Bayar item"
                                            >
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M12 5v14M5 12h14" />
                                                </svg>
                                                <span class="sr-only">Bayar item</span>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="(detailItem?.items ?? []).length === 0">
                                    <td colspan="7" class="px-5 py-10 text-center text-[0.78rem] text-slate-500">
                                        Belum ada item tagihan pada dokumen ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200/80">
                        <table class="min-w-full divide-y divide-slate-200/80 text-[0.76rem]">
                            <thead class="bg-slate-50/90 text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <tr>
                                    <th class="px-3 py-3">No Bayar</th>
                                    <th class="px-3 py-3">Tanggal</th>
                                    <th class="px-3 py-3">No Tindak Lanjut</th>
                                    <th class="px-3 py-3">Obat</th>
                                    <th class="px-3 py-3">Metode</th>
                                    <th class="px-3 py-3 text-right">Jumlah</th>
                                    <th class="px-3 py-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/80 bg-white">
                                <template x-for="payment in detailItem?.payments ?? []" :key="payment.id">
                                    <tr>
                                        <td class="px-3 py-3 font-semibold text-slate-900" x-text="payment.payment_number"></td>
                                        <td class="px-3 py-3 text-slate-700" x-text="payment.payment_date"></td>
                                        <td class="px-3 py-3 text-slate-700" x-text="payment.adjustment_number"></td>
                                        <td class="px-3 py-3 text-slate-700" x-text="payment.medicine_name"></td>
                                        <td class="px-3 py-3 text-slate-700" x-text="payment.payment_method"></td>
                                        <td class="px-3 py-3 text-right font-semibold text-emerald-700" x-text="payment.amount_paid"></td>
                                        <td class="px-3 py-3 text-center">
                                            <button
                                                type="button"
                                                @click="openDeleteDialog({
                                                    action: payment.delete_url,
                                                    title: 'Hapus pembayaran tagihan internal ini?',
                                                    description: `Pembayaran ${payment.payment_number} akan dihapus dan nominal bayar pada dokumen ${detailItem?.opname_number ?? ''} akan dikurangi kembali.`,
                                                    warning: 'Setelah dihapus, sisa tagihan internal akan bertambah lagi sesuai nominal pembayaran ini.',
                                                    name: detailItem?.employee_name ?? payment.payment_number,
                                                    code: payment.payment_number,
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
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="(detailItem?.payments ?? []).length === 0">
                                    <td colspan="7" class="px-5 py-10 text-center text-[0.78rem] text-slate-500">
                                        Belum ada pembayaran untuk tagihan internal ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <x-master-delete-modal />

        <div
            x-cloak
            x-show="paymentModalOpen"
            x-transition.opacity
            class="fixed inset-0 z-40 overflow-y-auto bg-slate-950/50 backdrop-blur-sm"
            @click.self="closePayment()"
        >
            <div class="flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6">
                <div class="panel-surface relative z-50 w-full max-w-xl p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="inline-flex rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                                Catat Pembayaran
                            </div>
                            <h3 class="mt-3 text-lg font-semibold text-slate-950" x-text="paymentTarget?.employee_name"></h3>
                            <p class="mt-1 text-xs text-slate-500">
                                <span x-text="paymentTarget?.adjustment_number"></span>
                                <span class="px-1 text-slate-300">/</span>
                                <span x-text="paymentTarget?.medicine_name"></span>
                            </p>
                        </div>

                        <button type="button" class="table-icon-btn" @click="closePayment()" aria-label="Tutup pembayaran">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                                <path d="M6 6l12 12M18 6 6 18" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-4 rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3 text-[0.74rem] font-medium text-amber-700">
                        Sisa tagihan <span class="font-semibold" x-text="paymentTarget?.outstanding_label"></span>
                    </div>

                    <form method="POST" :action="paymentFormAction" class="mt-5 space-y-4">
                        @csrf

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-[0.74rem] font-semibold text-slate-700" for="payment_date">Tanggal bayar</label>
                                <input id="payment_date" name="payment_date" type="date" x-model="paymentForm.payment_date" class="ui-control px-3 text-[0.74rem]">
                            </div>

                            <div class="space-y-2">
                                <label class="text-[0.74rem] font-semibold text-slate-700" for="payment_method">Metode bayar</label>
                                <select id="payment_method" name="payment_method" x-model="paymentForm.payment_method" class="ui-select-control px-3 text-[0.74rem]">
                                    <option value="cash">Cash</option>
                                    <option value="transfer">Transfer</option>
                                    <option value="qris">QRIS</option>
                                    <option value="debit">Debit</option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[0.74rem] font-semibold text-slate-700" for="amount">Nominal bayar</label>
                            <input id="amount" name="amount" type="number" min="0.01" step="0.01" x-model="paymentForm.amount" x-ref="paymentAmountInput" class="ui-control px-3 text-[0.74rem]">
                        </div>

                        <div class="space-y-2">
                            <label class="text-[0.74rem] font-semibold text-slate-700" for="reference_number">No referensi</label>
                            <input id="reference_number" name="reference_number" type="text" x-model="paymentForm.reference_number" class="ui-control px-3 text-[0.74rem]" placeholder="Opsional">
                        </div>

                        <div class="space-y-2">
                            <label class="text-[0.74rem] font-semibold text-slate-700" for="notes">Catatan</label>
                            <input id="notes" name="notes" type="text" x-model="paymentForm.notes" class="ui-control px-3 text-[0.74rem]" placeholder="Catatan pembayaran">
                        </div>

                        <div class="flex justify-end gap-2 border-t border-slate-200/80 pt-4">
                            <button type="button" class="ui-action-btn ui-action-btn--neutral px-3 text-[0.74rem]" @click="closePayment()">Batal</button>
                            <button type="submit" class="ui-action-btn ui-action-btn--soft px-4 text-[0.74rem]">Simpan pembayaran</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
