<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $section }}</span>
                <span class="text-slate-300">/</span>
                <span class="text-slate-600">{{ $page['label'] }}</span>
            </div>

            <a
                href="{{ route('keuangan.piutang-pelanggan') }}"
                class="ui-action-btn ui-action-btn--soft px-4"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                    <path d="M10 4.167v11.666M4.167 10h11.666" />
                </svg>
                Piutang Pelanggan
            </a>
        </div>
    </x-slot>

    <div class="space-y-5">
        <div class="panel-surface px-5 py-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] text-slate-700">
                    <span class="font-medium">Transaksi bayar</span>
                    <span class="font-semibold text-slate-900">{{ number_format($stats['total']) }}</span>
                </div>

                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] text-slate-700">
                    <span class="font-medium">Total pembayaran</span>
                    <span class="font-semibold text-slate-900">Rp {{ number_format($stats['total_amount'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <section class="panel-surface overflow-hidden p-0">
            <div class="border-b border-slate-200/80 px-5 py-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="section-title">Riwayat pembayaran piutang</h3>

                    <form method="GET" action="{{ route('keuangan.riwayat-pembayaran') }}" class="flex w-full max-w-4xl flex-wrap items-center gap-2 lg:w-auto lg:flex-nowrap">
                        <label class="sr-only" for="search">Cari pembayaran piutang</label>
                        <input
                            id="search"
                            name="search"
                            type="text"
                            value="{{ $search }}"
                            placeholder="Cari nama pelanggan, no bayar, no jual, referensi"
                            class="ui-control min-w-[15rem] flex-1 px-2.5 text-[0.72rem] placeholder:text-[0.68rem] lg:w-80 lg:flex-none"
                        >

                        <input
                            name="date_from"
                            type="date"
                            value="{{ $dateFrom }}"
                            class="ui-control px-2.5 text-[0.72rem] lg:w-[9.25rem]"
                        >

                        <input
                            name="date_to"
                            type="date"
                            value="{{ $dateTo }}"
                            class="ui-control px-2.5 text-[0.72rem] lg:w-[9.25rem]"
                        >

                        <button
                            type="submit"
                            class="ui-action-btn ui-action-btn--soft px-3 text-[0.72rem]"
                        >
                            Terapkan
                        </button>

                        <a
                            href="{{ route('keuangan.riwayat-pembayaran') }}"
                            class="ui-action-btn ui-action-btn--neutral px-3 text-[0.72rem]"
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
                            <th class="px-3 py-3 text-center">Jumlah Bayar</th>
                            <th class="px-3 py-3 text-right">Total Pembayaran</th>
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
                                    <p class="mt-1 text-[0.66rem] text-slate-400">Pelanggan dengan riwayat pembayaran</p>
                                </td>
                                <td class="px-3 py-3 text-center font-semibold text-slate-900">{{ number_format((int) $row->payment_count) }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-emerald-700">Rp {{ number_format((float) $row->total_amount, 0, ',', '.') }}</td>
                                <td class="px-3 py-3 align-middle">
                                    <div class="table-action-group justify-center">
                                        <button
                                            type="button"
                                            onclick="window.location.href='{{ route('keuangan.riwayat-pembayaran.show', ['customer' => (int) $row->customer_id, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}'"
                                            class="table-icon-btn"
                                            title="Lihat detail pembayaran {{ $row->customer_name }}"
                                            aria-label="Lihat detail pembayaran {{ $row->customer_name }}"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M2.06 12.35a1 1 0 0 1 0-.7C3.2 8.38 6.52 5 12 5s8.8 3.38 9.94 6.65a1 1 0 0 1 0 .7C20.8 15.62 17.48 19 12 19s-8.8-3.38-9.94-6.65Z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                            <span class="sr-only">Lihat detail riwayat pembayaran</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-14 text-center">
                                    <div class="mx-auto max-w-md space-y-3">
                                        <div class="empty-title">{{ $search !== '' || $dateFrom !== '' || $dateTo !== '' ? 'Riwayat pembayaran tidak ditemukan' : 'Belum ada pembayaran piutang' }}</div>
                                        <p class="content-copy">
                                            {{ $search !== '' || $dateFrom !== '' || $dateTo !== '' ? 'Coba ubah kata kunci atau periode untuk melihat pembayaran piutang yang dicari.' : 'Setiap pembayaran dari pelanggan kredit akan tercatat otomatis di halaman ini.' }}
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
