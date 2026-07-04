@php
    $statusClasses = match ($licenseStatus['code']) {
        'active' => 'border-emerald-100 bg-emerald-50 text-emerald-700',
        'expired' => 'border-rose-100 bg-rose-50 text-rose-700',
        default => 'border-slate-200 bg-slate-100 text-slate-700',
    };
    $manualOpen = old('_manual_license_form') === 'manual' || $errors->has('manual_expires_at');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="space-y-4">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $section }}</span>
                <span class="text-slate-300">/</span>
                <span class="text-slate-600">{{ $page['label'] }}</span>
            </div>

            <div class="space-y-2">
                <span class="inline-flex rounded-full border border-amber-100 bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-amber-700">
                    Superadmin
                </span>
                <p class="max-w-3xl content-copy">
                    Kelola pengajuan lisensi dari user, unggah QRIS pembayaran, generate kode aktivasi, atau buat lisensi manual tanpa pengajuan bila dibutuhkan.
                </p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 xl:grid-cols-4">
            <div class="panel-surface stats-card">
                <p class="stats-card__label">Status lisensi</p>
                <p class="stats-card__value text-2xl">{{ $licenseStatus['label'] }}</p>
            </div>

            <div class="panel-surface stats-card">
                <p class="stats-card__label">Berlaku sampai</p>
                <p class="stats-card__value text-2xl">{{ $licenseStatus['expires_at_label'] }}</p>
            </div>

            <div class="panel-surface stats-card">
                <p class="stats-card__label">Pengajuan pending</p>
                <p class="stats-card__value">{{ number_format($stats['pending']) }}</p>
            </div>

            <div class="panel-surface stats-card">
                <p class="stats-card__label">Kode siap aktivasi</p>
                <p class="stats-card__value">{{ number_format($stats['ready_codes']) }}</p>
            </div>
        </section>

        <details class="panel-surface overflow-hidden p-0" {{ $manualOpen ? 'open' : '' }}>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-5">
                <div>
                    <h3 class="section-title-lg">Buat Lisensi Manual</h3>
                    <p class="mt-1 text-[0.76rem] text-slate-500">Gunakan jika superadmin ingin menyiapkan kode lisensi langsung tanpa menunggu pengajuan dari user.</p>
                </div>

                <span class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-slate-950 px-4 py-2 text-[0.74rem] font-semibold text-white shadow-sm transition">
                    Buka Form Manual
                </span>
            </summary>

            <div class="border-t border-slate-200/80 px-6 py-6">
                <form method="POST" action="{{ route('pengaturan.manajemen-lisensi.manual') }}" class="grid gap-5 xl:grid-cols-[1.05fr,0.95fr]">
                    @csrf
                    <input type="hidden" name="_manual_license_form" value="manual">

                    <div class="space-y-4">
                        <div>
                            <label for="manual_expires_at" class="text-sm font-semibold text-slate-800">Berlaku Sampai</label>
                            <input
                                id="manual_expires_at"
                                name="manual_expires_at"
                                type="datetime-local"
                                value="{{ old('manual_expires_at') }}"
                                class="mt-2 ui-control ui-control--amber"
                            >
                            @error('manual_expires_at')
                                <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-2xl border border-amber-300 bg-amber-500 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600"
                        >
                            Buat Kode Lisensi Manual
                        </button>
                    </div>

                    <div class="rounded-[1.75rem] border border-slate-200/80 bg-slate-50/80 p-5">
                        <p class="text-sm font-semibold text-slate-950">Cara Kerja Lisensi Manual</p>
                        <div class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                            <p>1. Superadmin menentukan sendiri tanggal dan jam berakhirnya lisensi.</p>
                            <p>2. Sistem membuat satu kode aktivasi baru tanpa pengajuan dari user.</p>
                            <p>3. Setelah admin memasukkan kode itu, masa aktif aplikasi akan mengikuti tanggal dan jam yang Anda set di sini.</p>
                        </div>
                    </div>
                </form>
            </div>
        </details>

        <section class="panel-surface p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="section-title-lg">QRIS Pembayaran Lisensi</h3>
                    <p class="mt-2 content-copy">QRIS ini akan tampil langsung di halaman lisensi agar user bisa membayar setelah membuat pengajuan.</p>
                </div>
                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] {{ $statusClasses }}">
                    {{ $licenseStatus['label'] }}
                </span>
            </div>

            <form method="POST" action="{{ route('pengaturan.manajemen-lisensi.qris') }}" enctype="multipart/form-data" class="mt-6 grid gap-5 lg:grid-cols-[1fr,0.9fr]">
                @csrf

                <div class="space-y-4">
                    <div>
                        <label for="receiver_name" class="text-sm font-semibold text-slate-800">Nama Penerima</label>
                        <input
                            id="receiver_name"
                            name="receiver_name"
                            type="text"
                            value="{{ old('receiver_name', $paymentSettings['receiver_name']) }}"
                            class="mt-2 ui-control ui-control--amber"
                        >
                        @error('receiver_name')
                            <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="qris_image" class="text-sm font-semibold text-slate-800">Gambar QRIS</label>
                        <input
                            id="qris_image"
                            name="qris_image"
                            type="file"
                            accept="image/*"
                            class="mt-2 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 file:mr-3 file:rounded-xl file:border-0 file:bg-slate-950 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-amber-600"
                        >
                        @error('qris_image')
                            <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="payment_notes" class="text-sm font-semibold text-slate-800">Catatan Pembayaran</label>
                        <textarea
                            id="payment_notes"
                            name="payment_notes"
                            rows="4"
                            class="mt-2 ui-control ui-control--amber"
                        >{{ old('payment_notes', $paymentSettings['notes']) }}</textarea>
                        @error('payment_notes')
                            <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-2xl border border-amber-300 bg-amber-500 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600"
                    >
                        Simpan QRIS Lisensi
                    </button>
                </div>

                <div class="rounded-[1.75rem] border border-slate-200/80 bg-slate-50/80 p-5">
                    @if (! empty($paymentSettings['qris_image_path']))
                        <img
                            src="{{ route('pengaturan.lisensi.qris-image', ['v' => md5((string) $paymentSettings['qris_image_path'])]) }}"
                            alt="QRIS lisensi"
                            class="mx-auto h-64 w-64 rounded-[1.5rem] border border-slate-200 bg-white object-contain p-3 shadow-sm"
                        >
                    @else
                        <div class="mx-auto flex h-64 w-64 items-center justify-center rounded-[1.5rem] border border-dashed border-slate-300 bg-white px-6 text-center text-sm leading-6 text-slate-500">
                            Belum ada gambar QRIS. Upload gambar agar tampil di halaman lisensi.
                        </div>
                    @endif

                    <div class="mt-4 space-y-2">
                        <p class="font-semibold text-slate-950">{{ $paymentSettings['receiver_name'] ?: 'QRIS Lisensi' }}</p>
                        <p class="text-sm leading-6 text-slate-500">{{ $paymentSettings['notes'] ?: 'Belum ada catatan pembayaran lisensi.' }}</p>
                    </div>
                </div>
            </form>
        </section>

        <section class="panel-surface overflow-hidden p-0">
            <div class="border-b border-slate-200/80 px-5 py-4">
                <h3 class="section-title">Pengajuan Perpanjangan Lisensi</h3>
                <p class="mt-1 text-[0.76rem] text-slate-500">Daftar permintaan perpanjangan dari user yang menunggu diproses oleh superadmin.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1100px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                    <thead class="bg-slate-50/90">
                        <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-3 py-3">Pengaju</th>
                            <th class="px-3 py-3">Durasi</th>
                            <th class="px-3 py-3">Berlaku Saat Itu</th>
                            <th class="px-3 py-3">Proyeksi</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Kode</th>
                            <th class="px-3 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($licenseRequests as $requestItem)
                            @php
                                $requestClasses = match ($requestItem->status) {
                                    'pending' => 'border-amber-100 bg-amber-50 text-amber-700',
                                    'code_generated' => 'border-sky-100 bg-sky-50 text-sky-700',
                                    'activated' => 'border-emerald-100 bg-emerald-50 text-emerald-700',
                                    default => 'border-slate-200 bg-slate-100 text-slate-700',
                                };
                                $requestLabel = match ($requestItem->status) {
                                    'pending' => 'Menunggu',
                                    'code_generated' => 'Kode Siap',
                                    'activated' => 'Aktif',
                                    default => 'Draft',
                                };
                            @endphp
                            <tr class="align-top">
                                <td class="px-4 py-3 text-slate-700">
                                    <p class="font-semibold text-slate-900">{{ $requestItem->created_at?->format('d-m-Y') ?? '-' }}</p>
                                    <p class="mt-1 text-[0.66rem] text-slate-400">{{ $requestItem->created_at?->format('H:i') ?? '-' }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-slate-900">{{ $requestItem->requestedBy?->name ?: '-' }}</p>
                                    <p class="mt-1 text-[0.66rem] text-slate-400">{{ $requestItem->requestedBy?->email ?: '-' }}</p>
                                </td>
                                <td class="px-3 py-3 font-semibold text-slate-900">{{ number_format($requestItem->duration_days) }} Hari</td>
                                <td class="px-3 py-3 text-slate-700">{{ $requestItem->current_expires_at?->format('d-m-Y H:i') ?? '-' }}</td>
                                <td class="px-3 py-3 font-semibold text-slate-900">{{ $requestItem->projected_expires_at?->format('d-m-Y H:i') ?? '-' }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[0.66rem] font-semibold uppercase tracking-[0.12em] {{ $requestClasses }}">
                                        {{ $requestLabel }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-slate-900">{{ $requestItem->activationCode?->code ?: '-' }}</p>
                                    @if ($requestItem->generatedBy)
                                        <p class="mt-1 text-[0.66rem] text-slate-400">{{ $requestItem->generatedBy->name }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if ($requestItem->status === 'pending')
                                        <form method="POST" action="{{ route('pengaturan.manajemen-lisensi.generate', $requestItem) }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-slate-950 px-3 py-2 text-[0.72rem] font-semibold text-white transition hover:bg-amber-600"
                                            >
                                                Generate Kode
                                            </button>
                                        </form>
                                    @elseif ($requestItem->status === 'code_generated')
                                        <div class="flex flex-col items-center gap-2">
                                            <span class="text-[0.72rem] font-semibold text-sky-700">Siap dikirim</span>
                                            <form method="POST" action="{{ route('pengaturan.manajemen-lisensi.cancel-generate', $requestItem) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center justify-center rounded-xl border border-rose-200 bg-white px-3 py-2 text-[0.72rem] font-semibold text-rose-700 transition hover:bg-rose-50"
                                                >
                                                    Batalkan Kode
                                                </button>
                                            </form>
                                        </div>
                                    @elseif ($requestItem->status === 'activated' && $requestItem->activationCode && $requestItem->activationCode->id === $revocableUsedCodeId)
                                        <div class="flex flex-col items-center gap-2">
                                            <span class="text-[0.72rem] font-semibold text-emerald-700">Lisensi aktif</span>
                                            <form method="POST" action="{{ route('pengaturan.manajemen-lisensi.codes.destroy', $requestItem->activationCode) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center justify-center rounded-xl border border-rose-200 bg-white px-3 py-2 text-[0.72rem] font-semibold text-rose-700 transition hover:bg-rose-50"
                                                >
                                                    Batalkan Lisensi
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-[0.72rem] font-semibold text-emerald-700">Sudah aktif</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-14 text-center">
                                    <div class="mx-auto max-w-md space-y-3">
                                        <div class="empty-title">Belum ada pengajuan lisensi</div>
                                        <p class="content-copy">Pengajuan dari user akan muncul di sini setelah mereka mengirim perpanjangan dari halaman lisensi.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($licenseRequests->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4">
                    {{ $licenseRequests->links() }}
                </div>
            @endif
        </section>

        <section class="panel-surface overflow-hidden p-0">
            <div class="border-b border-slate-200/80 px-5 py-4">
                <h3 class="section-title">Kode Lisensi Terbaru</h3>
                <p class="mt-1 text-[0.76rem] text-slate-500">Pantau kode yang sudah dibuat, siapa yang membuat, dan apakah kodenya sudah dipakai.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                    <thead class="bg-slate-50/90">
                        <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-3 py-3">Jenis Lisensi</th>
                            <th class="px-3 py-3">Sumber</th>
                            <th class="px-3 py-3">Dibuat Oleh</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Dipakai Oleh</th>
                            <th class="px-3 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($recentCodes as $codeItem)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ $codeItem->code }}</p>
                                    <p class="mt-1 text-[0.66rem] text-slate-400">{{ $codeItem->created_at?->format('d-m-Y H:i') ?? '-' }}</p>
                                </td>
                                <td class="px-3 py-3 text-slate-700">
                                    @if ($codeItem->license_type === 'manual')
                                        <p class="font-semibold text-slate-900">Manual</p>
                                        <p class="mt-1 text-[0.66rem] text-slate-400">
                                            Sampai {{ $codeItem->fixed_expires_at?->format('d-m-Y H:i') ?? '-' }}
                                        </p>
                                    @else
                                        <p class="font-semibold text-slate-900">{{ number_format((int) $codeItem->duration_days) }} Hari</p>
                                        <p class="mt-1 text-[0.66rem] text-slate-400">Perpanjangan bertahap</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-slate-700">{{ $codeItem->renewalRequest?->requestedBy?->name ?: 'Manual Superadmin' }}</td>
                                <td class="px-3 py-3 text-slate-700">{{ $codeItem->generatedBy?->name ?: '-' }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[0.66rem] font-semibold uppercase tracking-[0.12em] {{ $codeItem->status === 'used' ? 'border-emerald-100 bg-emerald-50 text-emerald-700' : 'border-sky-100 bg-sky-50 text-sky-700' }}">
                                        {{ $codeItem->status === 'used' ? 'Digunakan' : 'Tersedia' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-slate-700">
                                    <p>{{ $codeItem->usedBy?->name ?: '-' }}</p>
                                    @if ($codeItem->used_at)
                                        <p class="mt-1 text-[0.66rem] text-slate-400">{{ $codeItem->used_at->format('d-m-Y H:i') }}</p>
                                    @endif
                                    @if ($codeItem->status === 'used')
                                        <p class="mt-1 text-[0.66rem] text-slate-400">
                                            Aktif sampai {{ $codeItem->applied_until?->format('d-m-Y H:i') ?? '-' }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if ($codeItem->status === 'available')
                                        <form method="POST" action="{{ route('pengaturan.manajemen-lisensi.codes.destroy', $codeItem) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="inline-flex items-center justify-center rounded-xl border border-rose-200 bg-white px-3 py-2 text-[0.72rem] font-semibold text-rose-700 transition hover:bg-rose-50"
                                            >
                                                Hapus Kode
                                            </button>
                                        </form>
                                    @elseif ($codeItem->id === $revocableUsedCodeId)
                                        <form method="POST" action="{{ route('pengaturan.manajemen-lisensi.codes.destroy', $codeItem) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="inline-flex items-center justify-center rounded-xl border border-rose-200 bg-white px-3 py-2 text-[0.72rem] font-semibold text-rose-700 transition hover:bg-rose-50"
                                            >
                                                Batalkan Lisensi
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-[0.72rem] font-semibold text-slate-400">Terkunci</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-14 text-center">
                                    <div class="mx-auto max-w-md space-y-3">
                                        <div class="empty-title">Belum ada kode lisensi</div>
                                        <p class="content-copy">Kode lisensi akan tampil di sini setelah superadmin memproses pengajuan perpanjangan atau membuat lisensi manual.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
