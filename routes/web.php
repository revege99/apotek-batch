<?php

use App\Http\Controllers\MedicineController;
use App\Http\Controllers\MedicineCategoryController;
use App\Http\Controllers\MedicineUnitController;
use App\Http\Controllers\CustomerReceivableController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\LicenseManagementController;
use App\Http\Controllers\PrincipalController;
use App\Http\Controllers\PharmacyProfileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductionOpeningSetupController;
use App\Http\Controllers\ProductionDataResetController;
use App\Http\Controllers\PurchaseExchangeController;
use App\Http\Controllers\PurchaseExchangeReplacementController;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\PurchaseReturnReplacementController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\StorageRackController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StorageLocationController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\InternalBillingController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\UserAccessController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

$modulePages = collect(config('apotik.navigation'))
    ->filter(fn (array $item): bool => isset($item['children']))
    ->flatMap(function (array $group) {
        return collect($group['children'])
            ->reject(fn (array $child): bool => in_array($child['route'], [
                'master-data.data-obat',
                'master-data.kategori-obat',
                'master-data.satuan-obat',
                'master-data.supplier',
                'master-data.golongan-pelanggan',
                'master-data.pelanggan',
                'master-data.pabrik-principal',
                'master-data.lokasi-obat',
                'master-data.rak-obat',
                'pengaturan.profil-apotik',
                'pengaturan.lisensi',
                'pengaturan.manajemen-lisensi',
                'pengaturan.reset-data-produksi',
                'pengaturan.user',
                'pengaturan.hak-akses',
                'setup-saldo-awal.stok',
                'setup-saldo-awal.piutang',
                'setup-saldo-awal.hutang',
                'setup-saldo-awal.kas',
                'pembelian.input-faktur-pembelian',
                'pembelian.data-pembelian',
                'pembelian.retur-pembelian',
                'pembelian.tukar-barang',
                'pembelian.realisasi-pengganti-retur',
                'pembelian.realisasi-tukar-barang',
                'penjualan.kasir-penjualan',
                'penjualan.data-penjualan',
                'penjualan.retur-penjualan',
                'stok-batch.stok-obat',
                'stok-batch.stok-per-batch',
                'stok-batch.stok-opname',
                'stok-batch.penyesuaian-stok',
                'keuangan.piutang-pelanggan',
                'keuangan.riwayat-pembayaran',
                'keuangan.riwayat-tagihan-internal',
                'laporan.laporan-pembelian',
                'laporan.laporan-penjualan',
                'laporan.laporan-penerimaan-kas',
                'laporan.laporan-hilang-biasa',
                'laporan.laporan-stok',
                'laporan.laporan-expired',
                'laporan.laporan-hutang',
                'laporan.laporan-piutang',
                'laporan.laporan-laba-rugi',
            ], true))
            ->map(function (array $child) use ($group) {
            return [
                ...$child,
                'section' => $group['label'],
                'section_icon' => $group['icon'],
                'section_summary' => $group['summary'],
            ];
        });
    })
    ->all();

