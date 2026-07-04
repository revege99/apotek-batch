<x-app-layout>
    <x-slot name="header">
        <div class="space-y-4">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                <span>{{ $section }}</span>
                <span class="text-slate-300">/</span>
                <span class="text-slate-600">{{ $page['label'] }}</span>
            </div>

            <div class="space-y-2">
                <span class="inline-flex rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700">
                    Superadmin
                </span>
                <p class="max-w-4xl content-copy">{{ $description }}</p>
            </div>
        </div>
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-[1.1fr,0.9fr]">
        <section class="panel-surface p-6">
            <h2 class="section-title-lg">{{ $title }}</h2>
            <p class="mt-2 content-copy">
                Struktur sub menu sudah dipisah supaya tiap jenis saldo awal punya alur sendiri dan tidak bercampur dengan modul lain.
            </p>

            <div class="mt-5 space-y-3">
                @foreach ($checkpoints as $checkpoint)
                    <div class="flex items-start gap-3 rounded-[0.95rem] border border-slate-200/80 bg-slate-50/80 px-4 py-3">
                        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500"></span>
                        <p class="text-[0.76rem] leading-5 text-slate-600">{{ $checkpoint }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="panel-surface p-6">
            <h2 class="section-title">Status</h2>
            <div class="mt-4 rounded-[1rem] border border-sky-100 bg-sky-50/80 px-4 py-4">
                <p class="text-sm font-semibold text-sky-800">Pondasi submenu sudah siap</p>
                <p class="mt-2 text-[0.76rem] leading-5 text-slate-600">
                    Halaman ini siap kita lanjutkan ke form input dan histori khusus setelah modul saldo awal stok selesai dipastikan stabil.
                </p>
            </div>
        </section>
    </div>
</x-app-layout>
