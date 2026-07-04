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
        Schema::table('purchase_exchange_replacement_items', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'line_total']);
        });

        Schema::table('purchase_exchange_replacements', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'tax_amount', 'total_amount']);
        });

        Schema::table('purchase_exchange_items', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'line_total']);
        });

        Schema::table('purchase_exchanges', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'tax_amount', 'total_amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_exchanges', function (Blueprint $table) {
            $table->decimal('subtotal', 14, 2)->default(0)->after('status');
            $table->decimal('tax_amount', 14, 2)->default(0)->after('subtotal');
            $table->decimal('total_amount', 14, 2)->default(0)->after('tax_amount');
        });

        Schema::table('purchase_exchange_items', function (Blueprint $table) {
            $table->decimal('unit_price', 14, 2)->default(0)->after('quantity');
            $table->decimal('line_total', 14, 2)->default(0)->after('unit_price');
        });

        Schema::table('purchase_exchange_replacements', function (Blueprint $table) {
            $table->decimal('subtotal', 14, 2)->default(0)->after('status');
            $table->decimal('tax_amount', 14, 2)->default(0)->after('subtotal');
            $table->decimal('total_amount', 14, 2)->default(0)->after('tax_amount');
        });

        Schema::table('purchase_exchange_replacement_items', function (Blueprint $table) {
            $table->decimal('unit_price', 14, 2)->default(0)->after('quantity');
            $table->decimal('line_total', 14, 2)->default(0)->after('unit_price');
        });
    }
};
