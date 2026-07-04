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
                        <h2 class="page-title text-[1.05rem]">Dokumen Tindak Lanjut {{ $stockOpname->opname_number }}</h2>
                        <span class="inline-flex rounded-full border border-emerald-100 bg-emerald-50 px-2.5 py-1 text-[0.64rem] font-semibold uppercase tracking-[0.14em] text-emerald-700">
                            approved
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
                    @if ($summary['item_count'] > 0 && $summary['applied_count'] < $summary['item_count'])
                        <form method="POST" action="{{ route('stok-batch.penyesuaian-stok.dokumen.process', $stockOpname->id) }}">
                            @csrf
                            <button type="submit" class="ui-action-btn ui-action-btn--soft inline-flex items-center gap-2 px-3 text-[0.74rem]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 6 9 17l-5-5" />
                                </svg>
                                Proses tindak lanjut
                            </button>
                        </form>
                    @elseif ($summary['item_count'] > 0)
                        <span class="inline-flex rounded-full border border-emerald-100 bg-emerald-50 px-3 py-2 text-[0.72rem] font-semibold text-emerald-700">
                            Semua item sudah diproses
                        </span>
                    @endif
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

        <section class="panel-surface overflow-visible p-0">
            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full divide-y divide-slate-200/80 text-[0.72rem]">
                    <thead class="bg-slate-50/90">
                        <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                            <th class="px-3 py-2.5">Obat</th>
                            <th class="px-2 py-2.5 text-center">Stok Sistem</th>
                            <th class="px-2 py-2.5 text-center">Stok Fisik</th>
                            <th class="px-2 py-2.5 text-center">Hilang</th>
                            <th class="px-2 py-2.5 text-center">Lebih</th>
                            <th class="px-2 py-2.5 text-right">Nilai Selisih</th>
                            <th class="px-2 py-2.5">No tindak lanjut</th>
                            <th class="px-2 py-2.5 text-center">Status</th>
                            <th class="px-2 py-2.5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($rows as $item)
                            @php
                                $difference = (float) $item->difference_quantity;
                                $followUp = $item->followUp;
                                $statusClass = match ($followUp?->status) {
                                    'applied' => 'border-emerald-100 bg-emerald-50 text-emerald-700',
                                    'draft' => 'border-sky-100 bg-sky-50 text-sky-700',
                                    default => 'border-amber-100 bg-amber-50 text-amber-700',
                                };
                                $statusLabel = match ($followUp?->status) {
                                    'applied' => 'Sudah diproses',
                                    'draft' => 'Draft berjalan',
                                    default => 'Belum ditindaklanjuti',
                                };
                            @endphp
                            <tr>
                                <td class="px-3 py-2.5">
                                    <div class="font-semibold text-slate-900">{{ $item->medicine?->name ?: '-' }}</div>
                                    <div class="mt-1 text-[0.66rem] text-slate-400">{{ $item->medicine?->code ?: '-' }}</div>
                                </td>
                                <td class="px-2 py-2.5 text-center font-semibold text-slate-900">{{ number_format((float) $item->system_quantity, 0, ',', '.') }}</td>
                                <td class="px-2 py-2.5 text-center font-semibold text-slate-900">{{ number_format((float) $item->physical_quantity, 0, ',', '.') }}</td>
                                <td class="px-2 py-2.5 text-center font-semibold text-rose-700">{{ $difference < 0 ? number_format(abs($difference), 0, ',', '.') : '-' }}</td>
                                <td class="px-2 py-2.5 text-center font-semibold text-sky-700">{{ $difference > 0 ? number_format($difference, 0, ',', '.') : '-' }}</td>
                                <td class="px-2 py-2.5 text-right font-semibold {{ (float) $item->adjustment_value >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                    {{ (float) $item->adjustment_value >= 0 ? 'Rp '.number_format((float) $item->adjustment_value, 0, ',', '.') : '-Rp '.number_format(abs((float) $item->adjustment_value), 0, ',', '.') }}
                                </td>
                                <td class="px-2 py-2.5 text-[0.68rem] text-slate-700">{{ $followUp?->adjustment_number ?: '-' }}</td>
                                <td class="px-2 py-2.5 text-center">
                                    <span class="inline-flex rounded-full border px-2 py-1 text-[0.6rem] font-semibold uppercase tracking-[0.12em] {{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="px-2 py-2.5 text-center">
                                    <a
                                        href="{{ route('stok-batch.penyesuaian-stok.follow-up', $item->id) }}"
                                        title="{{ $followUp?->status === 'applied' ? 'Lihat proses' : ($followUp ? 'Lanjutkan draft' : 'Atur item') }}"
                                        aria-label="{{ $followUp?->status === 'applied' ? 'Lihat proses' : ($followUp ? 'Lanjutkan draft' : 'Atur item') }}"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                                    >
                                        <svg class="h-4 w-4 text-slate-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <circle cx="5" cy="12" r="1.8" />
                                            <circle cx="12" cy="12" r="1.8" />
                                            <circle cx="19" cy="12" r="1.8" />
                                        </svg>
                                        <span class="sr-only">Aksi</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-12 text-center text-[0.78rem] text-slate-500">
                                    Tidak ada item selisih pada dokumen ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
