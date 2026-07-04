<x-app-layout>
    <x-slot name="header">
        <div class="space-y-4">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $section }}</span>
                <span class="text-slate-300">/</span>
                <span class="text-slate-600">{{ $page['label'] }}</span>
            </div>

            <div class="space-y-2">
                <span class="inline-flex rounded-full border border-rose-100 bg-rose-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-rose-700">
                    Superadmin
                </span>
                <p class="max-w-4xl content-copy">
                    Gunakan halaman ini hanya saat aplikasi siap dipakai di produksi dan Anda ingin membersihkan seluruh data transaksi lama tanpa menghapus master data.
                </p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 xl:grid-cols-4">
            <div class="panel-surface stats-card">
                <p class="stats-card__label">Total data transaksi</p>
                <p class="stats-card__value">{{ number_format($totalRows) }}</p>
            </div>

            @foreach ($summary as $label => $item)
                <div class="panel-surface stats-card">
                    <p class="stats-card__label">{{ $label }}</p>
                    <p class="stats-card__value">{{ number_format($item['rows']) }}</p>
                    <p class="mt-2 text-[0.72rem] text-slate-500">{{ number_format($item['tables']) }} tabel</p>
                </div>
            @endforeach
        </section>

        <section class="panel-surface rounded-[1rem] border border-rose-200/80 bg-gradient-to-br from-rose-50 via-white to-amber-50 p-6">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-rose-200 bg-white text-rose-600 shadow-sm">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 9v4" />
                        <path d="M12 17h.01" />
                        <path d="M10.3 3.83 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.83a2 2 0 0 0-3.4 0Z" />
                    </svg>
                </div>

                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-slate-950">Reset ini bersifat permanen</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Sistem akan menghapus data transaksi pembelian, penjualan, stok, stok opname, penyesuaian stok, pembayaran, dan histori turunannya. Laporan akan otomatis kosong karena sumber transaksinya sudah dibersihkan.
                    </p>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.05fr,0.95fr]">
            <div class="panel-surface overflow-hidden p-0">
                <div class="border-b border-slate-200/80 px-5 py-4">
                    <h3 class="section-title">Tabel yang Akan Dibersihkan</h3>
                    <p class="mt-1 text-[0.76rem] text-slate-500">Semua tabel ini akan dikosongkan dan nomor urutnya direset kembali dari awal.</p>
                </div>

                <div class="divide-y divide-slate-200/80">
                    @foreach ($groupedTables as $group => $tables)
                        <div class="px-5 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-sm font-semibold text-slate-900">{{ $group }}</h4>
                                <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[0.68rem] font-semibold text-slate-600">
                                    {{ number_format($summary[$group]['rows'] ?? 0) }} baris
                                </span>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($tables as $table)
                                    <span class="inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-[0.68rem] font-medium text-slate-600">
                                        {{ $table }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="space-y-6">
                <section class="panel-surface p-6">
                    <h3 class="section-title-lg">Data yang Tetap Dipertahankan</h3>
                    <div class="mt-4 space-y-3">
                        @foreach ($preservedScopes as $scope)
                            <div class="flex items-start gap-3 rounded-[0.9rem] border border-emerald-100 bg-emerald-50/60 px-4 py-3">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 12.75 11.25 15 15 9.75" />
                                    <circle cx="12" cy="12" r="8" />
                                </svg>
                                <p class="text-sm text-slate-700">{{ $scope }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="panel-surface p-6">
                    <h3 class="section-title-lg text-rose-700">Konfirmasi Reset Data</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Ketik <span class="rounded-md bg-slate-100 px-2 py-1 font-semibold text-slate-900">{{ $confirmationText }}</span> lalu centang persetujuan sebelum proses dijalankan.
                    </p>

                    <form method="POST" action="{{ route('pengaturan.reset-data-produksi.destroy') }}" class="mt-5 space-y-4">
                        @csrf
                        @method('DELETE')

                        <div>
                            <label for="confirmation_text" class="text-sm font-semibold text-slate-800">Teks konfirmasi</label>
                            <input
                                id="confirmation_text"
                                name="confirmation_text"
                                type="text"
                                value="{{ old('confirmation_text') }}"
                                autocomplete="off"
                                class="mt-2 ui-control"
                            >
                            @error('confirmation_text')
                                <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="flex items-start gap-3 rounded-[0.9rem] border border-slate-200 bg-slate-50/70 px-4 py-3">
                            <input
                                type="checkbox"
                                name="confirmation_acknowledged"
                                value="1"
                                @checked(old('confirmation_acknowledged'))
                                class="mt-1 h-4 w-4 rounded border-slate-300 text-rose-600 focus:ring-rose-200"
                            >
                            <span class="text-sm leading-6 text-slate-600">
                                Saya paham bahwa proses ini menghapus seluruh data non-master secara permanen dan tidak bisa dibatalkan.
                            </span>
                        </label>
                        @error('confirmation_acknowledged')
                            <p class="-mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror

                        <button
                            type="submit"
                            class="inline-flex h-[35px] items-center justify-center rounded-xl border border-rose-600 bg-rose-600 px-4 text-xs font-semibold text-white shadow-sm transition hover:border-rose-700 hover:bg-rose-700"
                        >
                            Hapus Semua Data Transaksi
                        </button>
                    </form>
                </section>
            </div>
        </section>
    </div>
</x-app-layout>
