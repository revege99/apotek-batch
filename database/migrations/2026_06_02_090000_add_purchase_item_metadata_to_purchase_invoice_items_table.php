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
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->string('purchase_unit')->nullable()->after('storage_location_id');
            $table->decimal('unit_content', 14, 2)->default(1)->after('purchase_unit');
            $table->decimal('discount_percentage', 8, 2)->default(0)->after('unit_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->dropColumn(['purchase_unit', 'unit_content', 'discount_percentage']);
        });
    }
};
