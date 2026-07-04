@php
    $sections = \App\Support\NavigationAccess::navigationFor(auth()->user());
    $currentSection = $sections->firstWhere('label', $page['section']);
    $siblings = $currentSection['children'] ?? [];
    $playbooks = [
        'Master Data' => [
            'Mulai dari tabel data dengan pencarian, filter, dan pagination.',
            'Tambah form create dan edit dengan validasi kode unik serta status aktif.',
            'Hubungkan relasi ke obat, supplier, lokasi, atau satuan sesuai modulnya.',
        ],
        'Pembelian' => [
            'Siapkan header transaksi, detail item, dan total per faktur.',
            'Tambahkan alur penerimaan stok dan pembentukan hutang supplier.',
            'Sediakan histori pembelian serta retur untuk audit operasional.',
        ],
        'Penjualan' => [
            'Fokus pada alur kasir yang cepat, ringkas, dan aman untuk operator.',
            'Pastikan transaksi mengurangi stok serta menyimpan histori penjualan.',
            'Siapkan retur penjualan dan kontrol pembatalan sesuai hak akses.',
        ],
        'Stok & Batch' => [
            'Gunakan stok per batch sebagai sumber utama untuk expired dan traceability.',
            'Sediakan mutasi masuk-keluar agar kartu stok mudah diaudit.',
            'Tambahkan notifikasi stok minimum, batch hampir habis, dan opname.',
        ],
        'Keuangan' => [
            'Fokuskan ke piutang, pembayaran, dan tindak lanjut saldo yang butuh aksi operasional.',
            'Pisahkan daftar tagihan dari riwayat pembayaran agar pelunasan mudah dilacak.',
            'Jaga agar data keuangan harian tetap sinkron dengan transaksi pembelian dan penjualan.',
        ],
        'Laporan' => [
            'Utamakan filter tanggal, export, dan ringkasan angka utama per laporan.',
            'Sediakan tampilan rekap dan detail agar mudah dipakai owner maupun admin.',
            'Pastikan laporan penjualan, piutang, hutang, dan laba rugi membaca snapshot transaksi yang sama agar konsisten.',
        ],
        'Pengaturan' => [
            'Kumpulkan setting inti aplikasi di satu tempat yang mudah diubah admin.',
            'Pisahkan profil apotik, hak akses, pajak, dan toleransi agar jelas tanggung jawabnya.',
            'Tambahkan histori perubahan untuk setting yang berpengaruh ke transaksi.',
        ],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="space-y-4">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $page['section'] }}</span>
                <span class="text-slate-300">/</span>
                <span class="text-slate-600">{{ $page['label'] }}</span>
            </div>

            <div class="space-y-3">
                <span class="inline-flex rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700">
                    Route aktif
                </span>

                <p class="max-w-3xl content-copy">
                    {{ $page['section_summary'] }} Halaman ini sudah disiapkan sebagai ruang kerja awal untuk modul {{ strtolower($page['label']) }}.
                </p>
            </div>
        </div>
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-[1.45fr,0.95fr]">
        <div class="space-y-6">
            <section class="panel-surface p-6">
                <h3 class="section-title-lg">Ruang kerja awal modul</h3>
                <p class="mt-2 content-copy">
                    Kita bisa lanjutkan halaman ini menjadi CRUD penuh, form transaksi, atau laporan sesuai prioritas pengembangan aplikasi apotik.
                </p>

                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    <div class="rounded-[1.4rem] border border-slate-200/80 bg-slate-50/80 p-4">
                        <p class="text-sm font-semibold text-slate-950">Fokus pertama</p>
                        <p class="mt-2 content-copy">Tabel data, pencarian, filter, dan status penting untuk {{ strtolower($page['label']) }}.</p>
                    </div>

                    <div class="rounded-[1.4rem] border border-slate-200/80 bg-slate-50/80 p-4">
                        <p class="text-sm font-semibold text-slate-950">Data penting</p>
                        <p class="mt-2 content-copy">Field utama, relasi antar modul, serta validasi input agar transaksi tetap konsisten.</p>
                    </div>

                    <div class="rounded-[1.4rem] border border-slate-200/80 bg-slate-50/80 p-4">
                        <p class="text-sm font-semibold text-slate-950">Pengembangan lanjut</p>
                        <p class="mt-2 content-copy">Tambahkan export, histori, approval, atau alert setelah alur dasarnya stabil.</p>
                    </div>
                </div>
            </section>

            <section class="panel-surface p-6">
                <h3 class="section-title-lg">Langkah implementasi yang disarankan</h3>
                <div class="mt-5 space-y-3">
                    @foreach ($playbooks[$page['section']] ?? [] as $step)
                        <div class="flex items-start gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3">
                            <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500"></span>
                            <p class="content-copy">{{ $step }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="space-y-6">
            <section class="panel-surface p-6">
                <h3 class="section-title">Submenu satu kelompok</h3>
                <p class="mt-2 content-copy">Pindah cepat ke halaman lain dalam area {{ $page['section'] }}.</p>

                <div class="mt-5 space-y-2">
                    @foreach ($siblings as $sibling)
                        <a
                            href="{{ route($sibling['route']) }}"
                            class="@class([
                                'flex items-center justify-between gap-3 rounded-2xl border px-4 py-3 text-sm transition',
                                'border-transparent bg-slate-950 text-white shadow-lg shadow-slate-900/15' => $sibling['route'] === $page['route'],
                                'border-slate-200/80 bg-slate-50/80 text-slate-700 hover:border-emerald-200 hover:bg-white' => $sibling['route'] !== $page['route'],
                            ])"
                        >
                            <span>{{ $sibling['label'] }}</span>
                            <span class="@class([
                                'text-xs font-semibold uppercase tracking-[0.18em]' => true,
                                'text-emerald-300' => $sibling['route'] === $page['route'],
                                'text-slate-400' => $sibling['route'] !== $page['route'],
                            ])">
                                {{ $sibling['route'] === $page['route'] ? 'Aktif' : 'Buka' }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="panel-surface p-6">
                <h3 class="section-title">Shortcut</h3>

                <div class="mt-4 space-y-3">
                    <a href="{{ route('dashboard') }}" class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-sm text-slate-700 transition hover:border-emerald-200 hover:bg-white">
                        <span>Kembali ke Dashboard</span>
                        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Home</span>
                    </a>

                    <a href="{{ route('profile.edit') }}" class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-sm text-slate-700 transition hover:border-emerald-200 hover:bg-white">
                        <span>Profil Pengguna</span>
                        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-600">Akun</span>
                    </a>

                    <a href="{{ route('laporan.laporan-penjualan') }}" class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-sm text-slate-700 transition hover:border-emerald-200 hover:bg-white">
                        <span>Laporan Penjualan</span>
                        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-600">Report</span>
                    </a>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
