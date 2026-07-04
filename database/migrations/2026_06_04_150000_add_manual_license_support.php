<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('license_activation_codes', function (Blueprint $table) {
            $table->string('license_type')->default('duration')->after('code');
            $table->dateTime('fixed_expires_at')->nullable()->after('duration_days');
            $table->dateTime('previous_expires_at')->nullable()->after('fixed_expires_at');
        });

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE pharmacy_profiles MODIFY app_license_expires_at DATETIME NULL');
        DB::statement('ALTER TABLE license_renewal_requests MODIFY current_expires_at DATETIME NULL');
        DB::statement('ALTER TABLE license_renewal_requests MODIFY projected_expires_at DATETIME NULL');
        DB::statement('ALTER TABLE license_activation_codes MODIFY duration_days INT NULL');
        DB::statement('ALTER TABLE license_activation_codes MODIFY applied_from DATETIME NULL');
        DB::statement('ALTER TABLE license_activation_codes MODIFY applied_until DATETIME NULL');

        DB::statement("UPDATE pharmacy_profiles SET app_license_expires_at = TIMESTAMP(DATE(app_license_expires_at), '23:59:59') WHERE app_license_expires_at IS NOT NULL");
        DB::statement("UPDATE license_renewal_requests SET current_expires_at = TIMESTAMP(DATE(current_expires_at), '23:59:59') WHERE current_expires_at IS NOT NULL");
        DB::statement("UPDATE license_renewal_requests SET projected_expires_at = TIMESTAMP(DATE(projected_expires_at), '23:59:59') WHERE projected_expires_at IS NOT NULL");
        DB::statement("UPDATE license_activation_codes SET applied_from = TIMESTAMP(DATE(applied_from), '00:00:00') WHERE applied_from IS NOT NULL");
        DB::statement("UPDATE license_activation_codes SET applied_until = TIMESTAMP(DATE(applied_until), '23:59:59') WHERE applied_until IS NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('license_activation_codes', function (Blueprint $table) {
            $table->dropColumn([
                'license_type',
                'fixed_expires_at',
                'previous_expires_at',
            ]);
        });

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('UPDATE license_activation_codes SET duration_days = 0 WHERE duration_days IS NULL');
        DB::statement('ALTER TABLE pharmacy_profiles MODIFY app_license_expires_at DATE NULL');
        DB::statement('ALTER TABLE license_renewal_requests MODIFY current_expires_at DATE NULL');
        DB::statement('ALTER TABLE license_renewal_requests MODIFY projected_expires_at DATE NULL');
        DB::statement('ALTER TABLE license_activation_codes MODIFY duration_days INT NOT NULL');
        DB::statement('ALTER TABLE license_activation_codes MODIFY applied_from DATE NULL');
        DB::statement('ALTER TABLE license_activation_codes MODIFY applied_until DATE NULL');
    }
};
