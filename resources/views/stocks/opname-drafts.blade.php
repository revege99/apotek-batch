<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
            <span>{{ $section }}</span>
            <span class="text-slate-300">/</span>
            <span class="text-slate-600">{{ $page['label'] }}</span>
        </div>
    </x-slot>

    <div
        x-data="{
            deleteModalOpen: false,
            deleteFormAction: '',
            deleteTarget: null,
            openDeleteDialog(payload = {}) {
                this.deleteTarget = {
                    title: payload.title ?? 'Hapus hasil stok opname ini?',
                    description: payload.description ?? 'Dokumen stok opname ini akan dihapus dari riwayat.',
                    warning: payload.warning ?? 'Hapus hanya jika hasil audit ini memang sudah tidak dipakai lagi sebagai pembanding.',
                    confirm_label: payload.confirm_label ?? 'Ya, hapus hasil opname',
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
        }"
        @keydown.escape.window="closeDeleteDialog()"
        class="space-y-5"
    >
        <section class="panel-surface overflow-hidden p-0">
            <div class="border-b border-slate-200/80 px-4 py-3">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="section-title">Draft stok opname terbaru</h3>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <form method="GET" action="{{ route('stok-batch.stok-opname.draft') }}" class="flex flex-wrap items-center gap-2">


                            <input
                                type="date"
                                name="date_from"
                                value="{{ $dateFrom }}"
                                class="ui-control w-[9.25rem] px-3 text-[0.74rem]"
                            >

                            <input
                                type="date"
                                name="date_to"
                                value="{{ $dateTo }}"
                                class="ui-control w-[9.25rem] px-3 text-[0.74rem]"
                            >

                            <button type="submit" class="ui-action-btn ui-action-btn--soft px-3 text-[0.74rem]">
                                Tampilkan
                            </button>
                        </form>

                        <a href="{{ route('stok-batch.stok-opname') }}" class="ui-action-btn ui-action-btn--soft px-3 text-[0.74rem]">
                            Kembali ke input
                        </a>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200/80 text-[0.74rem]">
                    <thead class="bg-slate-50/90">
                        <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                            <th class="px-3 py-3">No opname</th>
                            <th class="px-2.5 py-3">Tanggal</th>
                            <th class="px-2.5 py-3 text-center">Obat dicek</th>
                            <th class="px-2.5 py-3 text-center">Lebih</th>
                            <th class="px-2.5 py-3 text-center">Hilang</th>
                            <th class="px-2.5 py-3">Dibuat oleh</th>
                            <th class="px-2.5 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($recentOpnames as $opname)
                            <tr>
                                <td class="px-3 py-3 font-semibold text-slate-900">{{ $opname['number'] }}</td>
                                <td class="px-2.5 py-3 text-slate-700">{{ $opname['date'] }}</td>

                                <td class="px-2.5 py-3 text-center font-semibold text-slate-900">{{ number_format($opname['item_count']) }}</td>
                                <td class="px-2.5 py-3 text-center font-semibold text-sky-700">{{ $opname['total_more'] }}</td>
                                <td class="px-2.5 py-3 text-center font-semibold text-rose-700">{{ $opname['total_less'] }}</td>
                                <td class="px-2.5 py-3 text-slate-700">{{ $opname['created_by'] }}</td>
                                <td class="px-2.5 py-3 text-center">
                                    <div
                                        x-data="floatingActionMenu()"
                                        @keydown.escape.window="close()"
                                        @click.window="if (open && ! $refs.trigger.contains($event.target) && ! ($refs.panel && $refs.panel.contains($event.target))) close()"
                                        class="relative flex min-h-8 items-center justify-center"
                                    >
                                        <button
                                            x-ref="trigger"
                                            type="button"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                                            title="Aksi stok opname"
                                            aria-label="Aksi {{ $opname['number'] }}"
                                            :aria-expanded="open"
                                            @click="toggleMenu()"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                <circle cx="5" cy="12" r="1.75" />
                                                <circle cx="12" cy="12" r="1.75" />
                                                <circle cx="19" cy="12" r="1.75" />
                                            </svg>
                                        </button>
                                        <template x-teleport="body">
                                            <div
                                                x-cloak
                                                x-show="open"
                                                x-ref="panel"
                                                x-transition.opacity.duration.120ms
                                                x-bind:style="menuStyles"
                                                class="fixed z-[70] w-40 overflow-hidden rounded-xl border border-slate-200 bg-white py-1.5 shadow-xl shadow-slate-200/70"
                                            >
                                        <a
                                            href="{{ route('stok-batch.stok-opname.show', $opname['id']) }}"
                                            class="flex items-center gap-2 px-3 py-2 text-[0.8rem] font-medium text-slate-700 transition hover:bg-slate-50 hover:text-emerald-700"
                                            @click="close()"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
                                            Lihat hasil
                                        </a>

                                            <a href="{{ route('stok-batch.stok-opname.edit', $opname['id']) }}"
                                                class="flex items-center gap-2 px-3 py-2 text-[0.8rem] font-medium text-slate-700 transition hover:bg-slate-50 hover:text-emerald-700"
                                                @click="close()">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" /></svg>
                                                Edit
                                            </a>


                                        <button
                                            type="button"
                                            @click="close(); openDeleteDialog(@js([
                                                'action' => route('stok-batch.stok-opname.destroy', $opname['id']),
                                                'title' => 'Hapus hasil stok opname ini?',
                                                'description' => 'Dokumen '.$opname['number'].' akan dihapus dari riwayat stok opname.',
                                                'warning' => 'Hapus hanya jika hasil audit ini memang sudah tidak dipakai lagi sebagai pembanding.',
                                                'name' => 'Stok opname '.$opname['date'],
                                                'code' => $opname['number'],
                                                'confirm_label' => 'Ya, hapus hasil opname',
                                            ]))"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-[0.8rem] font-medium text-rose-700 transition hover:bg-rose-50 hover:text-rose-800"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 14h12l1-14M10 10v6M14 10v6" /></svg>
                                            Hapus
                                        </button>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-[0.78rem] text-slate-500">
                                    Belum ada draft stok opname yang disimpan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <x-master-delete-modal />
    </div>
</x-app-layout>
