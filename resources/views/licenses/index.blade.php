@php
    $statusClasses = match ($licenseStatus['code']) {
        'active' => 'border-emerald-100 bg-emerald-50 text-emerald-700',
        'expired' => 'border-rose-100 bg-rose-50 text-rose-700',
        default => 'border-slate-200 bg-slate-100 text-slate-700',
    };
    $statusCardClasses = match ($licenseStatus['code']) {
        'active' => 'border-emerald-200 bg-emerald-50/80',
        'expired' => 'border-rose-200 bg-rose-50/90',
        default => 'border-slate-200/80 bg-slate-50/80',
    };
    $statusValueClasses = match ($licenseStatus['code']) {
        'active' => 'text-emerald-900',
        'expired' => 'text-rose-700',
        default => 'text-slate-950',
    };
    $renewOpen = old('_license_form') === 'renew';
    $activateOpen = old('_license_form') === 'activate' || $errors->has('activation_code');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="space-y-4">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $section }}</span>
                <span class="text-slate-300">/</span>
                <span class="text-slate-600">{{ $page['label'] }}</span>
            </div>

            <div>
                <span class="inline-flex rounded-full border border-sky-100 bg-sky-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-sky-700">
                    Lisensi Aplikasi
                </span>
            </div>
        </div>
    </x-slot>

    <div
        x-data="{
            activePanel: @js($activateOpen ? 'activate' : ($renewOpen ? 'renew' : null)),
            qrisModalOpen: false,
            qrisDetail: null,
            qrisPayloads: @js($qrisPayloads),
            autoOpenQrisRequestId: @js($autoOpenQrisRequestId),
            duration: @js((string) old('duration_days', array_key_first($renewalOptions))),
            projectedDates: @js($projectedExpiryDates),
            init() {
                if (this.autoOpenQrisRequestId && this.qrisPayloads[this.autoOpenQrisRequestId]) {
                    this.openQris(this.qrisPayloads[this.autoOpenQrisRequestId]);
                }
            },
            projectedLabel() {
                return this.projectedDates[this.duration] ?? '-';
            },
            openQris(payload) {
                this.qrisDetail = payload;
                this.qrisModalOpen = true;
            },
            closeQris() {
                this.qrisModalOpen = false;
                this.qrisDetail = null;
            },
            togglePanel(panel) {
                this.activePanel = this.activePanel === panel ? null : panel;
            },
        }"
        @keydown.escape.window="closeQris()"
        class="space-y-6"
    >
        <section class="panel-surface p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-950">Status Lisensi</p>
                    <p class="mt-1 text-[0.76rem] text-slate-500">Informasi masa aktif lisensi aplikasi yang sedang berjalan.</p>
                </div>

                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] {{ $statusClasses }}">
                    {{ $licenseStatus['label'] }}
                </span>
            </div>

            <div class="mt-6 grid gap-3 md:grid-cols-3">
                <div class="rounded-[1.5rem] border p-4 {{ $statusCardClasses }}">
                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-slate-500">Status</p>
                    <p class="mt-2 text-lg font-semibold {{ $statusValueClasses }}">{{ $licenseStatus['label'] }}</p>
                </div>

                <div class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-4">
                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-slate-500">Berlaku Sampai</p>
                    <p class="mt-2 text-lg font-semibold text-slate-950">{{ $licenseStatus['expires_at_label'] }}</p>
                </div>

                <div class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-4">
                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-slate-500">Sisa Hari</p>
                    <p class="mt-2 text-lg font-semibold text-slate-950">{{ $licenseStatus['remaining_days_label'] }}</p>
                </div>
            </div>

            @if ($licenseStatus['code'] === 'expired')
                <div class="mt-4 rounded-[1.4rem] border border-rose-200 bg-rose-50 px-4 py-4 text-sm leading-6 text-rose-800">
                    Lisensi berakhir. Silahkan lakukan perpanjangan lisensi di menu profile lisensi agar modul transaksi bisa digunakan kembali.
                </div>
            @endif
        </section>

        <section class="panel-surface p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-950">Aksi Lisensi</p>
                    <p class="mt-1 text-[0.76rem] text-slate-500">Pilih tindakan yang ingin dijalankan untuk lisensi aplikasi.</p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button
                        type="button"
                        @click="togglePanel('renew')"
                        :class="activePanel === 'renew'
                            ? 'border-emerald-300 bg-emerald-500 text-white hover:bg-emerald-600'
                            : 'border-slate-200 bg-white text-slate-800 hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700'"
                        class="inline-flex items-center justify-center rounded-2xl border px-5 py-3 text-sm font-semibold shadow-sm transition"
                    >
                        Perpanjang Lisensi
                    </button>

                    <button
                        type="button"
                        @click="togglePanel('activate')"
                        :class="activePanel === 'activate'
                            ? 'border-sky-300 bg-sky-500 text-white hover:bg-sky-600'
                            : 'border-slate-200 bg-white text-slate-800 hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700'"
                        class="inline-flex items-center justify-center rounded-2xl border px-5 py-3 text-sm font-semibold shadow-sm transition"
                    >
                        Aktivasi Lisensi
                    </button>
                </div>
            </div>
        </section>

        <section x-cloak x-show="activePanel === 'renew'" x-transition.opacity.duration.150ms class="panel-surface p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="section-title-lg">Perpanjang Lisensi</h3>
                    <p class="mt-1 text-[0.76rem] text-slate-500">Pilih durasi lisensi lalu kirim pengajuan. QRIS akan muncul otomatis setelah pengajuan tersimpan.</p>
                </div>

                <button
                    type="button"
                    @click="activePanel = null"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-[0.72rem] font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                >
                    Tutup
                </button>
            </div>

            <form method="POST" action="{{ route('pengaturan.lisensi.renewal-request') }}" class="mt-5 grid gap-5 xl:grid-cols-[1.15fr,0.85fr]">
                @csrf
                <input type="hidden" name="_license_form" value="renew">

                <div class="space-y-4">
                    <div>
                        <label for="duration_days" class="text-sm font-semibold text-slate-800">Durasi Perpanjangan</label>
                        <select
                            id="duration_days"
                            name="duration_days"
                            x-model="duration"
                            class="mt-2 ui-select-control"
                        >
                            @foreach ($renewalOptions as $days => $label)
                                <option value="{{ $days }}">{{ $label }} ({{ number_format($days) }} hari)</option>
                            @endforeach
                        </select>
                        @error('duration_days')
                            <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-slate-500">Berlaku Saat Ini</p>
                            <p class="mt-2 text-sm font-semibold text-slate-950">{{ $licenseStatus['expires_at_label'] }}</p>
                        </div>

                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4">
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-emerald-700">Proyeksi Berlaku Sampai</p>
                            <p class="mt-2 text-sm font-semibold text-emerald-900" x-text="projectedLabel()"></p>
                        </div>
                    </div>

                    <div>
                        <label for="license_notes" class="text-sm font-semibold text-slate-800">Catatan Pengajuan</label>
                        <textarea
                            id="license_notes"
                            name="notes"
                            rows="4"
                            class="mt-2 ui-control"
                            placeholder="Opsional, misalnya konfirmasi pembayaran atau catatan untuk superadmin"
                        >{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="rounded-[1.75rem] border border-slate-200/80 bg-slate-50/80 p-5">
                    <p class="text-sm font-semibold text-slate-950">Alur Perpanjangan</p>
                    <div class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                        <p>1. Kirim pengajuan dari form ini terlebih dahulu.</p>
                        <p>2. Setelah tersimpan, QRIS pembayaran akan muncul otomatis.</p>
                        <p>3. Riwayat pengajuan tetap bisa membuka QRIS lagi sampai admin memproses lisensi Anda.</p>
                    </div>

                    <button
                        type="submit"
                        class="mt-6 inline-flex w-full items-center justify-center rounded-2xl border border-emerald-300 bg-emerald-500 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600"
                    >
                        Kirim Pengajuan Perpanjangan
                    </button>
                </div>
            </form>
        </section>

        <section x-cloak x-show="activePanel === 'activate'" x-transition.opacity.duration.150ms class="panel-surface p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="section-title-lg">Aktivasi Lisensi</h3>
                    <p class="mt-1 text-[0.76rem] text-slate-500">Masukkan kode lisensi dari admin untuk memperbarui masa aktif aplikasi.</p>
                </div>

                <button
                    type="button"
                    @click="activePanel = null"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-[0.72rem] font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                >
                    Tutup
                </button>
            </div>

            <form method="POST" action="{{ route('pengaturan.lisensi.activate') }}" class="mt-5 grid gap-5 lg:grid-cols-[1fr,auto] lg:items-end">
                @csrf
                <input type="hidden" name="_license_form" value="activate">

                <div>
                    <label for="activation_code" class="text-sm font-semibold text-slate-800">Kode Lisensi</label>
                    <input
                        id="activation_code"
                        name="activation_code"
                        type="text"
                        value="{{ old('activation_code') }}"
                        placeholder="Contoh: LIC-260604-ABC123"
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm uppercase tracking-[0.08em] text-slate-900 shadow-sm transition placeholder:normal-case placeholder:tracking-normal placeholder:text-slate-400 focus:border-sky-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-sky-100"
                    >
                    @error('activation_code')
                        <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-slate-950 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-600"
                >
                    Aktivasi Sekarang
                </button>
            </form>
        </section>

        <section class="panel-surface overflow-hidden p-0">
            <div class="border-b border-slate-200/80 px-5 py-4">
                <h3 class="section-title">Riwayat Perpanjangan Lisensi</h3>
                <p class="mt-1 text-[0.76rem] text-slate-500">Setiap pengajuan, kode yang sudah dibuat, dan hasil aktivasi akan tercatat di sini.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                    <thead class="bg-slate-50/90">
                        <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-3 py-3">Durasi</th>
                            <th class="px-3 py-3">Berlaku Saat Itu</th>
                            <th class="px-3 py-3">Proyeksi</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Kode Lisensi</th>
                            <th class="px-3 py-3">Diproses Oleh</th>
                            <th class="px-3 py-3 text-center">QRIS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($licenseHistory as $history)
                            @php
                                $historyClasses = match ($history->status) {
                                    'pending' => 'border-amber-100 bg-amber-50 text-amber-700',
                                    'code_generated' => 'border-sky-100 bg-sky-50 text-sky-700',
                                    'activated' => 'border-emerald-100 bg-emerald-50 text-emerald-700',
                                    default => 'border-slate-200 bg-slate-100 text-slate-700',
                                };
                                $historyLabel = match ($history->status) {
                                    'pending' => 'Sedang Diproses Admin',
                                    'code_generated' => 'Kode Siap Aktivasi',
                                    'activated' => 'Lisensi Aktif',
                                    default => 'Draft',
                                };
                            @endphp
                            <tr class="align-top">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ $history->created_at?->format('d-m-Y') ?? '-' }}</p>
                                    <p class="mt-1 text-[0.66rem] text-slate-400">{{ $history->requestedBy?->name ?: '-' }}</p>
                                </td>
                                <td class="px-3 py-3 text-slate-700">{{ number_format($history->duration_days) }} Hari</td>
                                <td class="px-3 py-3 text-slate-700">{{ $history->current_expires_at?->format('d-m-Y H:i') ?? '-' }}</td>
                                <td class="px-3 py-3 font-semibold text-slate-900">{{ $history->projected_expires_at?->format('d-m-Y H:i') ?? '-' }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[0.66rem] font-semibold uppercase tracking-[0.12em] {{ $historyClasses }}">
                                        {{ $historyLabel }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-slate-900">{{ $history->activationCode?->code ?: '-' }}</p>
                                    @if ($history->activated_at)
                                        <p class="mt-1 text-[0.66rem] text-slate-400">Aktivasi {{ $history->activated_at->format('d-m-Y H:i') }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-slate-700">{{ $history->generatedBy?->name ?: '-' }}</td>
                                <td class="px-3 py-3 text-center">
                                    @if ($history->status !== 'activated')
                                        <button
                                            type="button"
                                            @click="openQris(qrisPayloads[{{ $history->id }}])"
                                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-[0.72rem] font-semibold text-slate-700 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                                        >
                                            Lihat QRIS
                                        </button>
                                    @else
                                        <span class="text-[0.72rem] font-semibold text-slate-400">Selesai</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-14 text-center">
                                    <div class="mx-auto max-w-md space-y-3">
                                        <div class="empty-title">Belum ada riwayat lisensi</div>
                                        <p class="content-copy">Riwayat pengajuan perpanjangan dan aktivasi lisensi akan tampil di sini setelah Anda mulai mengajukan lisensi.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($licenseHistory->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4">
                    {{ $licenseHistory->links() }}
                </div>
            @endif
        </section>

        <div
            x-cloak
            x-show="qrisModalOpen"
            x-transition.opacity
            class="fixed inset-0 z-40 overflow-y-auto bg-slate-950/50 backdrop-blur-sm"
            @click.self="closeQris()"
        >
            <div class="flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6">
                <div class="panel-surface relative z-50 w-full max-w-xl p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="inline-flex rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                                QRIS Pembayaran
                            </div>
                            <h3 class="mt-3 text-lg font-semibold text-slate-950">Pembayaran Lisensi</h3>
                            <p class="mt-1 text-xs text-slate-500">
                                Pengajuan
                                <span class="font-semibold text-slate-700" x-text="qrisDetail?.requested_at ?? '-'"></span>
                                <span class="px-1 text-slate-300">/</span>
                                <span x-text="qrisDetail?.status_label ?? '-'"></span>
                            </p>
                        </div>

                        <button type="button" class="table-icon-btn" @click="closeQris()" aria-label="Tutup QRIS">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                                <path d="M6 6l12 12M18 6 6 18" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-5 grid gap-5 lg:grid-cols-[0.95fr,1.05fr]">
                        <div class="rounded-[1.75rem] border border-slate-200/80 bg-slate-50/80 p-5">
                            @if (! empty($paymentSettings['qris_image_path']))
                                <img
                                    src="{{ route('pengaturan.lisensi.qris-image', ['v' => md5((string) $paymentSettings['qris_image_path'])]) }}"
                                    alt="QRIS lisensi"
                                    class="mx-auto h-56 w-56 rounded-[1.5rem] border border-slate-200 bg-white object-contain p-3 shadow-sm"
                                >
                            @else
                                <div class="mx-auto flex h-56 w-56 items-center justify-center rounded-[1.5rem] border border-dashed border-slate-300 bg-white px-6 text-center text-sm leading-6 text-slate-500">
                                    QRIS lisensi belum diunggah oleh admin.
                                </div>
                            @endif

                            <div class="mt-4 space-y-2 text-sm">
                                <p class="font-semibold text-slate-950">{{ $paymentSettings['receiver_name'] ?: 'QRIS Lisensi' }}</p>
                                <p class="leading-6 text-slate-500">{{ $paymentSettings['notes'] ?: 'Gunakan QRIS ini untuk pembayaran lisensi, lalu tunggu admin memproses pengajuan Anda.' }}</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-slate-500">Durasi Pengajuan</p>
                                <p class="mt-2 text-sm font-semibold text-slate-950" x-text="qrisDetail?.duration_label ?? '-'"></p>
                            </div>

                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-slate-500">Berlaku Saat Ini</p>
                                <p class="mt-2 text-sm font-semibold text-slate-950" x-text="qrisDetail?.current_expires_at ?? '-'"></p>
                            </div>

                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-emerald-700">Proyeksi Berlaku Sampai</p>
                                <p class="mt-2 text-sm font-semibold text-emerald-900" x-text="qrisDetail?.projected_expires_at ?? '-'"></p>
                            </div>

                            <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-sm leading-6 text-amber-900">
                                Setelah pembayaran dilakukan, pengajuan akan tetap berstatus
                                <span class="font-semibold">sedang diproses admin</span>
                                sampai kode lisensi dibuat untuk Anda.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
