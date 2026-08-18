<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales')
            ->where('payment_method', '!=', 'credit')
            ->update(['due_date' => null]);
    }

    public function down(): void
    {
        // Non-credit sales intentionally have no due date.
    }
};
