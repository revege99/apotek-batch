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
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('social_amount', 14, 2)->default(0)->after('discount_amount');
        });

        DB::table('sales')
            ->where('discount_amount', '>', 0)
            ->where('notes', 'like', '%Penjualan sosial%')
            ->update([
                'social_amount' => DB::raw('discount_amount'),
                'discount_amount' => 0,
                'grand_total' => DB::raw('subtotal'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('sales')
            ->where('social_amount', '>', 0)
            ->where('notes', 'like', '%Penjualan sosial%')
            ->update([
                'discount_amount' => DB::raw('social_amount'),
                'grand_total' => DB::raw('paid_amount'),
            ]);

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('social_amount');
        });
    }
};
