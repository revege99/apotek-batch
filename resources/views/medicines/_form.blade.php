@php
    $isEditing = $medicine->exists;
@endphp

<div class="grid gap-6 xl:grid-cols-[minmax(0,1.55fr),340px]">
    <section class="panel-surface p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h3 class="section-title-lg">{{ $isEditing ? 'Ubah data obat' : 'Input data obat' }}</h3>
                <p class="mt-2 max-w-2xl content-copy">
                    Simpan kode barang, industri farmasi, nama barang, dan kandungan obat dalam format yang rapi dan mudah dicari.
                </p>
            </div>

            <span class="rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                {{ $isEditing ? 'Edit mode' : 'Tambah baru' }}
            </span>
        </div>

        <form method="POST" action="{{ $isEditing ? route('master-data.data-obat.update', $medicine) : route('master-data.data-obat.store') }}" class="mt-6 space-y-5">
            @csrf
            @if ($isEditing)
                @method('PATCH')
            @endif

            @include('medicines._fields')

            <div class="flex flex-col gap-3 border-t border-slate-200/80 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <a
                    href="{{ route('master-data.data-obat') }}"
                    class="ui-action-btn ui-action-btn--neutral"
                >
                    Kembali ke daftar
                </a>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-2xl border border-emerald-300 bg-emerald-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-600"
                >
                    {{ $isEditing ? 'Simpan perubahan' : 'Simpan data obat' }}
                </button>
            </div>
        </form>
    </section>

    <div class="space-y-6">
        <section class="panel-surface p-6">
            <h3 class="section-title">Panduan input</h3>
            <div class="mt-4 space-y-3">
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3">
                    <p class="text-sm font-semibold text-slate-900">Kode barang</p>
                    <p class="mt-1 content-copy">Gunakan kode unik dan konsisten agar mudah dipakai di pembelian, stok, dan kasir.</p>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3">
                    <p class="text-sm font-semibold text-slate-900">Industri farmasi</p>
                    <p class="mt-1 content-copy">Pilih industri farmasi dari master yang sudah tersedia agar referensi obat tetap rapi.</p>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3">
                    <p class="text-sm font-semibold text-slate-900">Jenis, kategori, golongan</p>
                    <p class="mt-1 content-copy">Rapikan klasifikasi obat agar laporan dan pencarian data jadi lebih cepat.</p>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3">
                    <p class="text-sm font-semibold text-slate-900">Satuan dan isi</p>
                    <p class="mt-1 content-copy">Isi membantu menjelaskan berapa satuan kecil yang ada di dalam satu satuan besar.</p>
                </div>
            </div>
        </section>

        <section class="panel-surface p-6">
            <h3 class="section-title">Submenu master data</h3>
            <div class="mt-4 space-y-2">
                @foreach ($siblings as $sibling)
                    <a
                        href="{{ route($sibling['route']) }}"
                        @class([
                            'flex items-center justify-between gap-3 rounded-2xl border px-4 py-3 text-sm transition',
                            'border-transparent bg-slate-950 text-white shadow-lg shadow-slate-900/15' => $sibling['route'] === 'master-data.data-obat',
                            'border-slate-200/80 bg-slate-50/80 text-slate-700 hover:border-emerald-200 hover:bg-white' => $sibling['route'] !== 'master-data.data-obat',
                        ])
                    >
                        <span>{{ $sibling['label'] }}</span>
                        <span class="text-xs font-semibold uppercase tracking-[0.18em] {{ $sibling['route'] === 'master-data.data-obat' ? 'text-emerald-300' : 'text-slate-400' }}">
                            {{ $sibling['route'] === 'master-data.data-obat' ? 'Aktif' : 'Buka' }}
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</div>
