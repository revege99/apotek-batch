<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
            <span>{{ $section }}</span>
            <span class="text-slate-300">/</span>
            <span class="text-slate-600">{{ $page['label'] }}</span>
        </div>
    </x-slot>

    <div class="space-y-5" x-data="masterDeleteDialog()">
        <section class="panel-surface overflow-hidden p-0">
            <div class="border-b border-slate-200/80 px-4 py-3">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex flex-wrap items-center gap-2 text-[0.72rem]">
                        <div class="rounded-full bg-slate-100 px-3 py-2 font-semibold text-slate-700">
                            {{ number_format($openingStockStats['entries']) }} dokumen
                        </div>
                        <div class="rounded-full bg-slate-100 px-3 py-2 font-semibold text-slate-700">
                            {{ number_format($openingStockStats['batches']) }} batch awal
                        </div>
                        <div class="rounded-full bg-slate-100 px-3 py-2 font-semibold text-slate-700">
                            {{ number_format($openingStockStats['medicines']) }} obat
                        </div>
                        <div class="rounded-full bg-slate-100 px-3 py-2 font-semibold text-slate-700">
                            {{ number_format($openingStockStats['quantity'], 0, ',', '.') }} unit
                        </div>
                        <div class="rounded-full bg-slate-100 px-3 py-2 font-semibold text-slate-700">
                            Rp {{ number_format($openingStockStats['value'], 0, ',', '.') }}
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('setup-saldo-awal.stok') }}" class="ui-action-btn ui-action-btn--neutral px-4 text-[0.74rem]">
                            Kembali ke input
                        </a>

                        <form method="GET" action="{{ route('setup-saldo-awal.stok.riwayat') }}" class="w-full max-w-xs">
                            <input
                                id="search"
                                name="search"
                                type="text"
                                value="{{ $search }}"
                                placeholder="Cari obat, batch, dokumen, lokasi"
                                class="ui-control"
                                @input.debounce.350ms="$el.form?.requestSubmit()"
                            >
                        </form>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="master-table min-w-full divide-y divide-slate-200/80 text-[0.8rem]">
                    <thead>
                        <tr class="text-left text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-slate-400">
                            <th class="px-4 py-2.5">Dokumen</th>
                            <th class="px-4 py-2.5">Obat</th>
                            <th class="px-4 py-2.5">Batch</th>
                            <th class="px-4 py-2.5">Lokasi</th>
                            <th class="px-4 py-2.5 text-center">Qty</th>
                            <th class="px-4 py-2.5 text-right">Harga Beli</th>
                            <th class="px-4 py-2.5 text-right">Nilai</th>
                            <th class="px-4 py-2.5">Tanggal</th>
                            <th class="table-action-head">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white">
                        @forelse ($entryItems as $row)
                            <tr class="align-middle">
                                <td class="px-4 py-2.5">
                                    <div class="flex min-h-8 items-center">
                                        <div>
                                            <div class="font-semibold text-slate-900">{{ $row->entry?->entry_number ?: '-' }}</div>
                                            <div class="mt-1 text-xs text-slate-500">{{ $row->entry?->opening_date?->translatedFormat('d M Y') ?? '-' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex min-h-8 items-center">
                                        <div>
                                            <div class="font-semibold text-slate-900">{{ $row->medicine?->name ?: '-' }}</div>
                                            <div class="mt-1 text-xs text-slate-500">{{ $row->medicine?->code ?: '-' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex min-h-8 items-center">
                                        <div>
                                            <div class="font-semibold text-slate-900">{{ $row->batch_number ?: '-' }}</div>
                                            <div class="mt-1 text-xs text-slate-500">{{ $row->expiry_date?->translatedFormat('d M Y') ?? 'Tanpa expired' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex min-h-8 items-center text-slate-700">{{ $row->storageLocation?->name ?: '-' }}</div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex min-h-8 items-center justify-center font-semibold text-slate-900">
                                        {{ number_format((float) $row->quantity, 0, ',', '.') }}
                                        {{ $row->medicine?->small_unit ? ' '.$row->medicine->small_unit : '' }}
                                    </div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex min-h-8 items-center justify-end font-semibold text-slate-900">
                                        Rp {{ number_format((float) $row->purchase_price, 0, ',', '.') }}
                                    </div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex min-h-8 items-center justify-end font-semibold text-emerald-700">
                                        Rp {{ number_format((float) $row->quantity * (float) $row->purchase_price, 0, ',', '.') }}
                                    </div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex min-h-8 items-center text-slate-700">{{ $row->entry?->opening_date?->translatedFormat('d M Y') ?? '-' }}</div>
                                </td>
                                <td class="table-action-cell">
                                    <div class="table-action-group">
                                        <button
                                            type="button"
                                            class="table-icon-btn table-icon-btn--danger"
                                            @click="openDeleteDialog({
                                                action: @js(route('setup-saldo-awal.stok.destroy', $row)),
                                                title: 'Hapus saldo awal batch ini?',
                                                description: 'Batch saldo awal ini akan dihapus dari dokumen setup saldo awal stok.',
                                                warning: 'Batch hanya bisa dihapus jika belum dipakai oleh mutasi stok atau proses lain.',
                                                confirm_label: 'Ya, hapus batch',
                                                name: @js($row->medicine?->name ?: 'Batch saldo awal'),
                                                code: @js($row->batch_number ?: '-'),
                                            })"
                                            title="Hapus saldo awal batch"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M3 6h18" />
                                                <path d="M8 6V4.75A1.75 1.75 0 0 1 9.75 3h4.5A1.75 1.75 0 0 1 16 4.75V6" />
                                                <path d="M19 6l-.82 11.47A2 2 0 0 1 16.19 19H7.81a2 2 0 0 1-1.99-1.53L5 6" />
                                                <path d="M10 10.5v5" />
                                                <path d="M14 10.5v5" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-14 text-center">
                                    <div class="mx-auto max-w-md space-y-3">
                                        <div class="empty-title">Belum ada riwayat saldo awal stok</div>
                                        <p class="content-copy">Data batch saldo awal yang sudah diposting akan muncul di halaman ini.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($entryItems->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4">
                    {{ $entryItems->links() }}
                </div>
            @endif
        </section>

        <x-master-delete-modal />
    </div>
</x-app-layout>
