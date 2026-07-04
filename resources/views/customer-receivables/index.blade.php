<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $section }}</span>
                <span class="text-slate-300">/</span>
                <span class="text-slate-600">{{ $page['label'] }}</span>
            </div>

            <a
                href="{{ route('keuangan.riwayat-pembayaran') }}"
                class="ui-action-btn ui-action-btn--soft px-4"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                    <path d="M3.333 5.833h13.334M3.333 10h13.334M3.333 14.167h13.334" />
                </svg>
                Riwayat Pembayaran
            </a>
        </div>
    </x-slot>

    <div class="space-y-5">
        <div class="panel-surface px-5 py-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] text-slate-700">
                    <span class="font-medium">Total piutang</span>
                    <span class="font-semibold text-slate-900">Rp {{ number_format($stats['total_receivable'], 0, ',', '.') }}</span>
                </div>

                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] text-slate-700">
                    <span class="font-medium">Total faktur kredit</span>
                    <span class="font-semibold text-slate-900">{{ number_format($stats['invoice_count']) }}</span>
                </div>
            </div>
        </div>

        <section class="panel-surface overflow-hidden p-0">
            <div class="border-b border-slate-200/80 px-5 py-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="section-title">Daftar piutang pelanggan</h3>

                    <form method="GET" action="{{ route('keuangan.piutang-pelanggan') }}" class="flex w-full max-w-2xl flex-wrap items-center gap-2 lg:w-auto lg:flex-nowrap">
                        <label class="sr-only" for="search">Cari pelanggan piutang</label>
                        <input
                            id="search"
                            name="search"
                            type="text"
                            value="{{ $search }}"
                            placeholder="Cari nama pelanggan atau no jual"
                            class="h-10 min-w-[16rem] flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 text-[0.76rem] text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100 lg:w-80 lg:flex-none"
                        >

                        <button
                            type="submit"
                            class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 text-[0.74rem] font-semibold text-slate-700 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                        >
                            Terapkan
                        </button>

                        <a
                            href="{{ route('keuangan.piutang-pelanggan') }}"
                            class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 text-[0.74rem] font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            Reset
                        </a>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[860px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                    <thead class="bg-slate-50/90">
                        <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                            <th class="px-4 py-3 text-center">No</th>
                            <th class="px-3 py-3">Nama Pelanggan</th>
                            <th class="px-3 py-3 text-center">Jumlah Nota</th>
                            <th class="px-3 py-3 text-right">Total Piutang</th>
                            <th class="px-3 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($customers as $index => $row)
                            <tr class="align-top">
                                <td class="px-4 py-3 text-center font-semibold text-slate-900">
                                    {{ $customers->firstItem() + $index }}
                                </td>
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-slate-900">{{ $row->customer_name ?: '-' }}</p>
                                    <p class="mt-1 text-[0.66rem] text-slate-400">Pelanggan kredit aktif</p>
                                </td>
                                <td class="px-3 py-3 text-center font-semibold text-slate-900">{{ number_format((int) $row->invoice_count) }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-emerald-700">Rp {{ number_format((float) $row->total_receivable, 0, ',', '.') }}</td>
                                <td class="px-3 py-3 align-middle">
                                    <div class="table-action-group">
                                        <a
                                            href="{{ route('keuangan.piutang-pelanggan.show', (int) $row->customer_id) }}"
                                            class="table-icon-btn"
                                            title="Lihat faktur kredit {{ $row->customer_name }}"
                                            aria-label="Lihat faktur kredit {{ $row->customer_name }}"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M2.06 12.35a1 1 0 0 1 0-.7C3.2 8.38 6.52 5 12 5s8.8 3.38 9.94 6.65a1 1 0 0 1 0 .7C20.8 15.62 17.48 19 12 19s-8.8-3.38-9.94-6.65Z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                            <span class="sr-only">Lihat detail piutang</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-14 text-center">
                                    <div class="mx-auto max-w-md space-y-3">
                                        <div class="empty-title">{{ $search !== '' ? 'Piutang pelanggan tidak ditemukan' : 'Belum ada piutang pelanggan' }}</div>
                                        <p class="content-copy">
                                            {{ $search !== '' ? 'Coba ubah kata kunci untuk melihat pelanggan yang masih punya piutang.' : 'Faktur penjualan kredit yang belum lunas akan otomatis muncul di halaman ini.' }}
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($customers->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4">
                    {{ $customers->links() }}
                </div>
            @endif
        </section>

    </div>
</x-app-layout>
