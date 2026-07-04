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
        DB::table('pharmacy_profiles')
            ->whereDate('app_license_expires_at', '2027-12-31')
            ->whereNull('app_license_activated_at')
            ->where(function ($query): void {
                $query
                    ->whereNull('app_license_status')
                    ->orWhere('app_license_status', 'active');
            })
            ->whereNotExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('license_activation_codes')
                    ->whereColumn('license_activation_codes.pharmacy_profile_id', 'pharmacy_profiles.id')
                    ->where('license_activation_codes.status', 'used');
            })
            ->update([
                'app_license_status' => 'inactive',
                'app_license_expires_at' => null,
                'app_license_activated_at' => null,
                'updated_at' => now(),
            ]);

        DB::table('pharmacy_profiles')
            ->whereNull('app_license_expires_at')
            ->update([
                'app_license_status' => 'inactive',
                'app_license_activated_at' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('pharmacy_profiles')
            ->where('app_license_status', 'inactive')
            ->whereNull('app_license_expires_at')
            ->update([
                'app_license_status' => 'active',
                'app_license_expires_at' => '2027-12-31',
                'updated_at' => now(),
            ]);
    }
};
