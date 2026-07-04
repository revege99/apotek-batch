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
        Schema::table('pharmacy_profiles', function (Blueprint $table) {
            $table->string('app_license_status')->default('active')->after('license_number');
            $table->date('app_license_expires_at')->nullable()->after('app_license_status');
            $table->timestamp('app_license_activated_at')->nullable()->after('app_license_expires_at');
        });

        DB::table('pharmacy_profiles')
            ->whereNull('app_license_expires_at')
            ->update([
                'app_license_status' => 'active',
                'app_license_expires_at' => '2027-12-31',
            ]);

        Schema::create('license_renewal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->integer('duration_days');
            $table->string('status')->default('pending')->index();
            $table->text('notes')->nullable();
            $table->date('current_expires_at')->nullable();
            $table->date('projected_expires_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('license_activation_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_renewal_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code')->unique();
            $table->integer('duration_days');
            $table->string('status')->default('available')->index();
            $table->timestamp('used_at')->nullable();
            $table->date('applied_from')->nullable();
            $table->date('applied_until')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_activation_codes');
        Schema::dropIfExists('license_renewal_requests');

        Schema::table('pharmacy_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'app_license_status',
                'app_license_expires_at',
                'app_license_activated_at',
            ]);
        });
    }
};
