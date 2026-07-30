<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $section }}</span><span>/</span><span class="text-slate-600">{{ $page['label'] }}</span>
            </div>
            <a href="{{ route('keuangan.pembayaran-hutang') }}" class="ui-action-btn ui-action-btn--soft px-4">Hutang Supplier</a>
        </div>
    </x-slot>

    <div class="space-y-5">
        <div class="panel-surface px-5 py-3">
            <div class="flex flex-wrap gap-2.5">
                <div class="rounded-full bg-slate-100 px-4 py-2 text-[0.78rem]">Faktur lunas <strong>{{ number_format($stats['invoice_count']) }}</strong></div>
                <div class="rounded-full bg-emerald-50 px-4 py-2 text-[0.78rem] text-emerald-800">Total dibayar <strong>Rp {{ number_format($stats['total_amount'], 0, ',', '.') }}</strong></div>
            </div>
        </div>

        <section class="panel-surface overflow-hidden p-0">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 px-5 py-4">
                <h3 class="section-title">Riwayat hutang per supplier</h3>
                <form method="GET" action="{{ route('keuangan.riwayat-pembayaran-hutang') }}" class="flex w-full max-w-4xl flex-wrap items-center gap-2 lg:w-auto lg:flex-nowrap">
                    <label class="sr-only" for="supplier-history-search">Cari riwayat hutang</label>
                    <input id="supplier-history-search" name="search" type="text" value="{{ $search }}" placeholder="Cari supplier, no faktur, no bayar, referensi" class="ui-control min-w-[15rem] flex-1 px-2.5 text-[0.72rem] placeholder:text-[0.68rem] lg:w-80 lg:flex-none">
                    <input name="date_from" type="date" value="{{ $dateFrom }}" class="ui-control px-2.5 text-[0.72rem] lg:w-[9.25rem]">
                    <input name="date_to" type="date" value="{{ $dateTo }}" class="ui-control px-2.5 text-[0.72rem] lg:w-[9.25rem]">
                    <button type="submit" class="ui-action-btn ui-action-btn--soft px-3 text-[0.72rem]">Terapkan</button>
                    <a href="{{ route('keuangan.riwayat-pembayaran-hutang') }}" class="ui-action-btn ui-action-btn--neutral px-3 text-[0.72rem]">Reset</a>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[760px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                    <thead class="bg-slate-50/90 text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-center">No</th>
                            <th class="px-3 py-3 text-left">Nama Supplier</th>
                            <th class="px-3 py-3 text-center">Jumlah Faktur</th>
                            <th class="px-3 py-3 text-right">Total Pembayaran</th>
                            <th class="px-3 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($suppliers as $index => $row)
                            <tr>
                                <td class="px-4 py-3 text-center font-semibold text-slate-900">{{ $suppliers->firstItem() + $index }}</td>
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-slate-900">{{ $row->supplier_name ?: '-' }}</p>
                                    <p class="mt-1 text-[0.66rem] text-slate-400">Supplier dengan riwayat hutang</p>
                                </td>
                                <td class="px-3 py-3 text-center font-semibold text-slate-900">{{ number_format((int) $row->invoice_count) }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-emerald-700">Rp {{ number_format((float) $row->total_amount, 0, ',', '.') }}</td>
                                <td class="px-3 py-3">
                                    <div class="table-action-group justify-center">
                                        <a
                                            href="{{ route('keuangan.riwayat-pembayaran-hutang.show', ['supplier' => $row->supplier_id, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                                            class="table-icon-btn"
                                            title="Lihat riwayat faktur {{ $row->supplier_name }}"
                                            aria-label="Lihat riwayat faktur {{ $row->supplier_name }}"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M2.06 12.35a1 1 0 0 1 0-.7C3.2 8.38 6.52 5 12 5s8.8 3.38 9.94 6.65a1 1 0 0 1 0 .7C20.8 15.62 17.48 19 12 19s-8.8-3.38-9.94-6.65Z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-14 text-center"><div class="empty-title">Riwayat hutang tidak ditemukan</div><p class="mt-2 content-copy">Faktur hutang yang telah dilunasi akan muncul di halaman ini.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($suppliers->hasPages())<div class="border-t border-slate-200/80 px-5 py-4">{{ $suppliers->links() }}</div>@endif
        </section>
    </div>
</x-app-layout>
