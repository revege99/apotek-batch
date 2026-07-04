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
        Schema::create('stock_adjustment_recoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_id')->unique()->constrained('stock_movements')->cascadeOnDelete();
            $table->string('employee_name');
            $table->decimal('replacement_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->date('paid_at')->nullable()->index();
            $table->string('status')->default('unpaid')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_recoveries');
    }
};
