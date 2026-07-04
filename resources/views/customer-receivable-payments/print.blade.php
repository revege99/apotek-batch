<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bukti Penerimaan {{ $customer->name }}</title>
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

        .document-subtitle {
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

        .total-row td {
            font-weight: 700;
            background: #f1f5f9;
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
                <p class="document-title">Bukti Penerimaan</p>
                <p class="document-subtitle">{{ $customer->code ?: '-' }} Kas Penjualan</p>
            </td>
        </tr>
    </table>

    <div class="separator"></div>

    <table class="meta">
        <tr>
            <td class="meta-label" colspan="3">Terima Dari :</td>
            <td class="meta-label" colspan="3"></td>
        </tr>
        <tr>
            <td class="meta-label">Nama</td>
            <td class="meta-separator">:</td>
            <td class="meta-value">{{ $customer->name ?: '-' }}</td>
            <td class="meta-label">Tanggal Cetak</td>
            <td class="meta-separator">:</td>
            <td class="meta-value">{{ $printedAt->translatedFormat('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td class="meta-label">Alamat</td>
            <td class="meta-separator">:</td>
            <td class="meta-value">{{ $customer->address ?: '-' }}</td>
            <td class="meta-label">Periode</td>
            <td class="meta-separator">:</td>
            <td class="meta-value">
                {{ \Carbon\Carbon::parse($dateFrom)->translatedFormat('d/m/Y') }}
                s/d
                {{ \Carbon\Carbon::parse($dateTo)->translatedFormat('d/m/Y') }}
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th width="7%">No</th>
                <th width="26%">No Faktur</th>
                <th width="16%">Tanggal</th>
                <th width="16%">Jatuh Tempo</th>
                <th width="17%">Tgl Bayar</th>
                <th width="18%">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payments as $index => $payment)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $payment->sale?->sale_number ?: '-' }}</td>
                    <td class="text-center">{{ $payment->sale?->sale_date?->translatedFormat('d/m/Y') ?? '-' }}</td>
                    <td class="text-center">-</td>
                    <td class="text-center">{{ $payment->payment_date?->translatedFormat('d/m/Y') ?? '-' }}</td>
                    <td class="text-right">Rp {{ number_format((float) $payment->amount_paid, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Tidak ada pembayaran pada periode ini.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="5" class="text-right">TOTAL PEMBAYARAN</td>
                <td class="text-right">Rp {{ number_format($totalAmount, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <table class="footer-grid">
        <tr>
            <td>
                <div class="sign-title">Diketahui Oleh,</div>
                <div class="sign-name">{{ $profile->owner_name ?: '-' }}</div>
            </td>
            <td>
                <div class="sign-title">Dibayar Oleh,</div>
                <div class="sign-name">( {{ $customer->name ?: '-' }} )</div>
            </td>
        </tr>
    </table>
</body>
</html>
