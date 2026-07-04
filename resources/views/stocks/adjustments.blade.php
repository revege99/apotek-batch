<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
            <span>{{ $section }}</span>
            <span class="text-slate-300">/</span>
            <span class="text-slate-600">{{ $page['label'] }}</span>
        </div>
    </x-slot>

    <div class="space-y-5">
        <section class="panel-surface overflow-visible p-0">
            <div class="border-b border-slate-200/80 px-4 py-3">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <h3 class="section-title shrink-0">Dokumen tindak lanjut stok opname</h3>

                    <form method="GET" action="{{ route('stok-batch.penyesuaian-stok') }}" class="flex min-w-0 flex-nowrap items-center justify-end gap-2 lg:flex-1">
                        <input
                            name="search"
                            type="text"
                            value="{{ $search }}"
                            placeholder="Cari nomor opname, kode obat, atau petugas"
                            class="ui-control min-w-0 w-[13rem] shrink px-3 text-[0.74rem]"
                        >

                        <select name="adjustment_type" class="ui-select-control w-[6.5rem] shrink-0 px-2 text-[0.74rem]">
                            <option value="all" @selected($adjustmentType === 'all')>Semua jenis</option>
                            <option value="gain" @selected($adjustmentType === 'gain')>Stok lebih</option>
                            <option value="loss" @selected($adjustmentType === 'loss')>Stok hilang</option>
                        </select>

                        <input name="date_from" type="date" value="{{ $dateFrom }}" class="ui-control w-[7rem] shrink-0 px-1.5 text-[0.72rem]">
                        <input name="date_to" type="date" value="{{ $dateTo }}" class="ui-control w-[7rem] shrink-0 px-1.5 text-[0.72rem]">

                        <button type="submit" class="ui-action-btn ui-action-btn--soft shrink-0 px-3 text-[0.74rem]">Tampilkan</button>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1080px] w-full divide-y divide-slate-200/80 text-[0.74rem]">
                    <thead class="bg-slate-50/90">
                        <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                            <th class="px-3 py-3">No opname</th>
                            <th class="px-2.5 py-3">Tanggal</th>
                            <th class="px-2.5 py-3">Dibuat</th>
                            <th class="px-2.5 py-3">Approved</th>
                            <th class="px-2.5 py-3 text-center">Item Selisih</th>
                            <th class="px-2.5 py-3 text-center">Hilang</th>
                            <th class="px-2.5 py-3 text-center">Lebih</th>
                            <th class="px-2.5 py-3 text-right">Nilai Selisih</th>
                            <th class="px-2.5 py-3 text-center">Status</th>
                            <th class="px-2.5 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($rows as $row)
                            @php
                                $statusClass = match ($row->status) {
                                    'applied' => 'border-emerald-100 bg-emerald-50 text-emerald-700',
                                    'draft' => 'border-sky-100 bg-sky-50 text-sky-700',
                                    default => 'border-amber-100 bg-amber-50 text-amber-700',
                                };
                                $statusLabel = match ($row->status) {
                                    'applied' => 'Sudah diproses',
                                    'draft' => 'Draft berjalan',
                                    default => 'Belum ditindaklanjuti',
                                };
                            @endphp
                            <tr>
                                <td class="px-3 py-3 font-semibold text-slate-900">{{ $row->opname_number }}</td>
                                <td class="px-2.5 py-3 text-slate-700">{{ $row->opname_date?->translatedFormat('d M Y') ?? '-' }}</td>
                                <td class="px-2.5 py-3 text-slate-700">{{ $row->creator_name }}</td>
                                <td class="px-2.5 py-3 text-slate-700">{{ $row->approver_name }}</td>
                                <td class="px-2.5 py-3 text-center font-semibold text-slate-900">{{ number_format($row->item_count) }}</td>
                                <td class="px-2.5 py-3 text-center font-semibold text-rose-700">{{ number_format($row->loss_count) }}</td>
                                <td class="px-2.5 py-3 text-center font-semibold text-sky-700">{{ number_format($row->gain_count) }}</td>
                                <td class="px-2.5 py-3 text-right font-semibold {{ $row->total_adjustment_value >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                    {{ $row->total_adjustment_value >= 0 ? 'Rp '.number_format($row->total_adjustment_value, 0, ',', '.') : '-Rp '.number_format(abs($row->total_adjustment_value), 0, ',', '.') }}
                                </td>
                                <td class="px-2.5 py-3 text-center">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[0.64rem] font-semibold uppercase tracking-[0.14em] {{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="px-2.5 py-3 text-center">
                                    <div
                                        x-data="{
                                            open: false,
                                            menuStyle: '',
                                            toggleMenu(button) {
                                                if (this.open) {
                                                    this.open = false;
                                                    return;
                                                }

                                                const rect = button.getBoundingClientRect();
                                                const menuWidth = 176;
                                                const viewportWidth = window.innerWidth;
                                                const left = Math.max(12, Math.min(rect.right - menuWidth, viewportWidth - menuWidth - 12));
                                                const top = Math.max(12, rect.top - 92);

                                                this.menuStyle = `position: fixed; top: ${top}px; left: ${left}px; width: ${menuWidth}px;`;
                                                this.open = true;
                                            },
                                            closeMenu() {
                                                this.open = false;
                                            },
                                        }"
                                        class="relative inline-flex"
                                        @keydown.escape.window="closeMenu()"
                                        @scroll.window="closeMenu()"
                                        @resize.window="closeMenu()"
                                    >
                                        <button
                                            type="button"
                                            x-ref="actionButton"
                                            class="table-icon-btn h-8 w-8 rounded-lg"
                                            @click="toggleMenu($refs.actionButton)"
                                            aria-label="Buka aksi dokumen penyesuaian stok"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                                <circle cx="5" cy="12" r="1.5" />
                                                <circle cx="12" cy="12" r="1.5" />
                                                <circle cx="19" cy="12" r="1.5" />
                                            </svg>
                                        </button>

                                        <template x-teleport="body">
                                            <div
                                                x-cloak
                                                x-show="open"
                                                x-transition.opacity.duration.120ms
                                                class="fixed inset-0 z-[80]"
                                                @click="closeMenu()"
                                            >
                                                <div
                                                    class="overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5 text-left shadow-[0_18px_38px_-22px_rgba(15,23,42,0.38)]"
                                                    :style="menuStyle"
                                                    @click.stop
                                                >
                                                    <a
                                                        href="{{ $row->opname_url }}"
                                                        class="flex w-full items-center rounded-lg px-3 py-2 text-[0.72rem] font-medium text-slate-700 transition hover:bg-emerald-50 hover:text-emerald-700"
                                                        @click="closeMenu()"
                                                    >
                                                        Lihat hasil opname
                                                    </a>
                                                    <a
                                                        href="{{ $row->document_url }}"
                                                        class="flex w-full items-center rounded-lg px-3 py-2 text-[0.72rem] font-medium text-slate-700 transition hover:bg-emerald-50 hover:text-emerald-700"
                                                        @click="closeMenu()"
                                                    >
                                                        Kelola dokumen
                                                    </a>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-5 py-14 text-center">
                                    <div class="mx-auto max-w-md space-y-3">
                                        <div class="empty-title">Belum ada dokumen selisih stok opname</div>
                                        <p class="content-copy">
                                            Hasil stok opname approved yang punya selisih akan muncul di sini sebagai satu dokumen tindak lanjut.
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
    </div>
</x-app-layout>
