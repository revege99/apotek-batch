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
        Schema::dropIfExists('stock_adjustment_recovery_payments');

        Schema::create('stock_adjustment_recovery_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_adjustment_recovery_id');
            $table->string('payment_number')->unique();
            $table->date('payment_date')->index();
            $table->string('payment_method', 30)->default('cash')->index();
            $table->string('reference_number')->nullable();
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('stock_adjustment_recovery_id', 'sarp_recovery_fk')
                ->references('id')
                ->on('stock_adjustment_recoveries')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_recovery_payments');
    }
};