Route::middleware(['auth', 'verified', 'menu.access'])->group(function () use ($modulePages) {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/master-data/data-obat', [MedicineController::class, 'index'])->name('master-data.data-obat');
    Route::get('/master-data/data-obat/create', [MedicineController::class, 'create'])->name('master-data.data-obat.create');
    Route::post('/master-data/data-obat', [MedicineController::class, 'store'])->name('master-data.data-obat.store');
    Route::get('/master-data/data-obat/{medicine}/edit', [MedicineController::class, 'edit'])->name('master-data.data-obat.edit');
    Route::match(['put', 'patch'], '/master-data/data-obat/{medicine}', [MedicineController::class, 'update'])->name('master-data.data-obat.update');
    Route::delete('/master-data/data-obat/{medicine}', [MedicineController::class, 'destroy'])->name('master-data.data-obat.destroy');
    Route::get('/master-data/kategori-obat', [MedicineCategoryController::class, 'index'])->name('master-data.kategori-obat');
    Route::post('/master-data/kategori-obat', [MedicineCategoryController::class, 'store'])->name('master-data.kategori-obat.store');
    Route::patch('/master-data/kategori-obat/{medicineCategory}', [MedicineCategoryController::class, 'update'])->name('master-data.kategori-obat.update');
    Route::delete('/master-data/kategori-obat/{medicineCategory}', [MedicineCategoryController::class, 'destroy'])->name('master-data.kategori-obat.destroy');
    Route::get('/master-data/satuan-obat', [MedicineUnitController::class, 'index'])->name('master-data.satuan-obat');
    Route::post('/master-data/satuan-obat', [MedicineUnitController::class, 'store'])->name('master-data.satuan-obat.store');
    Route::patch('/master-data/satuan-obat/{medicineUnit}', [MedicineUnitController::class, 'update'])->name('master-data.satuan-obat.update');
    Route::delete('/master-data/satuan-obat/{medicineUnit}', [MedicineUnitController::class, 'destroy'])->name('master-data.satuan-obat.destroy');
    Route::get('/master-data/supplier', [SupplierController::class, 'index'])->name('master-data.supplier');
    Route::post('/master-data/supplier', [SupplierController::class, 'store'])->name('master-data.supplier.store');
    Route::patch('/master-data/supplier/{supplier}', [SupplierController::class, 'update'])->name('master-data.supplier.update');
    Route::delete('/master-data/supplier/{supplier}', [SupplierController::class, 'destroy'])->name('master-data.supplier.destroy');
    Route::get('/master-data/golongan-pelanggan', [CustomerGroupController::class, 'index'])->name('master-data.golongan-pelanggan');
    Route::post('/master-data/golongan-pelanggan', [CustomerGroupController::class, 'store'])->name('master-data.golongan-pelanggan.store');
    Route::patch('/master-data/golongan-pelanggan/{customerGroup}', [CustomerGroupController::class, 'update'])->name('master-data.golongan-pelanggan.update');
    Route::delete('/master-data/golongan-pelanggan/{customerGroup}', [CustomerGroupController::class, 'destroy'])->name('master-data.golongan-pelanggan.destroy');
    Route::get('/master-data/pelanggan', [CustomerController::class, 'index'])->name('master-data.pelanggan');
    Route::post('/master-data/pelanggan', [CustomerController::class, 'store'])->name('master-data.pelanggan.store');
    Route::patch('/master-data/pelanggan/{customer}', [CustomerController::class, 'update'])->name('master-data.pelanggan.update');
    Route::delete('/master-data/pelanggan/{customer}', [CustomerController::class, 'destroy'])->name('master-data.pelanggan.destroy');
    Route::get('/master-data/pabrik-principal', [PrincipalController::class, 'index'])->name('master-data.pabrik-principal');
    Route::post('/master-data/pabrik-principal', [PrincipalController::class, 'store'])->name('master-data.pabrik-principal.store');
    Route::patch('/master-data/pabrik-principal/{principal}', [PrincipalController::class, 'update'])->name('master-data.pabrik-principal.update');
    Route::delete('/master-data/pabrik-principal/{principal}', [PrincipalController::class, 'destroy'])->name('master-data.pabrik-principal.destroy');
    Route::get('/master-data/lokasi-obat', [StorageLocationController::class, 'index'])->name('master-data.lokasi-obat');
    Route::post('/master-data/lokasi-obat', [StorageLocationController::class, 'store'])->name('master-data.lokasi-obat.store');
    Route::patch('/master-data/lokasi-obat/{storageLocation}', [StorageLocationController::class, 'update'])->name('master-data.lokasi-obat.update');
    Route::delete('/master-data/lokasi-obat/{storageLocation}', [StorageLocationController::class, 'destroy'])->name('master-data.lokasi-obat.destroy');
    Route::get('/master-data/rak-obat', [StorageRackController::class, 'index'])->name('master-data.rak-obat');
    Route::post('/master-data/rak-obat', [StorageRackController::class, 'store'])->name('master-data.rak-obat.store');
    Route::patch('/master-data/rak-obat/{storageRack}', [StorageRackController::class, 'update'])->name('master-data.rak-obat.update');
    Route::delete('/master-data/rak-obat/{storageRack}', [StorageRackController::class, 'destroy'])->name('master-data.rak-obat.destroy');
    Route::get('/pengaturan/profil-apotik', [PharmacyProfileController::class, 'edit'])->name('pengaturan.profil-apotik');
    Route::patch('/pengaturan/profil-apotik', [PharmacyProfileController::class, 'update'])->name('pengaturan.profil-apotik.update');
    Route::get('/pengaturan/lisensi', [LicenseController::class, 'index'])->name('pengaturan.lisensi');
    Route::get('/pengaturan/lisensi/qris-image', [LicenseController::class, 'qrisImage'])->name('pengaturan.lisensi.qris-image');
    Route::post('/pengaturan/lisensi/perpanjang', [LicenseController::class, 'storeRenewalRequest'])->name('pengaturan.lisensi.renewal-request');
    Route::post('/pengaturan/lisensi/aktivasi', [LicenseController::class, 'activate'])->name('pengaturan.lisensi.activate');
    Route::middleware('role:superadmin')->group(function () {
        Route::get('/pengaturan/user', [UserAccessController::class, 'index'])->name('pengaturan.user');
        Route::post('/pengaturan/user', [UserAccessController::class, 'store'])->name('pengaturan.user.store');
        Route::patch('/pengaturan/user/{user}', [UserAccessController::class, 'update'])->name('pengaturan.user.update');
        Route::delete('/pengaturan/user/{user}', [UserAccessController::class, 'destroy'])->name('pengaturan.user.destroy');
        Route::get('/pengaturan/user-hak-akses', fn () => redirect()->route('pengaturan.user'))->name('pengaturan.user-hak-akses');
        Route::get('/pengaturan/hak-akses', [RolePermissionController::class, 'index'])->name('pengaturan.hak-akses');
        Route::patch('/pengaturan/hak-akses/{user}', [RolePermissionController::class, 'update'])->name('pengaturan.hak-akses.update');
    });
    Route::middleware('license.active')->group(function () {
        Route::get('/pembelian/input-faktur-pembelian', [PurchaseInvoiceController::class, 'create'])->name('pembelian.input-faktur-pembelian');
        Route::post('/pembelian/input-faktur-pembelian', [PurchaseInvoiceController::class, 'store'])->name('pembelian.input-faktur-pembelian.store');
    });
    Route::get('/pembelian/data-pembelian', [PurchaseInvoiceController::class, 'index'])->name('pembelian.data-pembelian');
    Route::get('/pembelian/data-pembelian/{purchaseInvoice}/edit', [PurchaseInvoiceController::class, 'edit'])->name('pembelian.data-pembelian.edit');
    Route::patch('/pembelian/data-pembelian/{purchaseInvoice}', [PurchaseInvoiceController::class, 'update'])->name('pembelian.data-pembelian.update');
    Route::delete('/pembelian/data-pembelian/{purchaseInvoice}', [PurchaseInvoiceController::class, 'destroy'])->name('pembelian.data-pembelian.destroy');
    Route::middleware('license.active')->group(function () {
        Route::get('/pembelian/retur-pembelian', [PurchaseReturnController::class, 'index'])->name('pembelian.retur-pembelian');
        Route::post('/pembelian/retur-pembelian', [PurchaseReturnController::class, 'store'])->name('pembelian.retur-pembelian.store');
        Route::get('/pembelian/retur-pembelian/riwayat', [PurchaseReturnController::class, 'history'])->name('pembelian.riwayat-retur-pembelian');
        Route::delete('/pembelian/retur-pembelian/{purchaseReturn}', [PurchaseReturnController::class, 'destroy'])->name('pembelian.retur-pembelian.destroy');
        Route::get('/pembelian/tukar-barang', [PurchaseExchangeController::class, 'index'])->name('pembelian.tukar-barang');
        Route::post('/pembelian/tukar-barang', [PurchaseExchangeController::class, 'store'])->name('pembelian.tukar-barang.store');
        Route::get('/pembelian/tukar-barang/riwayat', [PurchaseExchangeController::class, 'history'])->name('pembelian.riwayat-tukar-barang');
        Route::delete('/pembelian/tukar-barang/{purchaseExchange}', [PurchaseExchangeController::class, 'destroy'])->name('pembelian.tukar-barang.destroy');
        Route::get('/pembelian/realisasi-pengganti-retur', [PurchaseReturnReplacementController::class, 'index'])->name('pembelian.realisasi-pengganti-retur');
        Route::post('/pembelian/realisasi-pengganti-retur', [PurchaseReturnReplacementController::class, 'store'])->name('pembelian.realisasi-pengganti-retur.store');
        Route::get('/pembelian/realisasi-pengganti-retur/riwayat', [PurchaseReturnReplacementController::class, 'history'])->name('pembelian.riwayat-realisasi-pengganti-retur');
        Route::delete('/pembelian/realisasi-pengganti-retur/{purchaseReturnReplacement}', [PurchaseReturnReplacementController::class, 'destroy'])->name('pembelian.realisasi-pengganti-retur.destroy');
        Route::get('/pembelian/realisasi-tukar-barang', [PurchaseExchangeReplacementController::class, 'index'])->name('pembelian.realisasi-tukar-barang');
        Route::post('/pembelian/realisasi-tukar-barang', [PurchaseExchangeReplacementController::class, 'store'])->name('pembelian.realisasi-tukar-barang.store');
        Route::get('/pembelian/realisasi-tukar-barang/riwayat', [PurchaseExchangeReplacementController::class, 'history'])->name('pembelian.riwayat-realisasi-tukar-barang');
        Route::delete('/pembelian/realisasi-tukar-barang/{purchaseExchangeReplacement}', [PurchaseExchangeReplacementController::class, 'destroy'])->name('pembelian.realisasi-tukar-barang.destroy');
        Route::get('/penjualan/kasir-penjualan', [SaleController::class, 'create'])->name('penjualan.kasir-penjualan');
        Route::post('/penjualan/kasir-penjualan', [SaleController::class, 'store'])->name('penjualan.kasir-penjualan.store');
    });
    Route::get('/penjualan/data-penjualan', [SaleController::class, 'index'])->name('penjualan.data-penjualan');
    Route::get('/penjualan/data-penjualan/{sale}/edit', [SaleController::class, 'edit'])->name('penjualan.data-penjualan.edit');
    Route::match(['put', 'patch'], '/penjualan/data-penjualan/{sale}', [SaleController::class, 'update'])->name('penjualan.data-penjualan.update');
    Route::get('/penjualan/data-penjualan/{sale}/print', [SaleController::class, 'print'])->name('penjualan.data-penjualan.print');
    Route::delete('/penjualan/data-penjualan/{sale}', [SaleController::class, 'destroy'])->name('penjualan.data-penjualan.destroy');
    Route::middleware('license.active')->group(function () {
        Route::get('/penjualan/retur-penjualan', [SaleReturnController::class, 'index'])->name('penjualan.retur-penjualan');
        Route::post('/penjualan/retur-penjualan', [SaleReturnController::class, 'store'])->name('penjualan.retur-penjualan.store');
        Route::get('/penjualan/retur-penjualan/riwayat', [SaleReturnController::class, 'history'])->name('penjualan.riwayat-retur-penjualan');
        Route::delete('/penjualan/retur-penjualan/{saleReturn}', [SaleReturnController::class, 'destroy'])->name('penjualan.retur-penjualan.destroy');
    });
    Route::get('/stok-batch/stok-obat', [StockController::class, 'medicineIndex'])->name('stok-batch.stok-obat');
    Route::get('/stok-batch/stok-per-batch', [StockController::class, 'batchIndex'])->name('stok-batch.stok-per-batch');
    Route::get('/stok-batch/stok-opname', [StockController::class, 'opnameIndex'])->name('stok-batch.stok-opname');
    Route::get('/stok-batch/stok-opname/draft', [StockController::class, 'opnameDraftIndex'])->name('stok-batch.stok-opname.draft');
    Route::get('/stok-batch/stok-opname/{stockOpname}', [StockController::class, 'opnameShow'])->name('stok-batch.stok-opname.show');
    Route::get('/stok-batch/penyesuaian-stok', [StockController::class, 'adjustmentIndex'])->name('stok-batch.penyesuaian-stok');
    Route::get('/stok-batch/penyesuaian-stok/dokumen/{stockOpname}', [StockController::class, 'adjustmentDocumentShow'])->name('stok-batch.penyesuaian-stok.dokumen');
    Route::post('/stok-batch/penyesuaian-stok/dokumen/{stockOpname}/proses', [StockController::class, 'adjustmentDocumentProcess'])->name('stok-batch.penyesuaian-stok.dokumen.process');
    Route::get('/stok-batch/penyesuaian-stok/{stockOpnameItem}/tindak-lanjut', [StockController::class, 'adjustmentFollowUpCreate'])->name('stok-batch.penyesuaian-stok.follow-up');
    Route::post('/stok-batch/stok-opname', [StockController::class, 'opnameStore'])->name('stok-batch.stok-opname.store');
    Route::post('/stok-batch/penyesuaian-stok/{stockOpnameItem}/tindak-lanjut', [StockController::class, 'adjustmentFollowUpStore'])->name('stok-batch.penyesuaian-stok.follow-up.store');
    Route::post('/stok-batch/penyesuaian-stok/{stockOpnameItem}/proses', [StockController::class, 'adjustmentFollowUpProcess'])->name('stok-batch.penyesuaian-stok.follow-up.process');
    Route::post('/stok-batch/penyesuaian-stok/{stockOpnameItem}/batalkan', [StockController::class, 'adjustmentFollowUpCancel'])->name('stok-batch.penyesuaian-stok.follow-up.cancel');
    Route::post('/stok-batch/stok-opname/{stockOpname}/approve', [StockController::class, 'opnameApprove'])->name('stok-batch.stok-opname.approve');
    Route::delete('/stok-batch/stok-opname/{stockOpname}', [StockController::class, 'opnameDestroy'])->name('stok-batch.stok-opname.destroy');
    Route::post('/stok-batch/penyesuaian-stok/ganti-uang', [StockController::class, 'adjustmentRecoveryStore'])->name('stok-batch.penyesuaian-stok.ganti-uang');
    Route::get('/keuangan/piutang-pelanggan', [CustomerReceivableController::class, 'index'])->name('keuangan.piutang-pelanggan');
    Route::get('/keuangan/piutang-pelanggan/{customer}', [CustomerReceivableController::class, 'show'])->name('keuangan.piutang-pelanggan.show');
    Route::get('/keuangan/piutang-pelanggan/{customer}/print', [CustomerReceivableController::class, 'print'])->name('keuangan.piutang-pelanggan.print');
    Route::post('/keuangan/piutang-pelanggan/{sale}/bayar', [CustomerReceivableController::class, 'storePayment'])->name('keuangan.piutang-pelanggan.bayar');
    Route::get('/keuangan/riwayat-pembayaran', [CustomerReceivableController::class, 'history'])->name('keuangan.riwayat-pembayaran');
    Route::get('/keuangan/riwayat-pembayaran/{customer}', [CustomerReceivableController::class, 'historyShow'])->name('keuangan.riwayat-pembayaran.show');
    Route::get('/keuangan/riwayat-pembayaran/{customer}/print', [CustomerReceivableController::class, 'printPaymentHistory'])->name('keuangan.riwayat-pembayaran.print');
    Route::delete('/keuangan/riwayat-pembayaran/{customerPayment}', [CustomerReceivableController::class, 'destroyPayment'])->name('keuangan.riwayat-pembayaran.destroy');
    Route::get('/keuangan/riwayat-tagihan-internal', [InternalBillingController::class, 'index'])->name('keuangan.riwayat-tagihan-internal');
    Route::post('/keuangan/riwayat-tagihan-internal/dokumen/{stockOpname}/bayar', [InternalBillingController::class, 'storeDocumentPayment'])->name('keuangan.riwayat-tagihan-internal.bayar-dokumen');
    Route::post('/keuangan/riwayat-tagihan-internal/{stockAdjustmentRecovery}/bayar', [InternalBillingController::class, 'storePayment'])->name('keuangan.riwayat-tagihan-internal.bayar');
    Route::delete('/keuangan/riwayat-tagihan-internal/pembayaran/{stockAdjustmentRecoveryPayment}', [InternalBillingController::class, 'destroyPayment'])->name('keuangan.riwayat-tagihan-internal.destroy-payment');
    Route::get('/setup-saldo-awal/stok', [ProductionOpeningSetupController::class, 'stockIndex'])->name('setup-saldo-awal.stok');
    Route::get('/setup-saldo-awal/stok/riwayat', [ProductionOpeningSetupController::class, 'stockHistoryIndex'])->name('setup-saldo-awal.stok.riwayat');
    Route::post('/setup-saldo-awal/stok', [ProductionOpeningSetupController::class, 'storeOpeningStock'])->name('setup-saldo-awal.stok.store');
    Route::delete('/setup-saldo-awal/stok/{openingStockEntryItem}', [ProductionOpeningSetupController::class, 'destroyOpeningStock'])->name('setup-saldo-awal.stok.destroy');
    Route::get('/setup-saldo-awal/piutang', [ProductionOpeningSetupController::class, 'receivableIndex'])->name('setup-saldo-awal.piutang');
    Route::get('/setup-saldo-awal/hutang', [ProductionOpeningSetupController::class, 'payableIndex'])->name('setup-saldo-awal.hutang');
    Route::get('/setup-saldo-awal/kas', [ProductionOpeningSetupController::class, 'cashIndex'])->name('setup-saldo-awal.kas');
    Route::get('/laporan/laporan-pembelian', [ReportController::class, 'purchase'])->name('laporan.laporan-pembelian');
    Route::get('/laporan/laporan-penjualan', [ReportController::class, 'sale'])->name('laporan.laporan-penjualan');
    Route::get('/laporan/laporan-penerimaan-kas', [ReportController::class, 'cashReceipt'])->name('laporan.laporan-penerimaan-kas');
    Route::get('/laporan/laporan-hilang-biasa', [ReportController::class, 'writeoffLoss'])->name('laporan.laporan-hilang-biasa');
    Route::get('/laporan/laporan-stok', [ReportController::class, 'stock'])->name('laporan.laporan-stok');
    Route::get('/laporan/laporan-expired', [ReportController::class, 'expired'])->name('laporan.laporan-expired');
    Route::get('/laporan/laporan-hutang', [ReportController::class, 'payable'])->name('laporan.laporan-hutang');
    Route::get('/laporan/laporan-piutang', [ReportController::class, 'receivable'])->name('laporan.laporan-piutang');
    Route::get('/laporan/laporan-laba-rugi', [ReportController::class, 'profitLoss'])->name('laporan.laporan-laba-rugi');

    Route::middleware('role:superadmin')->group(function () {
        Route::get('/pengaturan/manajemen-lisensi', [LicenseManagementController::class, 'index'])->name('pengaturan.manajemen-lisensi');
        Route::post('/pengaturan/manajemen-lisensi/manual', [LicenseManagementController::class, 'storeManualCode'])->name('pengaturan.manajemen-lisensi.manual');
        Route::post('/pengaturan/manajemen-lisensi/{licenseRenewalRequest}/generate', [LicenseManagementController::class, 'generateCode'])->name('pengaturan.manajemen-lisensi.generate');
        Route::delete('/pengaturan/manajemen-lisensi/{licenseRenewalRequest}/generate', [LicenseManagementController::class, 'cancelGeneratedCode'])->name('pengaturan.manajemen-lisensi.cancel-generate');
        Route::delete('/pengaturan/manajemen-lisensi/kode/{licenseActivationCode}', [LicenseManagementController::class, 'destroyCode'])->name('pengaturan.manajemen-lisensi.codes.destroy');
        Route::post('/pengaturan/manajemen-lisensi/qris', [LicenseManagementController::class, 'updatePaymentSettings'])->name('pengaturan.manajemen-lisensi.qris');
        Route::get('/pengaturan/reset-data-produksi', [ProductionDataResetController::class, 'index'])->name('pengaturan.reset-data-produksi');
        Route::delete('/pengaturan/reset-data-produksi', [ProductionDataResetController::class, 'destroy'])->name('pengaturan.reset-data-produksi.destroy');
    });

    foreach ($modulePages as $page) {
        Route::view($page['path'], 'modules.show', ['page' => $page])->name($page['route']);
    }
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
