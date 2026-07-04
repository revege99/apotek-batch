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
        Schema::table('stock_adjustment_follow_ups', function (Blueprint $table) {
            $table->string('employee_name')->nullable()->after('status');
            $table->string('replacement_batch_number')->nullable()->after('employee_name');
            $table->date('replacement_expiry_date')->nullable()->after('replacement_batch_number');
            $table->decimal('replacement_purchase_price', 14, 2)->nullable()->after('replacement_expiry_date');
            $table->unsignedBigInteger('replacement_storage_location_id')->nullable()->after('replacement_purchase_price');
            $table->timestamp('processed_at')->nullable()->after('notes');
            $table->foreignId('processed_by')->nullable()->after('processed_at')->constrained('users')->nullOnDelete();

            $table->foreign('replacement_storage_location_id', 'safu_replacement_location_fk')
                ->references('id')
                ->on('storage_locations')
                ->nullOnDelete();
        });

        Schema::table('stock_adjustment_recoveries', function (Blueprint $table) {
            $table->unsignedBigInteger('stock_adjustment_follow_up_id')->nullable()->after('stock_movement_id');
        });

        DB::statement('ALTER TABLE stock_adjustment_recoveries MODIFY stock_movement_id BIGINT UNSIGNED NULL');

        Schema::table('stock_adjustment_recoveries', function (Blueprint $table) {
            $table->foreign('stock_adjustment_follow_up_id', 'sar_followup_fk')
                ->references('id')
                ->on('stock_adjustment_follow_ups')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_adjustment_recoveries', function (Blueprint $table) {
            $table->dropForeign('sar_followup_fk');
            $table->dropColumn('stock_adjustment_follow_up_id');
        });

        DB::statement('ALTER TABLE stock_adjustment_recoveries MODIFY stock_movement_id BIGINT UNSIGNED NOT NULL');

        Schema::table('stock_adjustment_follow_ups', function (Blueprint $table) {
            $table->dropForeign('safu_replacement_location_fk');
            $table->dropConstrainedForeignId('processed_by');
            $table->dropColumn([
                'employee_name',
                'replacement_batch_number',
                'replacement_expiry_date',
                'replacement_purchase_price',
                'replacement_storage_location_id',
                'processed_at',
            ]);
        });
    }
};
