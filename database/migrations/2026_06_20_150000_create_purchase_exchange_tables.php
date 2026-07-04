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
        Schema::create('purchase_exchanges', function (Blueprint $table) {
            $table->id();
            $table->string('exchange_number');
            $table->unique('exchange_number', 'purchase_exchanges_number_unique');
            $table->foreignId('purchase_invoice_id')->nullable()->constrained('purchase_invoices')->nullOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->date('exchange_date')->index();
            $table->string('status')->default('draft')->index();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_exchange_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_exchange_id')->constrained('purchase_exchanges')->cascadeOnDelete();
            $table->foreignId('purchase_invoice_item_id')->nullable()->constrained('purchase_invoice_items')->nullOnDelete();
            $table->foreignId('medicine_id')->constrained('medicines')->restrictOnDelete();
            $table->string('batch_number')->nullable()->index();
            $table->date('expiry_date')->nullable()->index();
            $table->decimal('quantity', 14, 2);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_exchange_replacements', function (Blueprint $table) {
            $table->id();
            $table->string('exchange_replacement_number');
            $table->unique('exchange_replacement_number', 'purchase_exchange_replacements_number_unique');
            $table->foreignId('purchase_exchange_id')->constrained('purchase_exchanges')->cascadeOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()->constrained('purchase_invoices')->nullOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->date('exchange_replacement_date')->index();
            $table->string('status')->default('draft')->index();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_exchange_replacement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_exchange_replacement_id')
                ->constrained(table: 'purchase_exchange_replacements', indexName: 'peri_exchange_replacement_fk')
                ->cascadeOnDelete();
            $table->foreignId('purchase_exchange_item_id')
                ->constrained(table: 'purchase_exchange_items', indexName: 'peri_exchange_item_fk')
                ->restrictOnDelete();
            $table->foreignId('purchase_invoice_item_id')
                ->nullable()
                ->constrained(table: 'purchase_invoice_items', indexName: 'peri_invoice_item_fk')
                ->nullOnDelete();
            $table->foreignId('medicine_id')->constrained('medicines')->restrictOnDelete();
            $table->string('batch_number')->nullable()->index();
            $table->date('expiry_date')->nullable()->index();
            $table->decimal('quantity', 14, 2);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_exchange_replacement_items');
        Schema::dropIfExists('purchase_exchange_replacements');
        Schema::dropIfExists('purchase_exchange_items');
        Schema::dropIfExists('purchase_exchanges');
    }
};
