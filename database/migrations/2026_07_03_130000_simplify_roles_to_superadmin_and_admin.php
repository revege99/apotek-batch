<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $timestamp = now();

        $administratorRole = DB::table('roles')->where('code', 'administrator')->first();
        $adminRole = DB::table('roles')->where('code', 'admin')->first();

        if ($administratorRole && ! $adminRole) {
            DB::table('roles')
                ->where('id', $administratorRole->id)
                ->update([
                    'code' => 'admin',
                    'name' => 'Admin',
                    'description' => 'Akses penuh operasional aplikasi untuk klinik kecil.',
                    'is_active' => true,
                    'updated_at' => $timestamp,
                ]);

            $adminRole = DB::table('roles')->where('id', $administratorRole->id)->first();
        }

        if (! $adminRole) {
            $adminRoleId = DB::table('roles')->insertGetId([
                'code' => 'admin',
                'name' => 'Admin',
                'description' => 'Akses penuh operasional aplikasi untuk klinik kecil.',
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $adminRole = DB::table('roles')->where('id', $adminRoleId)->first();
        }

        $adminRoleId = $adminRole->id;
        $legacyRoleIds = DB::table('roles')
            ->whereIn('code', ['owner', 'gudang', 'kasir'])
            ->pluck('id');

        foreach ($legacyRoleIds as $legacyRoleId) {
            $userIds = DB::table('user_roles')
                ->where('role_id', $legacyRoleId)
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                DB::table('user_roles')->updateOrInsert([
                    'user_id' => $userId,
                    'role_id' => $adminRoleId,
                ], [
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        }

        DB::table('roles')
            ->whereIn('code', ['owner', 'gudang', 'kasir'])
            ->update([
                'is_active' => false,
                'updated_at' => $timestamp,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')
            ->where('code', 'admin')
            ->update([
                'code' => 'administrator',
                'name' => 'Administrator',
                'description' => 'Akses penuh ke seluruh modul aplikasi.',
                'updated_at' => now(),
            ]);

        DB::table('roles')
            ->whereIn('code', ['owner', 'gudang', 'kasir'])
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};
