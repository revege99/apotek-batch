<x-app-layout>
    <x-slot name="header">
        <div class="space-y-4">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                    <span>{{ $section }}</span>
                    <span class="text-slate-300">/</span>
                    <span class="text-slate-600">{{ $page['label'] }}</span>
                </div>
                <span class="shrink-0" x-data>
                    <button type="button" class="ui-action-btn ui-action-btn--soft px-4" @click="$dispatch('open-create-location-modal')">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><path d="M10 4.167v11.666M4.167 10h11.666" /></svg>
                        Tambah Lokasi
                    </button>
                </span>
            </div>
        </div>
    </x-slot>

    <div x-data="{ createModalOpen: @js($errors->any() && old('_modal') === 'create'), editModalOpen: @js($editingLocation !== null), closeEdit(){ this.editModalOpen = false; window.location = @js(route('master-data.lokasi-obat')); } }" @open-create-location-modal.window="createModalOpen = true" @keydown.escape.window="createModalOpen = false; if (editModalOpen) closeEdit()" class="space-y-5">
        <section x-data="masterDeleteDialog()" @keydown.escape.window="closeDeleteDialog()" class="panel-surface overflow-visible p-0">
            <div class="border-b border-slate-200/80 px-6 py-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] font-medium text-slate-700">{{ number_format($stats['total']) }} lokasi</span>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] font-medium text-slate-700">{{ number_format($stats['active']) }} aktif</span>
                    </div>
                    <form method="GET" action="{{ route('master-data.lokasi-obat') }}" class="flex flex-wrap items-center justify-end gap-2">
                        <div class="w-[200px] shrink-0"><label for="search" class="sr-only">Cari lokasi</label><input id="search" name="search" type="text" value="{{ $search }}" placeholder="Cari lokasi" class="ui-control" @input.debounce.350ms="$el.form?.requestSubmit()"></div>
                        <div class="w-[145px] shrink-0"><label for="status" class="sr-only">Status</label><select id="status" name="status" class="ui-select-control"><option value="all" @selected($status === 'all')>Semua status</option><option value="active" @selected($status === 'active')>Aktif</option><option value="inactive" @selected($status === 'inactive')>Nonaktif</option></select></div>
                        <button type="submit" class="ui-action-btn ui-action-btn--soft shrink-0">Terapkan</button>
                    </form>
                </div>
            </div>

            <div class="overflow-visible">
                <table class="master-table min-w-full divide-y divide-slate-200/80 text-[0.8rem]">
                    <thead><tr class="text-left text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-slate-400"><th class="px-4 py-2.5">Kode</th><th class="px-4 py-2.5">Nama Lokasi</th><th class="px-4 py-2.5">Deskripsi</th><th class="px-4 py-2.5">Status</th><th class="table-action-head">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($items as $item)
                            <tr class="align-middle">
                                <td class="px-4 py-2.5"><div class="flex min-h-8 items-center font-semibold text-slate-900">{{ $item->code }}</div></td>
                                <td class="px-4 py-2.5"><div class="flex min-h-8 items-center font-semibold text-slate-900">{{ $item->name }}</div></td>
                                <td class="px-4 py-2.5"><div class="flex min-h-8 items-center"><div class="max-w-xl content-copy">{{ $item->description ?: 'Belum ada deskripsi.' }}</div></div></td>
                                <td class="px-4 py-2.5"><div class="flex min-h-8 items-center"><span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $item->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $item->is_active ? 'Aktif' : 'Nonaktif' }}</span></div></td>
                                <td class="px-4 py-2.5 text-center align-middle">
                                    <div x-data="floatingActionMenu()" @keydown.escape.window="close()" @click.window="if (open && ! $refs.trigger.contains($event.target) && ! ($refs.panel && $refs.panel.contains($event.target))) close()" class="relative flex min-h-8 items-center justify-center">
                                        <button x-ref="trigger" type="button" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700" title="Aksi lokasi" aria-label="Aksi {{ $item->name }}" @click="toggleMenu()">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.75" /><circle cx="12" cy="12" r="1.75" /><circle cx="19" cy="12" r="1.75" /></svg>
                                            <span class="sr-only">Aksi</span>
                                        </button>
                                        <template x-teleport="body">
                                            <div x-cloak x-show="open" x-ref="panel" x-transition.opacity.duration.120ms x-bind:style="menuStyles" class="fixed z-[70] w-40 overflow-hidden rounded-xl border border-slate-200 bg-white py-1.5 shadow-xl shadow-slate-200/70">
                                                <a href="{{ route('master-data.lokasi-obat', ['edit' => $item->id]) }}" class="flex items-center gap-2 px-3 py-2 text-[0.8rem] font-medium text-slate-700 transition hover:bg-slate-50 hover:text-emerald-700" @click="close()">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" /></svg>
                                                    <span>Ubah</span>
                                                </a>
                                                <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-[0.8rem] font-medium text-rose-700 transition hover:bg-rose-50 hover:text-rose-800" @click="close(); openDeleteDialog(@js(['action' => route('master-data.lokasi-obat.destroy', $item), 'title' => 'Hapus data lokasi ini?', 'description' => 'Lokasi '.$item->name.' akan dihapus dari master data penyimpanan.', 'warning' => 'Pastikan lokasi ini sudah tidak dipakai pada stok aktif, batch, atau penerimaan barang.', 'name' => $item->name, 'code' => $item->code, 'confirm_label' => 'Ya, hapus lokasi']))">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /><path d="M8 6V4.75A1.75 1.75 0 0 1 9.75 3h4.5A1.75 1.75 0 0 1 16 4.75V6" /><path d="M19 6l-.82 11.47A2 2 0 0 1 16.19 19H7.81a2 2 0 0 1-1.99-1.53L5 6" /><path d="M10 10.5v5" /><path d="M14 10.5v5" /></svg>
                                                    <span>Hapus</span>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-14 text-center"><div class="mx-auto max-w-md space-y-3"><div class="empty-title">Belum ada data lokasi</div><p class="content-copy">Tambah lokasi pertama seperti gudang apotik, ruang display, atau lemari kasir.</p><button type="button" @click="createModalOpen = true" class="ui-action-btn ui-action-btn--soft">Input lokasi pertama</button></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($items->hasPages())<div class="border-t border-slate-200/80 px-6 py-4">{{ $items->links() }}</div>@endif
            @include('components.master-delete-modal')
        </section>

        <div x-cloak x-show="createModalOpen" x-transition.opacity class="fixed inset-0 z-40 overflow-y-auto bg-slate-950/50 backdrop-blur-sm" @click.self="createModalOpen = false">
            <div class="flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6">
                <div class="panel-surface relative z-50 max-h-[calc(100vh-2rem)] w-full max-w-3xl overflow-y-auto p-6 sm:max-h-[calc(100vh-3rem)] sm:p-7">
                    <div class="flex items-start justify-between gap-4"><div class="inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Tambah Lokasi</div><button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-900" @click="createModalOpen = false"><span class="sr-only">Tutup modal</span><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18" /></svg></button></div>
                    @if ($errors->any() && old('_modal') === 'create')
                        <div class="mt-5 rounded-[1.35rem] border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">Periksa kembali input data lokasi.</div>
                    @endif
                    <form method="POST" action="{{ route('master-data.lokasi-obat.store') }}" class="mt-6 space-y-4">
                        @csrf
                        <input type="hidden" name="_modal" value="create">
                        <div class="space-y-2"><label class="text-sm font-semibold text-slate-800" for="create_name">Nama lokasi</label><input id="create_name" name="name" type="text" value="{{ old('name') }}" placeholder="Contoh: Gudang Apotik" class="ui-control"></div>
                        <div class="space-y-2"><label class="text-sm font-semibold text-slate-800" for="create_description">Deskripsi</label><textarea id="create_description" name="description" rows="3" class="w-full rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-900 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100">{{ old('description') }}</textarea></div>
                        <label class="flex items-start gap-3 rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 px-4 py-4"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-200"><span><span class="block text-sm font-semibold text-slate-900">Lokasi aktif</span><span class="mt-1 block content-copy">Jika aktif, lokasi siap dipakai pada proses stok dan pembelian.</span></span></label>
                        <div class="flex flex-col gap-3 border-t border-slate-200/80 pt-4 sm:flex-row sm:items-center sm:justify-between"><button type="button" class="ui-action-btn ui-action-btn--neutral" @click="createModalOpen = false">Batal</button><button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-emerald-300 bg-emerald-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-600">Simpan lokasi</button></div>
                    </form>
                </div>
            </div>
        </div>

        @if ($editingLocation)
            <div x-cloak x-show="editModalOpen" x-transition.opacity class="fixed inset-0 z-40 overflow-y-auto bg-slate-950/50 backdrop-blur-sm" @click.self="closeEdit()">
                <div class="flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6">
                <div class="panel-surface relative z-50 max-h-[calc(100vh-2rem)] w-full max-w-3xl overflow-y-auto p-6 sm:max-h-[calc(100vh-3rem)] sm:p-7">
                    <div class="flex items-start justify-between gap-4"><div class="inline-flex items-center gap-2 rounded-full border border-sky-100 bg-sky-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Ubah Lokasi</div><button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-900" @click="closeEdit()"><span class="sr-only">Tutup modal</span><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18" /></svg></button></div>
                    @if ($errors->any() && old('_modal') === 'edit')
                        <div class="mt-5 rounded-[1.35rem] border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">Periksa kembali input data lokasi.</div>
                    @endif
                    <form method="POST" action="{{ route('master-data.lokasi-obat.update', $editingLocation) }}" class="mt-6 space-y-4">
                            @csrf @method('PATCH')
                            <input type="hidden" name="_modal" value="edit"><input type="hidden" name="_edit_id" value="{{ $editingLocation->id }}">
                            <div class="space-y-2"><label class="text-sm font-semibold text-slate-800" for="edit_name">Nama lokasi</label><input id="edit_name" name="name" type="text" value="{{ old('name', $editingLocation->name) }}" class="ui-control ui-control--sky"></div>
                            <div class="space-y-2"><label class="text-sm font-semibold text-slate-800" for="edit_description">Deskripsi</label><textarea id="edit_description" name="description" rows="3" class="w-full rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-900 shadow-sm transition focus:border-sky-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-sky-100">{{ old('description', $editingLocation->description) }}</textarea></div>
                            <label class="flex items-start gap-3 rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 px-4 py-4"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingLocation->is_active)) class="mt-1 h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-200"><span><span class="block text-sm font-semibold text-slate-900">Lokasi aktif</span><span class="mt-1 block content-copy">Jika aktif, lokasi siap dipakai pada proses stok dan pembelian.</span></span></label>
                            <div class="flex flex-col gap-3 border-t border-slate-200/80 pt-4 sm:flex-row sm:items-center sm:justify-between"><button type="button" class="ui-action-btn ui-action-btn--neutral" @click="closeEdit()">Batal</button><button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-sky-300 bg-sky-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-sky-500/20 transition hover:bg-sky-600">Simpan perubahan</button></div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
