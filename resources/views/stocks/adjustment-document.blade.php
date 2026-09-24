<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
            <span>{{ $section }}</span>
            <span class="text-slate-300">/</span>
            <span class="text-slate-600">{{ $page['label'] }}</span>
        </div>
    </x-slot>

    <div class="space-y-5">
        <section class="panel-surface px-4 py-3">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="page-title text-[1.05rem]">Penyesuaian Stok — No Opname {{ $stockOpname->opname_number }}</h2>
                        <span class="inline-flex rounded-full border border-emerald-100 bg-emerald-50 px-2.5 py-1 text-[0.64rem] font-semibold uppercase tracking-[0.14em] text-emerald-700">
                            Tersimpan
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-x-5 gap-y-1 text-[0.74rem] text-slate-600">
                        <span>Tanggal {{ $stockOpname->opname_date?->translatedFormat('d M Y') ?? '-' }}</span>
                        <span>Dibuat oleh {{ $stockOpname->creator?->name ?? '-' }}</span>
                    </div>

                    @if (filled($stockOpname->notes))
                        <p class="text-[0.74rem] text-slate-600">{{ $stockOpname->notes }}</p>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($summary['item_count'] > $summary['applied_count'])
                        <form method="POST" action="{{ route('stok-batch.penyesuaian-stok.apply', $stockOpname) }}">
                            @csrf
                            <button class="ui-action-btn ui-action-btn--soft px-3" type="submit">Terapkan ke stok</button>
                        </form>
                    @endif
                    @if ($summary['applied_count'] > 0)
                        <form method="POST" action="{{ route('stok-batch.penyesuaian-stok.restore', $stockOpname) }}">
                            @csrf
                            <button class="ui-action-btn ui-action-btn--neutral px-3" type="submit">Kembalikan stok</button>
                        </form>
                    @endif
                    <a href="{{ route('stok-batch.penyesuaian-stok') }}" class="ui-action-btn ui-action-btn--soft inline-flex items-center gap-2 px-3 text-[0.74rem]">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 18l-6-6 6-6" />
                        </svg>
                        Kembali ke daftar
                    </a>
                    <a href="{{ route('stok-batch.stok-opname.show', $stockOpname->id) }}" class="ui-action-btn ui-action-btn--soft inline-flex items-center gap-2 px-3 text-[0.74rem]">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        Lihat hasil opname
                    </a>
                </div>
            </div>
        </section>

        <section class="panel-surface px-4 py-3">
            <div class="flex flex-wrap items-center gap-2 text-[0.72rem]">
                <div class="rounded-full bg-slate-100 px-3 py-2 font-semibold text-slate-700">
                    {{ number_format($summary['item_count']) }} item selisih
                </div>
                <div class="rounded-full bg-rose-50 px-3 py-2 font-semibold text-rose-700">
                    Hilang {{ number_format($summary['loss_count']) }}
                </div>
                <div class="rounded-full bg-sky-50 px-3 py-2 font-semibold text-sky-700">
                    Lebih {{ number_format($summary['gain_count']) }}
                </div>
                <div class="rounded-full bg-emerald-50 px-3 py-2 font-semibold text-emerald-700">
                    Selesai {{ number_format($summary['applied_count']) }}
                </div>
                <div class="rounded-full bg-slate-100 px-3 py-2 font-semibold text-slate-700">
                    Draft {{ number_format($summary['draft_count']) }}
                </div>
                <div class="rounded-full bg-amber-50 px-3 py-2 font-semibold text-amber-700">
                    Belum diatur {{ number_format($summary['pending_count']) }}
                </div>
                <div class="rounded-full bg-amber-50 px-3 py-2 font-semibold text-amber-700">
                    Nilai selisih {{ $summary['total_adjustment'] }}
                </div>
            </div>
        </section>

        @if ($errors->any())
            <section class="panel-surface px-4 py-3 text-rose-700">{{ $errors->first() }}</section>
        @endif
        @include('stocks.adjustment-table')
    </div>
</x-app-layout>
