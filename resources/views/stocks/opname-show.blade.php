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
                        <h2 class="page-title text-[1.05rem]">Hasil Stok Opname {{ $stockOpname->opname_number }}</h2>
                        <span class="inline-flex rounded-full border px-2.5 py-1 text-[0.64rem] font-semibold uppercase tracking-[0.14em] {{ $stockOpname->status === 'approved' ? 'border-emerald-100 bg-emerald-50 text-emerald-700' : 'border-amber-100 bg-amber-50 text-amber-700' }}">
                            {{ $stockOpname->status }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-x-5 gap-y-1 text-[0.74rem] text-slate-600">
                        <span>Tanggal {{ $stockOpname->opname_date?->translatedFormat('d M Y') ?? '-' }}</span>
                        <span>Dibuat oleh {{ $stockOpname->creator?->name ?? '-' }}</span>
                        <span>Approved oleh {{ $stockOpname->approver?->name ?? '-' }}</span>
                    </div>

                    @if (filled($stockOpname->notes))
                        <p class="text-[0.74rem] text-slate-600">{{ $stockOpname->notes }}</p>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($stockOpname->status === 'approved' && $rows->count() > 0)
                        <a href="{{ route('stok-batch.penyesuaian-stok.dokumen', $stockOpname->id) }}" class="ui-action-btn ui-action-btn--soft px-3 text-[0.74rem]">
                            Dokumen tindak lanjut
                        </a>
                    @endif
                    <a href="{{ route('stok-batch.stok-opname.draft') }}" class="ui-action-btn ui-action-btn--soft px-3 text-[0.74rem]">
                        Kembali ke draft
                    </a>
                    <a href="{{ route('stok-batch.stok-opname') }}" class="ui-action-btn ui-action-btn--soft px-3 text-[0.74rem]">
                        Kembali ke input
                    </a>
                </div>
            </div>
        </section>

        <section class="panel-surface px-4 py-3">
            <div class="flex flex-wrap items-center gap-2 text-[0.72rem]">
                <div class="rounded-full bg-slate-100 px-3 py-2 font-semibold text-slate-700">
                    {{ number_format($summary['item_count']) }} obat
                </div>
                <div class="rounded-full bg-slate-100 px-3 py-2 font-semibold text-slate-700">
                    Stok sistem {{ $summary['total_system'] }}
                </div>
                <div class="rounded-full bg-slate-100 px-3 py-2 font-semibold text-slate-700">
                    Stok fisik {{ $summary['total_physical'] }}
                </div>
                <div class="rounded-full bg-sky-50 px-3 py-2 font-semibold text-sky-700">
                    Total lebih {{ $summary['total_more'] }}
                </div>
                <div class="rounded-full bg-rose-50 px-3 py-2 font-semibold text-rose-700">
                    Total hilang {{ $summary['total_less'] }}
                </div>
                <div class="rounded-full bg-amber-50 px-3 py-2 font-semibold text-amber-700">
                    Nilai selisih {{ $summary['total_adjustment'] }}
                </div>
            </div>
        </section>

        <section class="panel-surface overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full divide-y divide-slate-200/80 text-[0.74rem]">
                    <thead class="bg-slate-50/90">
                        <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                            <th class="px-3 py-3">Kode</th>
                            <th class="px-2.5 py-3">Obat</th>
                            <th class="px-2.5 py-3 text-center">Stok Sistem</th>
                            <th class="px-2.5 py-3 text-center">Stok Fisik</th>
                            <th class="px-2.5 py-3 text-center">Hilang</th>
                            <th class="px-2.5 py-3 text-center">Lebih</th>
                            <th class="px-2.5 py-3 text-right">Nilai Selisih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($rows as $row)
                            <tr class="align-middle">
                                <td class="px-3 py-2.5 font-semibold text-slate-900">{{ $row['medicine_code'] }}</td>
                                <td class="px-2.5 py-2.5 font-semibold text-slate-900">{{ $row['medicine_name'] }}</td>
                                <td class="px-2.5 py-2.5 text-center font-semibold text-slate-900">
                                    {{ number_format($row['system_quantity'], 0, ',', '.') }}
                                </td>
                                <td class="px-2.5 py-2.5 text-center font-semibold text-slate-900">
                                    {{ number_format($row['physical_quantity'], 0, ',', '.') }}
                                </td>
                                <td class="px-2.5 py-2.5 text-center font-semibold text-rose-700">
                                    {{ number_format($row['less_quantity'], 0, ',', '.') }}
                                </td>
                                <td class="px-2.5 py-2.5 text-center font-semibold text-sky-700">
                                    {{ number_format($row['more_quantity'], 0, ',', '.') }}
                                </td>
                                <td class="px-2.5 py-2.5 text-right font-semibold {{ $row['adjustment_value'] < 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                                    {{ 'Rp '.number_format($row['adjustment_value'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-[0.78rem] text-slate-500">
                                    Belum ada item hasil stok opname pada dokumen ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
