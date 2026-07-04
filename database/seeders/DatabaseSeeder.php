<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $timestamp = now();

        $admin = User::query()->updateOrCreate([
            'email' => 'admin@apotikbaru.local',
        ], [
            'name' => 'Admin Apotik',
            'username' => 'admin',
            'password' => Hash::make('password'),
            'email_verified_at' => $timestamp,
        ]);

        $profileExists = DB::table('pharmacy_profiles')->where('id', 1)->exists();

        DB::table('pharmacy_profiles')->updateOrInsert([
            'id' => 1,
        ], [
            'name' => 'Apotik Baru',
            'owner_name' => 'Owner Apotik',
            'phone' => '081234567890',
            'email' => 'halo@apotikbaru.local',
            'city' => 'Medan',
            'province' => 'Sumatera Utara',
            'postal_code' => '20100',
            'license_number' => 'SIA belum diatur.',
            'address' => 'Alamat apotik belum diatur.',
            'invoice_footer' => 'Terima kasih telah berbelanja di Apotik Baru.',
            'is_active' => true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            ...($profileExists ? [] : [
                'app_license_status' => 'inactive',
                'app_license_expires_at' => null,
                'app_license_activated_at' => null,
            ]),
        ]);

        $superadmin = User::query()->updateOrCreate([
            'email' => 'revege70@gmail.com',
        ], [
            'name' => 'Superadmin Lisensi',
            'username' => 'superadmin',
            'password' => Hash::make('superadmin'),
            'email_verified_at' => $timestamp,
        ]);

        $settings = [
            [
                'setting_group' => 'tax',
                'setting_key' => 'tax.ppn_enabled',
                'label' => 'PPN Aktif',
                'value' => '1',
                'type' => 'boolean',
                'notes' => 'Menandai apakah sistem mengenakan PPN pada transaksi.',
            ],
            [
                'setting_group' => 'tax',
                'setting_key' => 'tax.ppn_percentage',
                'label' => 'Persentase PPN',
                'value' => '11',
                'type' => 'number',
                'notes' => 'Persentase default PPN untuk pembelian dan penjualan.',
            ],
            [
                'setting_group' => 'inventory',
                'setting_key' => 'inventory.tolerance_difference',
                'label' => 'Toleransi Selisih Stok',
                'value' => '0.50',
                'type' => 'number',
                'notes' => 'Batas toleransi selisih stok opname sebelum perlu peninjauan.',
            ],
            [
                'setting_group' => 'license',
                'setting_key' => 'license.qris_receiver_name',
                'label' => 'Penerima QRIS Lisensi',
                'value' => 'QRIS Lisensi',
                'type' => 'string',
                'notes' => 'Nama penerima pembayaran lisensi via QRIS.',
            ],
            [
                'setting_group' => 'license',
                'setting_key' => 'license.qris_notes',
                'label' => 'Catatan QRIS Lisensi',
                'value' => 'Scan QRIS ini untuk pembayaran lisensi, lalu ajukan perpanjangan dari halaman lisensi.',
                'type' => 'text',
                'notes' => 'Catatan yang tampil pada halaman lisensi untuk pembayaran.',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('app_settings')->updateOrInsert([
                'setting_key' => $setting['setting_key'],
            ], [
                ...$setting,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $roles = [
            ['code' => 'superadmin', 'name' => 'Superadmin', 'description' => 'Mengelola lisensi aplikasi dan akses tingkat tertinggi.'],
            ['code' => 'admin', 'name' => 'Admin', 'description' => 'Akses penuh operasional aplikasi untuk klinik kecil.'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert([
                'code' => $role['code'],
            ], [
                ...$role,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $permissions = [
            ['code' => 'manage_master_data', 'name' => 'Kelola Master Data', 'module' => 'master-data'],
            ['code' => 'manage_purchases', 'name' => 'Kelola Pembelian', 'module' => 'pembelian'],
            ['code' => 'manage_sales', 'name' => 'Kelola Penjualan', 'module' => 'penjualan'],
            ['code' => 'manage_inventory', 'name' => 'Kelola Stok dan Batch', 'module' => 'stok-batch'],
            ['code' => 'manage_finance', 'name' => 'Kelola Keuangan', 'module' => 'keuangan'],
            ['code' => 'view_reports', 'name' => 'Lihat Laporan', 'module' => 'laporan'],
            ['code' => 'manage_settings', 'name' => 'Kelola Pengaturan', 'module' => 'pengaturan'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert([
                'code' => $permission['code'],
            ], [
                ...$permission,
                'description' => $permission['name'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $adminRoleId = DB::table('roles')->where('code', 'admin')->value('id');
        $permissionIds = DB::table('permissions')->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_permissions')->updateOrInsert([
                'role_id' => $adminRoleId,
                'permission_id' => $permissionId,
            ], [
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        DB::table('user_roles')->updateOrInsert([
            'user_id' => $admin->id,
            'role_id' => $adminRoleId,
        ], [
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $superadminRoleId = DB::table('roles')->where('code', 'superadmin')->value('id');

        DB::table('user_roles')->updateOrInsert([
            'user_id' => $superadmin->id,
            'role_id' => $superadminRoleId,
        ], [
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }
}
