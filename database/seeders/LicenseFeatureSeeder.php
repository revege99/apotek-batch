<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LicenseFeatureSeeder extends Seeder
{
    /**
     * Seed the licensing feature defaults without touching other master data.
     */
    public function run(): void
    {
        $timestamp = now();

        DB::table('roles')->updateOrInsert([
            'code' => 'superadmin',
        ], [
            'name' => 'Superadmin',
            'description' => 'Mengelola lisensi aplikasi dan akses tingkat tertinggi.',
            'is_active' => true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $superadmin = User::query()->updateOrCreate([
            'email' => 'revege70@gmail.com',
        ], [
            'name' => 'Superadmin Lisensi',
            'username' => 'superadmin',
            'password' => Hash::make('superadmin'),
            'email_verified_at' => $timestamp,
        ]);

        $superadminRoleId = DB::table('roles')->where('code', 'superadmin')->value('id');

        DB::table('user_roles')->updateOrInsert([
            'user_id' => $superadmin->id,
            'role_id' => $superadminRoleId,
        ], [
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        DB::table('app_settings')->updateOrInsert([
            'setting_key' => 'license.qris_receiver_name',
        ], [
            'setting_group' => 'license',
            'label' => 'Penerima QRIS Lisensi',
            'value' => 'QRIS Lisensi',
            'type' => 'string',
            'notes' => 'Nama penerima pembayaran lisensi via QRIS.',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        DB::table('app_settings')->updateOrInsert([
            'setting_key' => 'license.qris_notes',
        ], [
            'setting_group' => 'license',
            'label' => 'Catatan QRIS Lisensi',
            'value' => 'Scan QRIS ini untuk pembayaran lisensi, lalu ajukan perpanjangan dari halaman lisensi.',
            'type' => 'text',
            'notes' => 'Catatan yang tampil pada halaman lisensi untuk pembayaran.',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        DB::table('pharmacy_profiles')
            ->whereNull('app_license_status')
            ->update([
                'app_license_status' => 'inactive',
                'updated_at' => $timestamp,
            ]);
    }
}
