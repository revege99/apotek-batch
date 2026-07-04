<?php

return [
    'brand' => [
        'name' => 'Apotik',
        'tagline' => 'Transaksi, stok batch, dan laporan operasional dalam satu panel.',
    ],

    'navigation' => [
        [
            'label' => 'Master Data',
            'icon' => 'archive',
            'summary' => 'Fondasi katalog obat, supplier, dan lokasi penyimpanan.',
            'children' => [
                ['label' => 'Data Obat', 'route' => 'master-data.data-obat', 'path' => 'master-data/data-obat'],
                ['label' => 'Kategori Obat', 'route' => 'master-data.kategori-obat', 'path' => 'master-data/kategori-obat'],
                ['label' => 'Satuan Obat', 'route' => 'master-data.satuan-obat', 'path' => 'master-data/satuan-obat'],
                ['label' => 'Supplier', 'route' => 'master-data.supplier', 'path' => 'master-data/supplier'],
                ['label' => 'Golongan Pelanggan', 'route' => 'master-data.golongan-pelanggan', 'path' => 'master-data/golongan-pelanggan'],
                ['label' => 'Pelanggan', 'route' => 'master-data.pelanggan', 'path' => 'master-data/pelanggan'],
                ['label' => 'Industri Farmasi', 'route' => 'master-data.pabrik-principal', 'path' => 'master-data/pabrik-principal'],
                ['label' => 'Lokasi Obat', 'route' => 'master-data.lokasi-obat', 'path' => 'master-data/lokasi-obat'],
                ['label' => 'Rak Obat', 'route' => 'master-data.rak-obat', 'path' => 'master-data/rak-obat'],
            ],
        ],
        [
            'label' => 'Pembelian',
            'icon' => 'cart',
            'summary' => 'Siklus faktur, hutang supplier, dan retur pembelian.',
            'children' => [
                ['label' => 'Input Faktur Pembelian', 'route' => 'pembelian.input-faktur-pembelian', 'path' => 'pembelian/input-faktur-pembelian'],
                ['label' => 'Data Pembelian', 'route' => 'pembelian.data-pembelian', 'path' => 'pembelian/data-pembelian'],
                ['label' => 'Hutang Supplier', 'route' => 'pembelian.hutang-supplier', 'path' => 'pembelian/hutang-supplier'],
                ['label' => 'Retur Pembelian', 'route' => 'pembelian.retur-pembelian', 'path' => 'pembelian/retur-pembelian'],
                ['label' => 'Tukar Barang', 'route' => 'pembelian.tukar-barang', 'path' => 'pembelian/tukar-barang'],
                ['label' => 'Realisasi Pengganti Retur', 'route' => 'pembelian.realisasi-pengganti-retur', 'path' => 'pembelian/realisasi-pengganti-retur'],
                ['label' => 'Realisasi Tukar Barang', 'route' => 'pembelian.realisasi-tukar-barang', 'path' => 'pembelian/realisasi-tukar-barang'],
            ],
        ],
        [
            'label' => 'Penjualan',
            'icon' => 'receipt',
            'summary' => 'Kasir penjualan harian, histori transaksi, dan retur.',
            'children' => [
                ['label' => 'Kasir Penjualan', 'route' => 'penjualan.kasir-penjualan', 'path' => 'penjualan/kasir-penjualan'],
                ['label' => 'Data Penjualan', 'route' => 'penjualan.data-penjualan', 'path' => 'penjualan/data-penjualan'],
                ['label' => 'Retur Penjualan', 'route' => 'penjualan.retur-penjualan', 'path' => 'penjualan/retur-penjualan'],
            ],
        ],
        [
            'label' => 'Stok & Batch',
            'icon' => 'boxes',
            'summary' => 'Pantau stok real-time, batch, opname, dan risiko expired.',
            'children' => [
                ['label' => 'Stok Obat', 'route' => 'stok-batch.stok-obat', 'path' => 'stok-batch/stok-obat'],
                ['label' => 'Stok per Batch', 'route' => 'stok-batch.stok-per-batch', 'path' => 'stok-batch/stok-per-batch'],
                ['label' => 'Stok Opname', 'route' => 'stok-batch.stok-opname', 'path' => 'stok-batch/stok-opname'],
                ['label' => 'Penyesuaian Stok', 'route' => 'stok-batch.penyesuaian-stok', 'path' => 'stok-batch/penyesuaian-stok'],
            ],
        ],
        [
            'label' => 'Keuangan',
            'icon' => 'wallet',
            'summary' => 'Piutang, pembayaran, dan tindak lanjut saldo operasional harian.',
            'children' => [
                ['label' => 'Piutang Pelanggan', 'route' => 'keuangan.piutang-pelanggan', 'path' => 'keuangan/piutang-pelanggan'],
                ['label' => 'Riwayat Pembayaran', 'route' => 'keuangan.riwayat-pembayaran', 'path' => 'keuangan/riwayat-pembayaran'],
                ['label' => 'Riwayat Tagihan Internal', 'route' => 'keuangan.riwayat-tagihan-internal', 'path' => 'keuangan/riwayat-tagihan-internal'],
                ['label' => 'Pembayaran Hutang', 'route' => 'keuangan.pembayaran-hutang', 'path' => 'keuangan/pembayaran-hutang'],
            ],
        ],
        [
            'label' => 'Laporan',
            'icon' => 'document',
            'summary' => 'Rekap pembelian, penjualan, stok, piutang, hutang, dan laba rugi.',
            'children' => [
                ['label' => 'Laporan Pembelian', 'route' => 'laporan.laporan-pembelian', 'path' => 'laporan/laporan-pembelian'],
                ['label' => 'Laporan Penjualan', 'route' => 'laporan.laporan-penjualan', 'path' => 'laporan/laporan-penjualan'],
                ['label' => 'Laporan Penerimaan Kas', 'route' => 'laporan.laporan-penerimaan-kas', 'path' => 'laporan/laporan-penerimaan-kas'],
                ['label' => 'Laporan Hilang Biasa', 'route' => 'laporan.laporan-hilang-biasa', 'path' => 'laporan/laporan-hilang-biasa'],
                ['label' => 'Laporan Stok', 'route' => 'laporan.laporan-stok', 'path' => 'laporan/laporan-stok'],
                ['label' => 'Laporan Expired', 'route' => 'laporan.laporan-expired', 'path' => 'laporan/laporan-expired'],
                ['label' => 'Laporan Hutang', 'route' => 'laporan.laporan-hutang', 'path' => 'laporan/laporan-hutang'],
                ['label' => 'Laporan Piutang', 'route' => 'laporan.laporan-piutang', 'path' => 'laporan/laporan-piutang'],
                ['label' => 'Laporan Laba Rugi', 'route' => 'laporan.laporan-laba-rugi', 'path' => 'laporan/laporan-laba-rugi'],
            ],
        ],
        [
            'label' => 'Setup Saldo Awal',
            'icon' => 'cog',
            'summary' => 'Area untuk menyiapkan saldo awal saat go-live aplikasi.',
            'children' => [
                ['label' => 'Saldo Awal Stok', 'route' => 'setup-saldo-awal.stok', 'path' => 'setup-saldo-awal/stok'],
                ['label' => 'Piutang Awal', 'route' => 'setup-saldo-awal.piutang', 'path' => 'setup-saldo-awal/piutang'],
                ['label' => 'Hutang Awal', 'route' => 'setup-saldo-awal.hutang', 'path' => 'setup-saldo-awal/hutang'],
                ['label' => 'Kas Awal', 'route' => 'setup-saldo-awal.kas', 'path' => 'setup-saldo-awal/kas'],
            ],
        ],
        [
            'label' => 'Pengaturan',
            'icon' => 'cog',
            'summary' => 'Identitas apotik, akses user, pajak, dan toleransi sistem.',
            'children' => [
                ['label' => 'Profil Apotik', 'route' => 'pengaturan.profil-apotik', 'path' => 'pengaturan/profil-apotik'],
                ['label' => 'Lisensi', 'route' => 'pengaturan.lisensi', 'path' => 'pengaturan/lisensi'],
                ['label' => 'Manajemen Lisensi', 'route' => 'pengaturan.manajemen-lisensi', 'path' => 'pengaturan/manajemen-lisensi', 'superadmin_only' => true],
                ['label' => 'Reset Data Produksi', 'route' => 'pengaturan.reset-data-produksi', 'path' => 'pengaturan/reset-data-produksi', 'superadmin_only' => true],
                ['label' => 'User', 'route' => 'pengaturan.user', 'path' => 'pengaturan/user', 'superadmin_only' => true],
                ['label' => 'Hak Akses', 'route' => 'pengaturan.hak-akses', 'path' => 'pengaturan/hak-akses', 'superadmin_only' => true],
                ['label' => 'Setting Pajak / PPN', 'route' => 'pengaturan.setting-pajak-ppn', 'path' => 'pengaturan/setting-pajak-ppn'],
                ['label' => 'Setting Toleransi Selisih', 'route' => 'pengaturan.setting-toleransi-selisih', 'path' => 'pengaturan/setting-toleransi-selisih'],
            ],
        ],
    ],
];
