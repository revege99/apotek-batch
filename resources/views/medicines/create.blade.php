<x-app-layout>
    <x-slot name="header">
        <div class="space-y-4">
            <div class="inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">
                {{ $section }}
            </div>

            <div class="space-y-3">
                <h2 class="page-title">Input Data Obat</h2>
                <p class="max-w-3xl content-copy">
                    Tambahkan obat baru dengan tampilan form yang seirama dengan area navigasi dan modul kerja aplikasi.
                </p>
            </div>
        </div>
    </x-slot>

    @include('medicines._form')
</x-app-layout>
