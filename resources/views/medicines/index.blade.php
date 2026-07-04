<x-app-layout>
    <x-slot name="header">
        <div class="space-y-4">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                    <span>{{ $section }}</span>
                    <span class="text-slate-300">/</span>
                    <span class="text-slate-600">{{ $page['label'] }}</span>
                </div>

                <span x-data class="shrink-0">
                    <button
                        type="button"
                        class="ui-action-btn ui-action-btn--soft px-4"
                        @click="$dispatch('open-create-medicine-modal')"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                            <path d="M10 4.167v11.666M4.167 10h11.666" />
                        </svg>
                        Tambah Obat
                    </button>
                </span>
            </div>
        </div>
    </x-slot>

    <div
        x-data="{
            createModalOpen: @js($errors->any() && old('_modal') === 'create'),
            editModalOpen: @js($editingMedicine !== null && ! ($errors->any() && old('_modal') === 'create')),
            detailModalOpen: false,
            detailMedicine: null,
            openDetail(medicine) {
                this.detailMedicine = medicine;
                this.detailModalOpen = true;
            },
            closeEdit() {
                this.editModalOpen = false;
                window.location = @js(route('master-data.data-obat', request()->except('edit')));
            },
            closeDetail() {
                this.detailModalOpen = false;
                this.detailMedicine = null;
            },
        }"
        @open-create-medicine-modal.window="createModalOpen = true"
        @keydown.escape.window="createModalOpen = false; if (editModalOpen) closeEdit(); closeDetail()"
        class="space-y-5"
    >
        <section x-data="masterDeleteDialog()" @keydown.escape.window="closeDeleteDialog()" class="panel-surface overflow-visible p-0">
            <div class="border-b border-slate-200/80 px-6 py-5">
                <div class="flex items-center justify-between gap-4 overflow-x-auto">
                    <div class="flex flex-nowrap items-center gap-2.5">
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] font-medium text-slate-700">
                            {{ number_format($stats['total']) }} obat
                        </span>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] font-medium text-slate-700">
                            {{ number_format($stats['active']) }} aktif
                        </span>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] font-medium text-slate-700">
                            {{ number_format($stats['principal_count']) }} industri
                        </span>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-4 py-2 text-[0.78rem] font-medium text-slate-700">
                            {{ number_format($stats['composition_count']) }} komposisi
                        </span>
                    </div>

                    <form method="GET" action="{{ route('master-data.data-obat') }}" class="flex flex-nowrap items-center gap-2">
                        <div class="w-[220px] shrink-0">
                            <label for="search" class="sr-only">Cari obat</label>
                            <input
                                id="search"
                                name="search"
                                type="text"
                                value="{{ $search }}"
                                placeholder="Cari obat"
                                class="ui-control"
                                @input.debounce.350ms="$el.form?.requestSubmit()"
                            >
                        </div>

                        <div class="w-[145px] shrink-0">
                            <label for="status" class="sr-only">Status</label>
                            <select
                                id="status"
                                name="status"
                                class="ui-select-control"
                            >
                                <option value="all" @selected($status === 'all')>Semua status</option>
                                <option value="active" @selected($status === 'active')>Aktif</option>
                                <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
                            </select>
                        </div>

                        <button
                            type="submit"
                            class="ui-action-btn ui-action-btn--soft shrink-0"
                        >
                            Terapkan
                        </button>
                    </form>
                </div>
            </div>

            <div class="overflow-visible">
                <table class="master-table min-w-full divide-y divide-slate-200/80 text-[0.8rem]">
                    <thead class="bg-slate-50/95">
                        <tr class="text-left text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-slate-400">
                            <th class="sticky z-10 bg-slate-50/95 px-4 py-2.5 shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur" style="top: -20px;">Kode</th>
                            <th class="sticky z-10 bg-slate-50/95 px-4 py-2.5 shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur" style="top: -20px;">Nama Obat</th>
                            <th class="sticky z-10 bg-slate-50/95 px-4 py-2.5 shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur" style="top: -20px;">Kandungan</th>
                            <th class="sticky z-10 bg-slate-50/95 px-4 py-2.5 shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur" style="top: -20px;">Satuan</th>
                            <th class="sticky z-10 bg-slate-50/95 px-4 py-2.5 shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur" style="top: -20px;">Isi</th>
                            <th class="sticky z-10 bg-slate-50/95 px-4 py-2.5 shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur" style="top: -20px;">Harga Beli</th>
                            <th class="sticky z-10 bg-slate-50/95 px-4 py-2.5 shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur" style="top: -20px;">Min Stok</th>
                            <th class="sticky z-10 bg-slate-50/95 px-4 py-2.5 text-center shadow-[0_1px_0_rgba(226,232,240,0.9)] backdrop-blur" style="top: -20px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($medicines as $medicine)
                            @php
                                $detailPayload = [
                                    'code' => $medicine->code,
                                    'name' => $medicine->name,
                                    'principal_name' => $medicine->principal?->name ?: '-',
                                    'medicine_type' => $medicine->medicine_type ?: '-',
                                    'category_name' => $medicine->category_name ?: '-',
                                    'medicine_group' => $medicine->medicine_group ?: '-',
                                    'large_unit' => $medicine->large_unit ?: '-',
                                    'small_unit' => $medicine->small_unit ?: '-',
                                    'small_unit_per_large_unit' => $medicine->small_unit_per_large_unit ? number_format($medicine->small_unit_per_large_unit) : '-',
                                    'minimum_stock' => number_format((float) $medicine->minimum_stock, 0, ',', '.'),
                                    'purchase_price' => 'Rp '.number_format((float) $medicine->purchase_price, 0, ',', '.'),
                                    'composition' => $medicine->composition ?: '-',
                                    'status_label' => $medicine->is_active ? 'Aktif' : 'Nonaktif',
                                    'status_active' => $medicine->is_active,
                                    'created_at' => $medicine->created_at?->translatedFormat('d M Y H:i') ?? '-',
                                ];
                            @endphp

                            <tr class="align-middle">
                                <td class="px-4 py-2.5 align-middle">
                                    <div class="flex min-h-8 items-center font-semibold text-slate-900">{{ $medicine->code }}</div>
                                </td>
                                <td class="px-4 py-2.5 align-middle">
                                    <div class="flex min-h-8 items-center font-semibold text-slate-900">{{ $medicine->name }}</div>
                                </td>
                                <td class="px-4 py-2.5 align-middle">
                                    <div class="flex min-h-8 items-center">
                                        <div class="max-w-sm content-copy">
                                        {{ $medicine->composition ? \Illuminate\Support\Str::limit($medicine->composition, 96) : 'Kandungan belum diisi.' }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 align-middle">
                                    <div class="flex min-h-8 items-center font-medium text-slate-900">
                                        @if ($medicine->large_unit || $medicine->small_unit)
                                            {{ $medicine->large_unit ?: '-' }} / {{ $medicine->small_unit ?: '-' }}
                                        @else
                                            -
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 align-middle">
                                    <div class="flex min-h-8 items-center font-medium text-slate-900">
                                        {{ $medicine->small_unit_per_large_unit ? number_format($medicine->small_unit_per_large_unit) : '-' }}
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 align-middle">
                                    <div class="flex min-h-8 items-center font-semibold text-slate-900">Rp {{ number_format((float) $medicine->purchase_price, 0, ',', '.') }}</div>
                                </td>
                                <td class="px-4 py-2.5 align-middle">
                                    <div class="flex min-h-8 items-center font-medium text-slate-900">{{ number_format((float) $medicine->minimum_stock, 0, ',', '.') }}</div>
                                </td>
                                <td class="px-4 py-2.5 text-center align-middle">
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
                                            title="Aksi obat"
                                            aria-label="Aksi {{ $medicine->name }}"
                                            @click="toggleMenu()"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                                                <circle cx="5" cy="12" r="1.75" />
                                                <circle cx="12" cy="12" r="1.75" />
                                                <circle cx="19" cy="12" r="1.75" />
                                            </svg>
                                            <span class="sr-only">Aksi</span>
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
                                                    href="{{ route('master-data.data-obat', array_merge(request()->except('edit'), ['edit' => $medicine->id])) }}"
                                                    class="flex items-center gap-2 px-3 py-2 text-[0.8rem] font-medium text-slate-700 transition hover:bg-slate-50 hover:text-emerald-700"
                                                    @click="close()"
                                                >
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M12 20h9" />
                                                        <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                                                    </svg>
                                                    <span>Ubah</span>
                                                </a>

                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-[0.8rem] font-medium text-slate-700 transition hover:bg-slate-50 hover:text-emerald-700"
                                                    @click="close(); openDetail(@js($detailPayload))"
                                                >
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M2.06 12.35a1 1 0 0 1 0-.7C3.2 8.38 6.52 5 12 5s8.8 3.38 9.94 6.65a1 1 0 0 1 0 .7C20.8 15.62 17.48 19 12 19s-8.8-3.38-9.94-6.65Z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                    </svg>
                                                    <span>Detail</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-[0.8rem] font-medium text-rose-700 transition hover:bg-rose-50 hover:text-rose-800"
                                                    @click="close(); openDeleteDialog(@js([
                                                        'action' => route('master-data.data-obat.destroy', $medicine),
                                                        'title' => 'Hapus data obat ini?',
                                                        'description' => 'Obat '.$medicine->name.' akan dihapus dari master data obat.',
                                                        'warning' => 'Pastikan obat ini belum dipakai pada stok, pembelian, atau referensi transaksi lain.',
                                                        'name' => $medicine->name,
                                                        'code' => $medicine->code,
                                                        'confirm_label' => 'Ya, hapus obat',
                                                    ]))"
                                                >
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M3 6h18" />
                                                        <path d="M8 6V4.75A1.75 1.75 0 0 1 9.75 3h4.5A1.75 1.75 0 0 1 16 4.75V6" />
                                                        <path d="M19 6l-.82 11.47A2 2 0 0 1 16.19 19H7.81a2 2 0 0 1-1.99-1.53L5 6" />
                                                        <path d="M10 10.5v5" />
                                                        <path d="M14 10.5v5" />
                                                    </svg>
                                                    <span>Hapus</span>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-14 text-center">
                                    <div class="mx-auto max-w-md space-y-3">
                                        <div class="empty-title">Belum ada data obat</div>
                                        <p class="content-copy">
                                            Tambah obat pertama untuk mulai mengisi kode barang, industri farmasi, jenis, kategori, satuan, harga beli, dan komposisi.
                                        </p>
                                        <button
                                            type="button"
                                            @click="createModalOpen = true"
                                            class="ui-action-btn ui-action-btn--soft"
                                        >
                                            Input obat pertama
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('components.master-delete-modal')
        </section>

        <div
            x-cloak
            x-show="createModalOpen"
            x-transition.opacity
            class="fixed inset-0 z-40 overflow-y-auto bg-slate-950/50 backdrop-blur-sm"
            @click.self="createModalOpen = false"
        >
            <div class="flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6">
                <div class="panel-surface relative z-50 max-h-[calc(100vh-2rem)] w-full max-w-5xl overflow-y-auto p-6 sm:max-h-[calc(100vh-3rem)] sm:p-7">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                                Tambah Obat
                            </div>
                        </div>

                        <button
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-900"
                            @click="createModalOpen = false"
                        >
                            <span class="sr-only">Tutup modal</span>
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                                <path d="M6 6l12 12M18 6 6 18" />
                            </svg>
                        </button>
                    </div>

                    @if ($errors->any() && old('_modal') === 'create')
                        <div class="mt-5 rounded-[1.35rem] border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            Periksa kembali input obat. Masih ada data yang perlu diperbaiki.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('master-data.data-obat.store') }}" class="mt-6 space-y-4">
                        @csrf
                        <input type="hidden" name="_modal" value="create">

                        @php
                            $medicine = $newMedicine;
                            $selectedPrincipalId = old('principal_id');
                            $fieldPrefix = 'create_';
                        @endphp

                        @include('medicines._fields')

                        <div class="flex flex-col gap-3 border-t border-slate-200/80 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <button
                                type="button"
                                class="ui-action-btn ui-action-btn--neutral"
                                @click="createModalOpen = false"
                            >
                                Batal
                            </button>

                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-2xl border border-emerald-300 bg-emerald-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-600"
                            >
                                Simpan data obat
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if ($editingMedicine)
            <div
                x-cloak
                x-show="editModalOpen"
                x-transition.opacity
                class="fixed inset-0 z-40 overflow-y-auto bg-slate-950/50 backdrop-blur-sm"
                @click.self="closeEdit()"
            >
                <div class="flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6">
                    <div class="panel-surface relative z-50 max-h-[calc(100vh-2rem)] w-full max-w-5xl overflow-y-auto p-6 sm:max-h-[calc(100vh-3rem)] sm:p-7">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="inline-flex items-center gap-2 rounded-full border border-sky-100 bg-sky-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">
                                    Edit Obat
                                </div>
                            </div>

                            <button
                                type="button"
                                class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-900"
                                @click="closeEdit()"
                            >
                                <span class="sr-only">Tutup modal</span>
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                                    <path d="M6 6l12 12M18 6 6 18" />
                                </svg>
                            </button>
                        </div>

                        @if ($errors->any() && old('_modal') === 'edit')
                            <div class="mt-5 rounded-[1.35rem] border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                Periksa kembali input obat. Masih ada data yang perlu diperbaiki.
                            </div>
                        @endif

                        <form method="POST" action="{{ route('master-data.data-obat.update', $editingMedicine) }}" class="mt-6 space-y-4">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="_modal" value="edit">
                            <input type="hidden" name="_edit_id" value="{{ $editingMedicine->id }}">

                            @php
                                $medicine = $editingMedicine;
                                $selectedPrincipalId = old('principal_id', $editingMedicine->principal_id);
                                $principalOptions = $editFormOptions['principalOptions'];
                                $typeSuggestions = $editFormOptions['typeSuggestions'];
                                $categorySuggestions = $editFormOptions['categorySuggestions'];
                                $groupSuggestions = $editFormOptions['groupSuggestions'];
                                $largeUnitSuggestions = $editFormOptions['largeUnitSuggestions'];
                                $smallUnitSuggestions = $editFormOptions['smallUnitSuggestions'];
                                $fieldPrefix = 'edit_';
                            @endphp

                            @include('medicines._fields')

                            <div class="flex flex-col gap-3 border-t border-slate-200/80 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                <button
                                    type="button"
                                    class="ui-action-btn ui-action-btn--neutral"
                                    @click="closeEdit()"
                                >
                                    Batal
                                </button>

                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-2xl border border-sky-300 bg-sky-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-sky-500/20 transition hover:bg-sky-600"
                                >
                                    Simpan perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <div
            x-cloak
            x-show="detailModalOpen"
            x-transition.opacity
            class="fixed inset-0 z-40 overflow-y-auto bg-slate-950/50 backdrop-blur-sm"
            @click.self="closeDetail()"
        >
            <div class="flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6">
                <div class="panel-surface relative z-50 max-h-[calc(100vh-2rem)] w-full max-w-4xl overflow-y-auto p-6 sm:max-h-[calc(100vh-3rem)] sm:p-7">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="inline-flex items-center gap-2 rounded-full border border-sky-100 bg-sky-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">
                                Detail Obat
                            </div>
                            <h3 class="mt-4 page-title" x-text="detailMedicine?.name"></h3>
                            <p class="mt-2 content-copy">
                                <span class="font-semibold text-slate-800">Kode:</span>
                                <span x-text="detailMedicine?.code"></span>
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-900"
                            @click="closeDetail()"
                        >
                            <span class="sr-only">Tutup detail</span>
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                                <path d="M6 6l12 12M18 6 6 18" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Industri Farmasi</p>
                            <p class="mt-2 text-[0.82rem] font-semibold text-slate-900" x-text="detailMedicine?.principal_name"></p>
                        </div>

                        <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Jenis</p>
                            <p class="mt-2 text-[0.82rem] font-semibold text-slate-900" x-text="detailMedicine?.medicine_type"></p>
                        </div>

                        <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Kategori</p>
                            <p class="mt-2 text-[0.82rem] font-semibold text-slate-900" x-text="detailMedicine?.category_name"></p>
                        </div>

                        <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Golongan</p>
                            <p class="mt-2 text-[0.82rem] font-semibold text-slate-900" x-text="detailMedicine?.medicine_group"></p>
                        </div>

                        <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Satuan</p>
                            <p class="mt-2 text-[0.82rem] font-semibold text-slate-900">
                                <span x-text="detailMedicine?.large_unit"></span>
                                <span class="text-slate-400"> / </span>
                                <span x-text="detailMedicine?.small_unit"></span>
                            </p>
                        </div>

                        <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Isi</p>
                            <p class="mt-2 text-[0.82rem] font-semibold text-slate-900" x-text="detailMedicine?.small_unit_per_large_unit"></p>
                        </div>

                        <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Harga Beli</p>
                            <p class="mt-2 text-[0.82rem] font-semibold text-slate-900" x-text="detailMedicine?.purchase_price"></p>
                        </div>

                        <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Stok Minimum</p>
                            <p class="mt-2 text-[0.82rem] font-semibold text-slate-900" x-text="detailMedicine?.minimum_stock"></p>
                        </div>

                        <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status</p>
                            <span
                                class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                :class="detailMedicine?.status_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                x-text="detailMedicine?.status_label"
                            ></span>
                        </div>

                        <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/80 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Dibuat</p>
                            <p class="mt-2 text-[0.82rem] font-semibold text-slate-900" x-text="detailMedicine?.created_at"></p>
                        </div>
                    </div>

                    <div class="mt-5 rounded-[1.45rem] border border-slate-200/80 bg-white p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Kandungan / Komposisi</p>
                        <p class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-700" x-text="detailMedicine?.composition"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
