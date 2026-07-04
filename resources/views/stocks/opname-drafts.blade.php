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
                            <select name="status" class="ui-select-control w-[10rem] px-3 text-[0.74rem]">
                                <option value="all" @selected($status === 'all')>Semua status</option>
                                <option value="draft" @selected($status === 'draft')>Draft</option>
                                <option value="approved" @selected($status === 'approved')>Approved</option>
                            </select>

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
                            <th class="px-2.5 py-3">Status</th>
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
                                <td class="px-2.5 py-3">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[0.64rem] font-semibold uppercase tracking-[0.14em] {{ $opname['status'] === 'approved' ? 'border-emerald-100 bg-emerald-50 text-emerald-700' : 'border-amber-100 bg-amber-50 text-amber-700' }}">
                                        {{ $opname['status'] }}
                                    </span>
                                </td>
                                <td class="px-2.5 py-3 text-center font-semibold text-slate-900">{{ number_format($opname['item_count']) }}</td>
                                <td class="px-2.5 py-3 text-center font-semibold text-sky-700">{{ $opname['total_more'] }}</td>
                                <td class="px-2.5 py-3 text-center font-semibold text-rose-700">{{ $opname['total_less'] }}</td>
                                <td class="px-2.5 py-3 text-slate-700">{{ $opname['created_by'] }}</td>
                                <td class="px-2.5 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <a
                                            href="{{ route('stok-batch.stok-opname.show', $opname['id']) }}"
                                            class="ui-action-btn ui-action-btn--soft px-3 text-[0.72rem]"
                                        >
                                            Lihat hasil
                                        </a>

                                        @if ($opname['status'] === 'draft')
                                            <form method="POST" action="{{ route('stok-batch.stok-opname.approve', $opname['id']) }}" class="inline-flex">
                                                @csrf
                                                <button type="submit" class="ui-action-btn ui-action-btn--soft px-3 text-[0.72rem]">
                                                    Approve
                                                </button>
                                            </form>
                                        @endif

                                        <button
                                            type="button"
                                            @click="openDeleteDialog(@js([
                                                'action' => route('stok-batch.stok-opname.destroy', $opname['id']),
                                                'title' => 'Hapus hasil stok opname ini?',
                                                'description' => 'Dokumen '.$opname['number'].' akan dihapus dari riwayat stok opname.',
                                                'warning' => 'Hapus hanya jika hasil audit ini memang sudah tidak dipakai lagi sebagai pembanding.',
                                                'name' => 'Stok opname '.$opname['date'],
                                                'code' => $opname['number'],
                                                'confirm_label' => 'Ya, hapus hasil opname',
                                            ]))"
                                            class="ui-action-btn ui-action-btn--neutral px-3 text-[0.72rem] text-rose-700 hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-10 text-center text-[0.78rem] text-slate-500">
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
