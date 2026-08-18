<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('sale_date')->index();
        });

        DB::table('sales')
            ->select(['id', 'sale_date'])
            ->where('payment_method', 'credit')
            ->orderBy('id')
            ->chunkById(500, function ($sales): void {
                foreach ($sales as $sale) {
                    DB::table('sales')
                        ->where('id', $sale->id)
                        ->update([
                            'due_date' => Carbon::parse($sale->sale_date)->addDays(25)->toDateString(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['due_date']);
            $table->dropColumn('due_date');
        });
    }
};
