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
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained('medicines')->restrictOnDelete();
            $table->foreignId('purchase_invoice_item_id')->nullable()->constrained('purchase_invoice_items')->nullOnDelete();
            $table->foreignId('storage_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->string('batch_number')->index();
            $table->date('expiry_date')->nullable()->index();
            $table->date('received_at')->nullable()->index();
            $table->decimal('purchase_price', 14, 2)->default(0);
            $table->decimal('selling_price', 14, 2)->default(0);
            $table->decimal('initial_quantity', 14, 2)->default(0);
            $table->decimal('quantity_in', 14, 2)->default(0);
            $table->decimal('quantity_out', 14, 2)->default(0);
            $table->decimal('quantity_balance', 14, 2)->default(0);
            $table->string('status')->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['medicine_id', 'batch_number']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->dateTime('movement_date')->index();
            $table->string('movement_type')->index();
            $table->string('reference_table')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable()->index();
            $table->foreignId('medicine_id')->constrained('medicines')->restrictOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();
            $table->foreignId('storage_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->decimal('quantity_in', 14, 2)->default(0);
            $table->decimal('quantity_out', 14, 2)->default(0);
            $table->decimal('balance_after', 14, 2)->default(0);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('opname_number')->unique();
            $table->date('opname_date')->index();
            $table->string('status')->default('draft')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained('medicines')->restrictOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();
            $table->foreignId('storage_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->decimal('system_quantity', 14, 2)->default(0);
            $table->decimal('physical_quantity', 14, 2)->default(0);
            $table->decimal('difference_quantity', 14, 2)->default(0);
            $table->decimal('adjustment_value', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_batches');
    }
};
