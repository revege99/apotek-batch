<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $section }}</span>
                <span class="text-slate-300">/</span>
                <span class="text-slate-600">{{ $page['label'] }}</span>
            </div>
            <a href="{{ route('keuangan.riwayat-pembayaran-hutang') }}" class="ui-action-btn ui-action-btn--soft px-4">Riwayat Hutang</a>
        </div>
    </x-slot>

    <div class="space-y-5">
        <div class="panel-surface px-5 py-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] text-slate-700">
                    <span>Total hutang</span>
                    <span class="font-semibold text-slate-900">Rp {{ number_format($stats['total_payable'], 0, ',', '.') }}</span>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] text-slate-700">
                    <span>Faktur kredit</span>
                    <span class="font-semibold text-slate-900">{{ number_format($stats['invoice_count']) }}</span>
                </div>
            </div>
        </div>

        <section class="panel-surface overflow-hidden p-0">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 px-5 py-4">
                <h3 class="section-title">Daftar hutang per supplier</h3>
                <form method="GET" action="{{ route('keuangan.pembayaran-hutang') }}" class="flex w-full max-w-xl items-center gap-2 lg:w-auto">
                    <input name="search" type="text" value="{{ $search }}" placeholder="Cari supplier atau nomor faktur" class="ui-control min-w-0 flex-1 lg:w-80">
                    <button type="submit" class="ui-action-btn ui-action-btn--soft px-3">Terapkan</button>
                    <a href="{{ route('keuangan.pembayaran-hutang') }}" class="ui-action-btn ui-action-btn--neutral px-3">Reset</a>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[760px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                    <thead class="bg-slate-50/90 text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-center">No</th>
                            <th class="px-3 py-3 text-left">Supplier</th>
                            <th class="px-3 py-3 text-center">Jumlah Faktur</th>
                            <th class="px-3 py-3 text-right">Total Hutang</th>
                            <th class="px-3 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($suppliers as $index => $row)
                            <tr>
                                <td class="px-4 py-3 text-center font-semibold text-slate-900">{{ $suppliers->firstItem() + $index }}</td>
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-slate-900">{{ $row->supplier_name ?: '-' }}</p>
                                    <p class="mt-1 text-[0.66rem] text-slate-400">Supplier dengan faktur kredit aktif</p>
                                </td>
                                <td class="px-3 py-3 text-center font-semibold text-slate-900">{{ number_format((int) $row->invoice_count) }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-amber-700">Rp {{ number_format((float) $row->total_payable, 0, ',', '.') }}</td>
                                <td class="px-3 py-3">
                                    <div class="table-action-group">
                                        <a href="{{ route('keuangan.pembayaran-hutang.show', $row->supplier_id) }}" class="table-icon-btn" title="Lihat dan lunasi hutang {{ $row->supplier_name }}">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                                <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-14 text-center">
                                    <div class="empty-title">{{ $search !== '' ? 'Hutang supplier tidak ditemukan' : 'Belum ada hutang supplier' }}</div>
                                    <p class="mt-2 content-copy">Faktur pembelian kredit yang belum lunas akan otomatis muncul di halaman ini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($suppliers->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4">{{ $suppliers->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
