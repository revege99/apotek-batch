<x-app-layout>
    <x-slot name="header">
        <div class="space-y-4">
            <div class="inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">
                {{ $section }}
            </div>

            <div class="space-y-3">
                <h2 class="page-title">Edit Data Obat</h2>
                <p class="max-w-3xl content-copy">
                    Perbarui data obat {{ $medicine->name }} tanpa mengubah gaya tampilan kerja yang sudah kamu arahkan.
                </p>
            </div>
        </div>
    </x-slot>

    @include('medicines._form')
</x-app-layout>
