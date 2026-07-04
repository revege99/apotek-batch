<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
            <span>{{ $section }}</span>
            <span class="text-slate-300">/</span>
            <span class="text-slate-600">{{ $page['label'] }}</span>
        </div>
    </x-slot>

    @php
        $difference = (float) $item->difference_quantity;
        $differenceType = $difference < 0 ? 'loss' : 'gain';
        $requiredQuantity = abs($difference);
        $physicalQuantity = (float) $item->physical_quantity;
        $isApplied = $item->followUp?->status === 'applied';
        $savedSettlementType = old('settlement_type', $item->followUp?->settlement_type ?? ($differenceType === 'loss' ? 'writeoff' : 'stock_found'));
        $savedEmployeeName = old('employee_name', $item->followUp?->employee_name);
        $savedReplacementBatchNumber = old('replacement_batch_number', $item->followUp?->replacement_batch_number);
        $savedReplacementExpiryDate = old('replacement_expiry_date', $item->followUp?->replacement_expiry_date?->toDateString());
        $savedReplacementPurchasePrice = old('replacement_purchase_price', $item->followUp?->replacement_purchase_price);
        $savedReplacementLocationId = old('replacement_storage_location_id', $item->followUp?->replacement_storage_location_id);
    @endphp

    <div x-data="{ settlementType: @js($savedSettlementType) }" class="space-y-5">
        <section class="panel-surface px-4 py-3">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="page-title text-[1.05rem]">Tindak Lanjut {{ $item->medicine?->name ?: '-' }}</h2>
                        <span class="inline-flex rounded-full border px-2.5 py-1 text-[0.64rem] font-semibold uppercase tracking-[0.14em] {{ $differenceType === 'loss' ? 'border-rose-100 bg-rose-50 text-rose-700' : 'border-sky-100 bg-sky-50 text-sky-700' }}">
                            {{ $differenceType === 'loss' ? 'Stok hilang' : 'Stok lebih' }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-x-5 gap-y-1 text-[0.74rem] text-slate-600">
                        <span>No opname {{ $item->stockOpname?->opname_number ?: '-' }}</span>
                        <span>Tanggal {{ $item->stockOpname?->opname_date?->translatedFormat('d M Y') ?? '-' }}</span>
                        <span>Selisih {{ number_format($requiredQuantity, 0, ',', '.') }} {{ $item->medicine?->small_unit ?: 'unit' }}</span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('stok-batch.penyesuaian-stok.dokumen', $item->stock_opname_id) }}" class="ui-action-btn ui-action-btn--soft inline-flex items-center gap-2 px-3 text-[0.74rem]">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 18l-6-6 6-6" />
                        </svg>
                        Kembali ke daftar
                    </a>
                    <a href="{{ $item->stockOpname ? route('stok-batch.stok-opname.show', $item->stockOpname->id) : '#' }}" class="ui-action-btn ui-action-btn--soft inline-flex items-center gap-2 px-3 text-[0.74rem]" @if (! $item->stockOpname) aria-disabled="true" @endif>
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        Lihat hasil opname
                    </a>
                </div>
            </div>
        </section>

        <section class="panel-surface px-4 py-3">
            <div class="flex flex-wrap items-center gap-2 text-[0.72rem]">
                <div class="rounded-full bg-slate-100 px-3 py-2 font-semibold text-slate-700">
                    Stok sistem {{ number_format((float) $item->system_quantity, 0, ',', '.') }}
                </div>
                <div class="rounded-full bg-slate-100 px-3 py-2 font-semibold text-slate-700">
                    Stok fisik {{ number_format((float) $item->physical_quantity, 0, ',', '.') }}
                </div>
                <div class="rounded-full {{ $differenceType === 'loss' ? 'bg-rose-50 text-rose-700' : 'bg-sky-50 text-sky-700' }} px-3 py-2 font-semibold">
                    {{ $differenceType === 'loss' ? 'Total hilang' : 'Total lebih' }} {{ number_format($requiredQuantity, 0, ',', '.') }}
                </div>
                <div class="rounded-full bg-amber-50 px-3 py-2 font-semibold text-amber-700">
                    Nilai selisih {{ (float) $item->adjustment_value >= 0 ? 'Rp '.number_format((float) $item->adjustment_value, 0, ',', '.') : '-Rp '.number_format(abs((float) $item->adjustment_value), 0, ',', '.') }}
                </div>
            </div>
        </section>

        <form method="POST" action="{{ route('stok-batch.penyesuaian-stok.follow-up.store', $item->id) }}" class="space-y-5">
            @csrf

            @if ($isApplied)
                <section class="panel-surface px-4 py-3">
                    <p class="text-[0.74rem] font-medium text-emerald-700">
                        Penyesuaian stok ini sudah diterapkan ke stok batch. Data batch ditampilkan sebagai referensi dan tidak bisa disimpan ulang.
                    </p>
                </section>
            @endif

            <section class="panel-surface px-4 py-3">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex w-full items-center gap-2 lg:w-auto lg:min-w-[17rem]">
                        <label class="shrink-0 text-[0.7rem] font-semibold text-slate-700" for="adjustment_number">No tindak lanjut</label>
                        <input id="adjustment_number" name="adjustment_number" type="text" value="{{ old('adjustment_number', $defaultAdjustmentNumber) }}" class="ui-control w-full px-2.5 text-[0.72rem] lg:w-[10.5rem]" @disabled($isApplied)>
                    </div>

                    <div class="flex w-full items-center gap-2 lg:w-auto lg:min-w-[12rem]">
                        <label class="shrink-0 text-[0.7rem] font-semibold text-slate-700" for="adjustment_date">Tanggal</label>
                        <input id="adjustment_date" name="adjustment_date" type="date" value="{{ old('adjustment_date', $defaultAdjustmentDate) }}" class="ui-control w-full px-2.5 text-[0.72rem] lg:w-[8rem]" @disabled($isApplied)>
                    </div>

                    <div class="flex w-full items-center gap-2 lg:w-auto lg:min-w-[16rem]">
                        <label class="shrink-0 text-[0.7rem] font-semibold text-slate-700" for="settlement_type">Jenis penyelesaian</label>
                        <select id="settlement_type" name="settlement_type" x-model="settlementType" class="ui-select-control w-full px-2.5 text-[0.72rem] lg:w-[9rem]" @disabled($isApplied)>
                            @if ($differenceType === 'loss')
                                <option value="writeoff" @selected($savedSettlementType === 'writeoff')>Hilang biasa</option>
                                <option value="replace_goods" @selected($savedSettlementType === 'replace_goods')>Ganti barang</option>
                                <option value="replace_cash" @selected($savedSettlementType === 'replace_cash')>Ganti uang</option>
                            @else
                                <option value="stock_found" @selected($savedSettlementType === 'stock_found')>Stok lebih ditemukan</option>
                            @endif
                        </select>
                    </div>

                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        <label class="shrink-0 text-[0.7rem] font-semibold text-slate-700" for="notes">Catatan</label>
                        <input id="notes" name="notes" type="text" value="{{ old('notes', $item->followUp?->notes) }}" class="ui-control min-w-0 flex-1 px-2.5 text-[0.72rem]" placeholder="Catatan tindak lanjut" @disabled($isApplied)>
                    </div>
                </div>
            </section>

            <section class="panel-surface px-4 py-3" x-show="settlementType === 'replace_cash'" x-cloak>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex w-full items-center gap-2 lg:w-auto lg:min-w-[18rem]">
                        <label class="shrink-0 text-[0.7rem] font-semibold text-slate-700" for="employee_name">Penanggung jawab</label>
                        <input id="employee_name" name="employee_name" type="text" value="{{ $savedEmployeeName }}" class="ui-control w-full px-2.5 text-[0.72rem] lg:w-[11rem]" placeholder="Nama pegawai" @disabled($isApplied)>
                    </div>
                    <div class="rounded-full bg-amber-50 px-3 py-2 text-[0.72rem] font-semibold text-amber-700">
                        Tagihan awal {{ 'Rp '.number_format(abs((float) $item->adjustment_value), 0, ',', '.') }}
                    </div>
                </div>
            </section>

            <section class="panel-surface px-4 py-3" x-show="settlementType === 'replace_goods'" x-cloak>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex w-full items-center gap-2 lg:w-auto lg:min-w-[15rem]">
                        <label class="shrink-0 text-[0.7rem] font-semibold text-slate-700" for="replacement_batch_number">Batch baru</label>
                        <input id="replacement_batch_number" name="replacement_batch_number" type="text" value="{{ $savedReplacementBatchNumber }}" class="ui-control w-full px-2.5 text-[0.72rem] lg:w-[9rem]" placeholder="Batch pengganti" @disabled($isApplied)>
                    </div>
                    <div class="flex w-full items-center gap-2 lg:w-auto lg:min-w-[13rem]">
                        <label class="shrink-0 text-[0.7rem] font-semibold text-slate-700" for="replacement_expiry_date">Expired</label>
                        <input id="replacement_expiry_date" name="replacement_expiry_date" type="date" value="{{ $savedReplacementExpiryDate }}" class="ui-control w-full px-2.5 text-[0.72rem] lg:w-[8rem]" @disabled($isApplied)>
                    </div>
                    <div class="flex w-full items-center gap-2 lg:w-auto lg:min-w-[15rem]">
                        <label class="shrink-0 text-[0.7rem] font-semibold text-slate-700" for="replacement_purchase_price">Harga beli</label>
                        <input id="replacement_purchase_price" name="replacement_purchase_price" type="number" min="0" step="0.01" value="{{ $savedReplacementPurchasePrice }}" class="ui-control w-full px-2.5 text-[0.72rem] lg:w-[8rem]" placeholder="0" @disabled($isApplied)>
                    </div>
                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        <label class="shrink-0 text-[0.7rem] font-semibold text-slate-700" for="replacement_storage_location_id">Lokasi masuk</label>
                        <select id="replacement_storage_location_id" name="replacement_storage_location_id" class="ui-select-control min-w-0 flex-1 px-2.5 text-[0.72rem]" @disabled($isApplied)>
                            <option value="">Tanpa lokasi</option>
                            @foreach ($activeLocations as $location)
                                <option value="{{ $location->id }}" @selected((string) $savedReplacementLocationId === (string) $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rounded-full bg-sky-50 px-3 py-2 text-[0.72rem] font-semibold text-sky-700">
                        Qty batch pengganti {{ number_format($requiredQuantity, 0, ',', '.') }} {{ $item->medicine?->small_unit ?: 'unit' }}
                    </div>
                </div>
            </section>

            <section class="panel-surface overflow-hidden p-0">
                <div class="border-b border-slate-200/80 px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="section-title">Pilih batch yang ditindaklanjuti</h3>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <p class="text-[0.72rem] text-slate-500">
                                Total stok fisik batch harus sama dengan {{ number_format($physicalQuantity, 0, ',', '.') }} {{ $item->medicine?->small_unit ?: 'unit' }}.
                            </p>
                            @if ($showAllBatches)
                                <a href="{{ route('stok-batch.penyesuaian-stok.follow-up', ['stockOpnameItem' => $item->id]) }}" class="ui-action-btn ui-action-btn--neutral inline-flex items-center gap-2 px-3 text-[0.72rem]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M8 6h10" />
                                        <path d="M8 12h10" />
                                        <path d="M8 18h10" />
                                        <path d="M4 6h.01" />
                                        <path d="M4 12h.01" />
                                        <path d="M4 18h.01" />
                                    </svg>
                                    Sembunyikan batch lama
                                </a>
                            @else
                                <a href="{{ route('stok-batch.penyesuaian-stok.follow-up', ['stockOpnameItem' => $item->id, 'show_all_batches' => 1]) }}" class="ui-action-btn ui-action-btn--neutral inline-flex items-center gap-2 px-3 text-[0.72rem]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 5v14" />
                                        <path d="M5 12h14" />
                                    </svg>
                                    Tampilkan batch lama
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[860px] w-full divide-y divide-slate-200/80 text-[0.74rem]">
                        <thead class="bg-slate-50/90">
                            <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <th class="px-3 py-3">Batch</th>
                                <th class="px-2.5 py-3">Lokasi</th>
                                <th class="px-2.5 py-3 text-center">Stok Sistem</th>
                                <th class="px-2.5 py-3 text-right">Harga Beli</th>
                                <th class="px-2.5 py-3 text-center">Stok Fisik Batch</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 bg-white">
                            @forelse ($batches as $index => $batch)
                                @php
                                    $savedSelection = $selectionMap->get((string) $batch->batch_number);
                                @endphp
                                <tr>
                                    <td class="px-3 py-2.5 font-semibold text-slate-900">{{ $batch->batch_number ?: '-' }}</td>
                                    <td class="px-2.5 py-2.5 text-slate-700">{{ $batch->location_label }}</td>
                                    <td class="px-2.5 py-2.5 text-center font-semibold text-slate-900">{{ number_format((float) $batch->quantity_balance, 0, ',', '.') }}</td>
                                    <td class="px-2.5 py-2.5 text-right font-semibold text-slate-900">{{ 'Rp '.number_format((float) $batch->purchase_price, 0, ',', '.') }}</td>
                                    <td class="px-2.5 py-2.5 text-center">
                                        <input type="hidden" name="batches[{{ $index }}][batch_number]" value="{{ $batch->batch_number }}">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            name="batches[{{ $index }}][quantity]"
                                            value="{{ old('batches.'.$index.'.quantity', $savedSelection?->quantity) }}"
                                            class="ui-control number-input-no-spinner mx-auto h-8 w-24 px-2 text-center text-[0.72rem]"
                                            @disabled($isApplied)
                                        >
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-10 text-center text-[0.78rem] text-slate-500">
                                        Belum ada batch untuk obat ini. Batch pengganti atau tindak lanjut lain akan kita tangani pada langkah berikutnya.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-200/80 px-4 py-3">
                    <div class="flex justify-end gap-2">
                        <a href="{{ route('stok-batch.penyesuaian-stok.dokumen', $item->stock_opname_id) }}" class="ui-action-btn ui-action-btn--neutral inline-flex items-center gap-2 px-3 text-[0.74rem]">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M15 18l-6-6 6-6" />
                            </svg>
                            Kembali
                        </a>
                        @unless ($isApplied)
                            <button type="submit" class="ui-action-btn ui-action-btn--neutral inline-flex items-center gap-2 px-4 text-[0.74rem]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12.5V7a2 2 0 0 1 2-2h8l4 4v8a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-1.5" />
                                    <path d="M9 5v4h6" />
                                    <path d="M9 13h6" />
                                </svg>
                                Simpan draft tindak lanjut
                            </button>
                        @endunless
                    </div>
                </div>
            </section>
        </form>

        @if ($item->followUp && $isApplied)
            <section class="panel-surface px-4 py-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-[0.74rem] text-slate-600">
                        Jika proses ini dibatalkan, stok batch akan dikembalikan seperti sebelum diproses. Untuk ganti uang, pembatalan hanya bisa dilakukan jika tagihannya belum memiliki pembayaran.
                    </p>
                    <form method="POST" action="{{ route('stok-batch.penyesuaian-stok.follow-up.cancel', $item->id) }}">
                        @csrf
                        <button type="submit" class="ui-action-btn ui-action-btn--neutral inline-flex items-center gap-2 px-4 text-[0.74rem]">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 6h18" />
                                <path d="M8 6V4h8v2" />
                                <path d="m19 6-1 13H6L5 6" />
                                <path d="M10 11v5" />
                                <path d="M14 11v5" />
                            </svg>
                            Batalkan proses
                        </button>
                    </form>
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
