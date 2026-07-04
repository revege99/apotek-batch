<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('stock_adjustment_follow_up_batches');
        Schema::dropIfExists('stock_adjustment_follow_ups');

        Schema::create('stock_adjustment_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_opname_item_id')->unique();
            $table->string('adjustment_number')->unique();
            $table->date('adjustment_date')->index();
            $table->string('difference_type', 20)->index();
            $table->string('settlement_type', 30)->nullable()->index();
            $table->string('status', 20)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('stock_opname_item_id', 'safu_opname_item_fk')
                ->references('id')
                ->on('stock_opname_items')
                ->cascadeOnDelete();
        });

        Schema::create('stock_adjustment_follow_up_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_adjustment_follow_up_id');
            $table->unsignedBigInteger('stock_batch_id')->nullable();
            $table->string('action_type', 20)->index();
            $table->decimal('quantity', 14, 2)->default(0);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('stock_adjustment_follow_up_id', 'safub_followup_fk')
                ->references('id')
                ->on('stock_adjustment_follow_ups')
                ->cascadeOnDelete();
            $table->foreign('stock_batch_id', 'safub_batch_fk')
                ->references('id')
                ->on('stock_batches')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_follow_up_batches');
        Schema::dropIfExists('stock_adjustment_follow_ups');
    }
};
