<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Faktur Penjualan {{ $sale->sale_number }}</title>
    <style>
        @page {
            margin: 4px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 4px;
            color: #0f172a;
            font-family: "Times New Roman", serif;
            font-size: 12.6px;
            line-height: 1.35;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .header td {
            vertical-align: top;
        }

        .brand-title {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            margin: 0 0 4px;
        }

        .brand-meta {
            margin: 1px 0;
            font-size: 10.8px;
            color: #334155;
        }

        .document-title {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-align: right;
            text-transform: uppercase;
            margin: 0 0 10px;
        }

        .document-number {
            font-size: 12.6px;
            font-weight: 700;
            text-align: right;
            margin: 0;
        }

        .separator {
            border-top: 2px solid #64748b;
            margin: 8px 0 10px;
        }

        table.meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            border: 1px solid #cbd5e1;
        }

        table.meta td {
            padding: 4px 6px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            font-size: 11px;
        }

        table.meta tr:last-child td {
            border-bottom: none;
        }

        .meta-label {
            width: 20%;
            font-weight: 700;
        }

        .meta-separator {
            width: 2%;
            text-align: center;
        }

        .meta-value {
            width: 28%;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        table.items th,
        table.items td {
            border: 1px solid #dbe4ee;
            padding: 5px 6px;
        }

        table.items th {
            background: #f1f5f9;
            font-size: 10.8px;
            font-weight: 700;
            text-transform: uppercase;
        }

        table.items td {
            font-size: 11px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .summary {
            width: 44%;
            margin-left: auto;
            margin-top: 6px;
            border-collapse: collapse;
        }

        .summary td {
            padding: 2px 0;
            font-size: 11px;
        }

        .summary-label {
            font-weight: 700;
            text-align: right;
            padding-right: 18px;
        }

        .summary-value {
            width: 36%;
            text-align: right;
            font-weight: 700;
        }

        .spellout-box {
            margin-top: 10px;
            border: 1px solid #cbd5e1;
            min-height: 34px;
            padding: 6px 8px;
            width: 62%;
        }

        .spellout-title {
            font-size: 10.8px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .notes {
            margin-top: 8px;
            font-size: 11px;
            color: #334155;
        }

        .notes p {
            margin: 3px 0;
        }

        .footer-grid {
            width: 100%;
            margin-top: 24px;
            border-collapse: collapse;
        }

        .footer-grid td {
            width: 50%;
            vertical-align: top;
        }

        .sign-title {
            text-align: center;
            margin-bottom: 48px;
        }

        .sign-name {
            text-align: center;
            font-weight: 700;
        }

        .muted {
            color: #475569;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td width="60%">
                <h1 class="brand-title">{{ $profile->name }}</h1>
                <p class="brand-meta">SIA : {{ $profile->license_number ?: '-' }}</p>
                <p class="brand-meta">{{ $pharmacyAddressLine }}</p>
                <p class="brand-meta">Telp : {{ $profile->phone ?: '-' }}</p>
            </td>
            <td width="40%">
                <p class="document-title">Faktur Penjualan</p>
                <p class="document-number">{{ $sale->sale_number }}</p>
            </td>
        </tr>
    </table>

    <div class="separator"></div>

    <table class="meta">
        <tr>
            <td class="meta-label">Terima Dari</td>
            <td class="meta-separator">:</td>
            <td class="meta-value">{{ $sale->customer_name ?: '-' }}</td>
            <td class="meta-label">Tanggal Cetak</td>
            <td class="meta-separator">:</td>
            <td class="meta-value">{{ $printedAt->translatedFormat('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td class="meta-label">Alamat</td>
            <td class="meta-separator">:</td>
            <td class="meta-value">{{ $customerAddress }}</td>
            <td class="meta-label">Tanggal Faktur</td>
            <td class="meta-separator">:</td>
            <td class="meta-value">{{ $sale->sale_date?->translatedFormat('d/m/Y') ?? '-' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Metode</td>
            <td class="meta-separator">:</td>
            <td class="meta-value">{{ $paymentMethodLabel }}</td>
            <td class="meta-label">Jatuh Tempo</td>
            <td class="meta-separator">:</td>
            <td class="meta-value">-</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="33%">Nama Barang</th>
                <th width="13%">No Batch</th>
                <th width="9%">Qty</th>
                <th width="9%">Satuan</th>
                <th width="12%">Harga</th>
                <th width="8%">Disc</th>
                <th width="11%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($groupedItems as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item['medicine_name'] }}</td>
                    <td>{{ $item['batch_number'] ?: '-' }}</td>
                    <td class="text-center">{{ number_format((float) $item['quantity'], 0, ',', '.') }}</td>
                    <td class="text-center">{{ $item['unit_name'] }}</td>
                    <td class="text-right">Rp {{ number_format((float) $item['unit_price'], 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format((float) $item['discount_amount'], 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format((float) $item['line_total'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td class="summary-label">TOTAL ITEM</td>
            <td class="summary-value">Rp {{ number_format((float) $sale->subtotal, 0, ',', '.') }}</td>
        </tr>
        @if ((float) $sale->social_amount > 0)
            <tr>
                <td class="summary-label">SOSIAL</td>
                <td class="summary-value">Rp {{ number_format((float) $sale->social_amount, 0, ',', '.') }}</td>
            </tr>
        @endif
        <tr>
            <td class="summary-label">PPN (0%)</td>
            <td class="summary-value">Rp {{ number_format((float) $sale->tax_amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="summary-label">GRAND TOTAL</td>
            <td class="summary-value">Rp {{ number_format((float) $sale->grand_total, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="summary-label">BAYAR</td>
            <td class="summary-value">Rp {{ number_format((float) $sale->paid_amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="summary-label">KEMBALI</td>
            <td class="summary-value">Rp {{ number_format((float) $sale->change_amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="summary-label">Tanggal Bayar</td>
            <td class="summary-value">-</td>
        </tr>
        <tr>
            <td class="summary-label">Status</td>
            <td class="summary-value">{{ strtoupper($paymentStatus) }}</td>
        </tr>
    </table>

    <div class="spellout-box">
        <div class="spellout-title">Terbilang:</div>
        <div>*** {{ $grandTotalWords }} ***</div>
    </div>

    <table class="footer-grid">
        <tr>
            <td>
                <div class="sign-title">Diketahui Oleh,</div>
                <div class="sign-name">{{ $profile->owner_name ?: '-' }}</div>
            </td>
            <td>
                <div class="sign-title">Dibayar Oleh,</div>
                <div class="sign-name">( {{ $sale->customer_name ?: '-' }} )</div>
            </td>
        </tr>
    </table>
</body>
</html>
