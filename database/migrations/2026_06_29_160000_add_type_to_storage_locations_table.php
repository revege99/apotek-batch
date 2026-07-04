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
        Schema::table('storage_locations', function (Blueprint $table) {
            $table->string('type', 20)->default('rack')->after('name')->index();
        });

        DB::table('storage_locations')
            ->whereNull('type')
            ->orWhere('type', '')
            ->update(['type' => 'rack']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('storage_locations', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
