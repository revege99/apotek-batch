@php
    use Carbon\Carbon;

    $hasDateFilter = in_array($mode, ['purchase', 'sale', 'cash_receipt', 'writeoff_loss', 'expired', 'payable', 'receivable', 'profit_loss'], true);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
            <span>{{ $section }}</span>
            <span class="text-slate-300">/</span>
            <span class="text-slate-600">{{ $page['label'] }}</span>
        </div>
    </x-slot>

    <div class="space-y-5">
        <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-5">
            @foreach ($stats as $stat)
                @php
                    $toneClass = match ($stat['tone'] ?? 'slate') {
                        'emerald' => 'border-emerald-200 bg-emerald-50/80 text-emerald-700',
                        'amber' => 'border-amber-200 bg-amber-50/80 text-amber-700',
                        'rose' => 'border-rose-200 bg-rose-50/80 text-rose-700',
                        'sky' => 'border-sky-200 bg-sky-50/80 text-sky-700',
                        'violet' => 'border-violet-200 bg-violet-50/80 text-violet-700',
                        default => 'border-slate-200 bg-slate-50/80 text-slate-700',
                    };
                @endphp
                <div class="panel-surface rounded-[1.4rem] border {{ $toneClass }} px-4 py-3">
                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.14em] opacity-80">{{ $stat['label'] }}</p>
                    <p class="mt-1 text-base font-semibold">{{ $stat['value'] }}</p>
                </div>
            @endforeach
        </div>

        <section class="panel-surface overflow-hidden p-0">
            <div class="border-b border-slate-200/80 px-5 py-4">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                    <div class="shrink-0">
                        <h3 class="section-title">{{ $page['label'] }}</h3>
                        @if ($mode === 'profit_loss')
                            <p class="mt-1 text-[0.72rem] text-slate-500">Ringkasan laba kotor berdasarkan snapshot harga jual dan harga masuk transaksi.</p>
                        @endif
                    </div>

                    <form method="GET" action="{{ route($page['route']) }}" class="flex w-full flex-wrap items-center gap-2 xl:w-auto xl:flex-nowrap xl:justify-end">
                        <label class="sr-only" for="search">Cari data laporan</label>
                        <input
                            id="search"
                            name="search"
                            type="text"
                            value="{{ $search }}"
                            placeholder="Cari data laporan"
                            class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 text-[0.76rem] text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100 sm:min-w-[230px] xl:w-[250px] xl:flex-none"
                            style="height: 35px;"
                        >

                        @if ($mode === 'receivable')
                            <select
                                name="status"
                                class="rounded-xl border border-slate-200 bg-slate-50 px-3 text-[0.76rem] text-slate-900 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100 xl:w-[168px]"
                                style="height: 35px;"
                            >
                                <option value="all" @selected(($statusFilter ?? 'all') === 'all')>Semua Status</option>
                                <option value="unpaid" @selected(($statusFilter ?? 'all') === 'unpaid')>Belum Lunas</option>
                                <option value="paid" @selected(($statusFilter ?? 'all') === 'paid')>Sudah Lunas</option>
                            </select>
                        @endif

                        @if ($hasDateFilter)
                            <input
                                name="date_from"
                                type="date"
                                value="{{ $dateFrom }}"
                                class="rounded-xl border border-slate-200 bg-slate-50 px-3 text-[0.76rem] text-slate-900 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100 xl:w-[148px]"
                                style="height: 35px;"
                            >

                            <input
                                name="date_to"
                                type="date"
                                value="{{ $dateTo }}"
                                class="rounded-xl border border-slate-200 bg-slate-50 px-3 text-[0.76rem] text-slate-900 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100 xl:w-[148px]"
                                style="height: 35px;"
                            >
                        @endif

                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-[0.74rem] font-semibold text-slate-700 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700 xl:min-w-[92px]"
                            style="height: 35px;"
                        >
                            Terapkan
                        </button>

                        <a
                            href="{{ route($page['route']) }}"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-[0.74rem] font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 xl:min-w-[82px]"
                            style="height: 35px;"
                        >
                            Reset
                        </a>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                @if ($mode === 'purchase')
                    <table class="min-w-[1020px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                        <thead class="bg-slate-50/90">
                            <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <th class="px-4 py-3">No Faktur</th>
                                <th class="px-3 py-3">Tanggal</th>
                                <th class="px-3 py-3">Supplier</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-3 py-3 text-right">Subtotal</th>
                                <th class="px-3 py-3 text-right">PPN</th>
                                <th class="px-3 py-3 text-right">Grand Total</th>
                                <th class="px-3 py-3 text-right">Sisa Hutang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 bg-white">
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ $row->invoice_number }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->invoice_date?->translatedFormat('d M Y') ?? '-' }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->supplier?->name ?: '-' }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ ucfirst($row->payment_status) }}</td>
                                    <td class="px-3 py-3 text-right text-slate-700">Rp {{ number_format((float) $row->subtotal, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-right text-slate-700">Rp {{ number_format((float) $row->tax_amount, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-emerald-700">Rp {{ number_format((float) $row->grand_total, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-amber-700">Rp {{ number_format((float) $row->outstanding_amount, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-14 text-center">
                                        <div class="empty-title">Belum ada data pembelian pada periode ini</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($mode === 'sale')
                    <table class="min-w-[1040px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                        <thead class="bg-slate-50/90">
                            <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <th class="px-4 py-3">No Jual</th>
                                <th class="px-3 py-3">Tanggal</th>
                                <th class="px-3 py-3">Pelanggan</th>
                                <th class="px-3 py-3">Metode</th>
                                <th class="px-3 py-3">Pelunasan</th>
                                <th class="px-3 py-3">Tanggal Pelunasan</th>
                                <th class="px-3 py-3 text-right">Total</th>
                                <th class="px-3 py-3 text-right">Bayar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 bg-white">
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ $row->sale_number }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->sale_date?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->customer_name ?: '-' }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->payment_method_label }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->settlement_status_label }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->settlement_date_label }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-emerald-700">Rp {{ number_format((float) $row->grand_total, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-right text-slate-700">Rp {{ number_format((float) $row->paid_amount, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-14 text-center">
                                        <div class="empty-title">Belum ada data penjualan pada periode ini</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($mode === 'cash_receipt')
                    <table class="min-w-[1080px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                        <thead class="bg-slate-50/90">
                            <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-3 py-3">Sumber</th>
                                <th class="px-3 py-3">Dokumen</th>
                                <th class="px-3 py-3">Pelanggan</th>
                                <th class="px-3 py-3">Metode</th>
                                <th class="px-3 py-3">Keterangan</th>
                                <th class="px-3 py-3 text-right">Nominal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 bg-white">
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 text-slate-700">{{ $row->receipt_date?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->source_label }}</td>
                                    <td class="px-3 py-3 font-semibold text-slate-900">{{ $row->document_number }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->customer_name }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->payment_method_label }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->notes }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-emerald-700">Rp {{ number_format((float) $row->amount, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-14 text-center">
                                        <div class="empty-title">Belum ada penerimaan kas pada periode ini</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($mode === 'writeoff_loss')
                    <table class="min-w-[1180px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                        <thead class="bg-slate-50/90">
                            <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <th class="px-4 py-3">Tanggal Proses</th>
                                <th class="px-3 py-3">No Tindak Lanjut</th>
                                <th class="px-3 py-3">No Opname</th>
                                <th class="px-3 py-3">Obat</th>
                                <th class="px-3 py-3">Batch</th>
                                <th class="px-3 py-3">Lokasi</th>
                                <th class="px-3 py-3 text-right">Qty Hilang</th>
                                <th class="px-3 py-3 text-right">Harga Beli</th>
                                <th class="px-3 py-3 text-right">Nilai Hilang</th>
                                <th class="px-3 py-3">Diproses Oleh</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 bg-white">
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 text-slate-700">{{ $row->movement_date?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                                    <td class="px-3 py-3 font-semibold text-slate-900">{{ $row->adjustment_number ?: '-' }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->opname_number ?: '-' }}</td>
                                    <td class="px-3 py-3">
                                        <p class="font-semibold text-slate-900">{{ $row->medicine_name ?: '-' }}</p>
                                        <p class="mt-1 text-[0.66rem] text-slate-400">{{ $row->medicine_code ?: '-' }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->batch_number ?: '-' }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->location_name ?: '-' }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-rose-700">{{ number_format((float) $row->quantity_out, 0, ',', '.') }} {{ $row->small_unit ?: '' }}</td>
                                    <td class="px-3 py-3 text-right text-slate-700">Rp {{ number_format((float) $row->unit_cost, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-rose-700">Rp {{ number_format((float) $row->quantity_out * (float) $row->unit_cost, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->processed_by_name ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-5 py-14 text-center">
                                        <div class="empty-title">Belum ada data hilang biasa pada periode ini</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($mode === 'stock')
                    <table class="min-w-[1000px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                        <thead class="bg-slate-50/90">
                            <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <th class="px-4 py-3">Kode</th>
                                <th class="px-3 py-3">Nama Obat</th>
                                <th class="px-3 py-3">Principal</th>
                                <th class="px-3 py-3 text-center">Batch</th>
                                <th class="px-3 py-3 text-right">Total Stok</th>
                                <th class="px-3 py-3">Expired Terdekat</th>
                                <th class="px-3 py-3 text-right">Nilai Stok</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 bg-white">
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ $row->code }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->name }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->principal_name ?: '-' }}</td>
                                    <td class="px-3 py-3 text-center font-semibold text-slate-900">{{ number_format((int) $row->batch_count) }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-emerald-700">{{ number_format((float) $row->total_stock, 0, ',', '.') }} {{ $row->small_unit ?: '' }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->nearest_expiry ? Carbon::parse($row->nearest_expiry)->translatedFormat('d M Y') : '-' }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-sky-700">Rp {{ number_format((float) $row->stock_value, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-14 text-center">
                                        <div class="empty-title">Belum ada data stok yang cocok</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($mode === 'expired')
                    <table class="min-w-[1080px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                        <thead class="bg-slate-50/90">
                            <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <th class="px-4 py-3">Obat</th>
                                <th class="px-3 py-3">Principal</th>
                                <th class="px-3 py-3">Batch</th>
                                <th class="px-3 py-3">Expired</th>
                                <th class="px-3 py-3">Hitung Mundur</th>
                                <th class="px-3 py-3 text-right">Saldo</th>
                                <th class="px-3 py-3 text-right">Nilai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 bg-white">
                            @forelse ($rows as $row)
                                @php
                                    $expiry = $row->expiry_date?->toDateString();
                                    $dayDiff = $expiry ? Carbon::parse($expiry)->startOfDay()->diffInDays(now()->startOfDay(), false) : null;
                                    $countdown = $dayDiff === null ? '-' : ($dayDiff > 0 ? 'Lewat '.$dayDiff.' hari' : ($dayDiff === 0 ? 'Hari ini' : abs($dayDiff).' hari lagi'));
                                @endphp
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-900">{{ $row->medicine?->name ?: '-' }}</p>
                                        <p class="mt-1 text-[0.66rem] text-slate-400">{{ $row->medicine?->code ?: '-' }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->medicine?->principal?->name ?: '-' }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->batch_number ?: '-' }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->expiry_date?->translatedFormat('d M Y') ?? '-' }}</td>
                                    <td class="px-3 py-3 font-semibold {{ $dayDiff !== null && $dayDiff >= 0 ? 'text-rose-700' : 'text-amber-700' }}">{{ $countdown }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-slate-900">{{ number_format((float) $row->quantity_balance, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-amber-700">Rp {{ number_format((float) $row->quantity_balance * (float) $row->purchase_price, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-14 text-center">
                                        <div class="empty-title">Tidak ada batch pada rentang expired ini</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($mode === 'payable')
                    <table class="min-w-[1080px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                        <thead class="bg-slate-50/90">
                            <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <th class="px-4 py-3">No Faktur</th>
                                <th class="px-3 py-3">Supplier</th>
                                <th class="px-3 py-3">Tanggal Faktur</th>
                                <th class="px-3 py-3">Jatuh Tempo</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-3 py-3 text-right">Grand Total</th>
                                <th class="px-3 py-3 text-right">Dibayar</th>
                                <th class="px-3 py-3 text-right">Sisa Hutang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 bg-white">
                            @forelse ($rows as $row)
                                @php
                                    $isOverdue = $row->due_date && $row->due_date->isPast() && (float) $row->outstanding_amount > 0.001;
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ $row->invoice_number }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->supplier?->name ?: '-' }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->invoice_date?->translatedFormat('d M Y') ?? '-' }}</td>
                                    <td class="px-3 py-3 font-semibold {{ $isOverdue ? 'text-rose-700' : 'text-slate-700' }}">{{ $row->due_date?->translatedFormat('d M Y') ?? '-' }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ ucfirst($row->payment_status) }}</td>
                                    <td class="px-3 py-3 text-right text-slate-700">Rp {{ number_format((float) $row->grand_total, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-right text-slate-700">Rp {{ number_format((float) $row->paid_amount, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-amber-700">Rp {{ number_format((float) $row->outstanding_amount, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-14 text-center">
                                        <div class="empty-title">Tidak ada hutang supplier pada periode ini</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($mode === 'receivable')
                    <table class="min-w-[940px] w-full divide-y divide-slate-200/80 text-[0.75rem]">
                        <thead class="bg-slate-50/90">
                            <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <th class="px-4 py-3">No Jual</th>
                                <th class="px-3 py-3">Tanggal</th>
                                <th class="px-3 py-3">Pelanggan</th>
                                <th class="px-3 py-3">Metode</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-2 py-3 whitespace-nowrap">Tanggal Pelunasan</th>
                                <th class="px-2 py-3 text-right whitespace-nowrap">Total Faktur</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 bg-white">
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ $row->sale_number }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->sale_date?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->customer_name ?: '-' }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->payment_method_label }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->settlement_status_label }}</td>
                                    <td class="px-2 py-3 text-slate-700 whitespace-nowrap">{{ $row->settlement_date_label }}</td>
                                    <td class="px-2 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">Rp {{ number_format((float) $row->grand_total, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-14 text-center">
                                        <div class="empty-title">Tidak ada faktur kredit pada periode ini</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($mode === 'profit_loss')
                    <table class="min-w-[1120px] w-full divide-y divide-slate-200/80 text-[0.76rem]">
                        <thead class="bg-slate-50/90">
                            <tr class="text-left text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <th class="px-4 py-3">No Jual</th>
                                <th class="px-3 py-3">Tanggal</th>
                                <th class="px-3 py-3">Pelanggan</th>
                                <th class="px-3 py-3 text-right">Penjualan Kotor</th>
                                <th class="px-3 py-3 text-right">Retur</th>
                                <th class="px-3 py-3 text-right">Penjualan Bersih</th>
                                <th class="px-3 py-3 text-right">HPP Bersih</th>
                                <th class="px-3 py-3 text-right">Laba Kotor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 bg-white">
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ $row->sale_number }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ Carbon::parse($row->sale_date)->translatedFormat('d M Y H:i') }}</td>
                                    <td class="px-3 py-3 text-slate-700">{{ $row->customer_name ?: '-' }}</td>
                                    <td class="px-3 py-3 text-right text-slate-700">Rp {{ number_format((float) $row->gross_sales, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-right text-rose-700">Rp {{ number_format((float) $row->sales_returns, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-sky-700">Rp {{ number_format((float) $row->net_sales, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-right text-amber-700">Rp {{ number_format((float) $row->net_cogs, 0, ',', '.') }}</td>
                                    <td class="px-3 py-3 text-right font-semibold {{ (float) $row->gross_profit >= 0 ? 'text-violet-700' : 'text-rose-700' }}">Rp {{ number_format((float) $row->gross_profit, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-14 text-center">
                                        <div class="empty-title">Belum ada data laba rugi pada periode ini</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if (! empty($summary))
                            <tfoot class="bg-slate-50/80 text-[0.74rem] font-semibold">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-right text-slate-500">Total Periode</td>
                                    <td class="px-3 py-3 text-right text-slate-700">{{ $stats[0]['value'] }}</td>
                                    <td class="px-3 py-3 text-right text-rose-700">{{ $stats[1]['value'] }}</td>
                                    <td class="px-3 py-3 text-right text-sky-700">{{ $stats[2]['value'] }}</td>
                                    <td class="px-3 py-3 text-right text-amber-700">{{ $stats[3]['value'] }}</td>
                                    <td class="px-3 py-3 text-right text-violet-700">{{ $stats[4]['value'] }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                @endif
            </div>

            @if ($rows->hasPages())
                <div class="border-t border-slate-200/80 px-5 py-4">
                    {{ $rows->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
