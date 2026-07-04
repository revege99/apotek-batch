<x-app-layout>
    <x-slot name="header">
        <div class="space-y-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-3">
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">
                        Dashboard Operasional
                    </div>
                    <div>
                        <h1 class="page-title">Ringkasan aktivitas apotik</h1>
                        <p class="mt-2 max-w-3xl content-copy">Pantau penjualan, pembelian, stok, piutang, hutang, dan aktivitas opname dari satu layar.</p>
                    </div>
                </div>

                <div class="inline-flex items-center rounded-full border border-slate-200 bg-white px-4 py-2 text-[0.76rem] font-medium text-slate-600 shadow-sm">
                    Update data: {{ $todayLabel }}
                </div>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                <a href="{{ $card['href'] }}" class="panel-surface stats-card transition hover:-translate-y-0.5 hover:border-emerald-200 hover:bg-white">
                    <p class="stats-card__label">{{ $card['label'] }}</p>
                    <p class="stats-card__value">{{ $card['value'] }}</p>
                    <p class="mt-2 text-[0.74rem] text-slate-500">{{ $card['meta'] }}</p>
                </a>
            @endforeach
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($inventoryCards as $card)
                <a href="{{ $card['href'] }}" class="panel-surface stats-card transition hover:-translate-y-0.5 hover:border-sky-200 hover:bg-white">
                    <p class="stats-card__label">{{ $card['label'] }}</p>
                    <p class="stats-card__value">{{ $card['value'] }}</p>
                    <p class="mt-2 text-[0.74rem] text-slate-500">{{ $card['meta'] }}</p>
                </a>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.3fr,1fr]">
            <div class="space-y-6">
                <section class="panel-surface p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="section-title-lg">Akses cepat modul</h3>
                            <p class="mt-2 content-copy">Buka modul utama yang paling sering dipakai tanpa perlu cari dari sidebar.</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 lg:grid-cols-2">
                        @foreach ($sections as $section)
                            <a href="{{ route($section['children'][0]['route']) }}" class="rounded-[1.2rem] border border-slate-200/80 bg-slate-50/80 p-4 transition hover:-translate-y-0.5 hover:border-emerald-200 hover:bg-white">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-950">{{ $section['label'] }}</p>
                                        <p class="mt-2 content-copy">{{ $section['summary'] }}</p>
                                    </div>
                                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">{{ count($section['children']) }} menu</span>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    @foreach (collect($section['children'])->take(3) as $child)
                                        <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-600">{{ $child['label'] }}</span>
                                    @endforeach
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>

                <section class="panel-surface p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="section-title-lg">Stok perlu perhatian</h3>
                            <p class="mt-2 content-copy">Obat yang sudah mendekati atau berada di bawah batas minimum.</p>
                        </div>
                        <a href="{{ route('stok-batch.stok-obat', ['stock_state' => 'low']) }}" class="ui-action-btn ui-action-btn--soft px-4">Lihat stok</a>
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200/80 text-[0.78rem]">
                            <thead class="bg-slate-50/90">
                                <tr class="text-left text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-slate-400">
                                    <th class="px-3 py-2.5">Kode</th>
                                    <th class="px-3 py-2.5">Obat</th>
                                    <th class="px-3 py-2.5 text-center">Stok</th>
                                    <th class="px-3 py-2.5 text-center">Min</th>
                                    <th class="px-3 py-2.5">Satuan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/80 bg-white">
                                @forelse ($lowStockItems as $item)
                                    <tr>
                                        <td class="px-3 py-2.5 font-medium text-slate-700">{{ $item->code }}</td>
                                        <td class="px-3 py-2.5 text-slate-900">{{ $item->name }}</td>
                                        <td class="px-3 py-2.5 text-center font-semibold text-rose-600">{{ number_format((float) $item->stock_total, 0, ',', '.') }}</td>
                                        <td class="px-3 py-2.5 text-center text-slate-600">{{ number_format((float) $item->minimum_stock, 0, ',', '.') }}</td>
                                        <td class="px-3 py-2.5 text-slate-500">{{ $item->small_unit ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-3 py-8 text-center text-[0.78rem] text-slate-500">Belum ada stok rendah yang perlu ditindaklanjuti.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="space-y-6">
                <section class="panel-surface p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="section-title">Penjualan terbaru</h3>
                            <p class="mt-2 content-copy">Pantau transaksi kasir paling baru.</p>
                        </div>
                        <a href="{{ route('penjualan.data-penjualan') }}" class="ui-action-btn ui-action-btn--soft px-4">Data penjualan</a>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($recentSales as $sale)
                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50/70 px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ $sale->sale_number }}</p>
                                        <p class="mt-1 text-[0.74rem] text-slate-500">{{ $sale->customer_name ?: 'Umum' }} • {{ $sale->sale_date?->translatedFormat('d M Y') ?? '-' }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-emerald-700">Rp {{ number_format((float) $sale->grand_total, 0, ',', '.') }}</p>
                                        <p class="mt-1 text-[0.72rem] uppercase tracking-[0.14em] text-slate-400">{{ strtoupper((string) $sale->payment_method) }}</p>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-[0.78rem] text-slate-500">Belum ada transaksi penjualan.</div>
                        @endforelse
                    </div>
                </section>

                <section class="panel-surface p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="section-title">Pembelian terbaru</h3>
                            <p class="mt-2 content-copy">Lihat faktur masuk terakhir dari supplier.</p>
                        </div>
                        <a href="{{ route('pembelian.data-pembelian') }}" class="ui-action-btn ui-action-btn--soft px-4">Data pembelian</a>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($recentPurchases as $purchase)
                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50/70 px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ $purchase->invoice_number }}</p>
                                        <p class="mt-1 text-[0.74rem] text-slate-500">{{ $purchase->supplier?->name ?: '-' }} • {{ $purchase->invoice_date?->translatedFormat('d M Y') ?? '-' }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-sky-700">Rp {{ number_format((float) $purchase->grand_total, 0, ',', '.') }}</p>
                                        <p class="mt-1 text-[0.72rem] uppercase tracking-[0.14em] text-slate-400">{{ strtoupper((string) $purchase->payment_status) }}</p>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-[0.78rem] text-slate-500">Belum ada pembelian yang tersimpan.</div>
                        @endforelse
                    </div>
                </section>

                <section class="panel-surface p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="section-title">Draft stok opname</h3>
                            <p class="mt-2 content-copy">Dokumen opname terbaru yang masih perlu ditindaklanjuti.</p>
                        </div>
                        <a href="{{ route('stok-batch.stok-opname.draft') }}" class="ui-action-btn ui-action-btn--soft px-4">Draft opname</a>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($latestOpnames as $opname)
                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50/70 px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ $opname->opname_number }}</p>
                                        <p class="mt-1 text-[0.74rem] text-slate-500">{{ $opname->opname_date?->translatedFormat('d M Y') ?? '-' }} • {{ number_format($opname->items_count) }} item</p>
                                    </div>
                                    <span class="rounded-full bg-white px-3 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-slate-600">{{ $opname->status }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-[0.78rem] text-slate-500">Belum ada dokumen stok opname.</div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
