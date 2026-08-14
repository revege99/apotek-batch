<?php

use App\Models\MedicineUnit;
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
        if (! Schema::hasColumn('medicine_units', 'unit_type')) {
            return;
        }

        Schema::table('medicine_units', function (Blueprint $table) {
            $table->dropIndex(['unit_type', 'is_active']);
            $table->dropColumn('unit_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('medicine_units', 'unit_type')) {
            return;
        }

        Schema::table('medicine_units', function (Blueprint $table) {
            $table->string('unit_type', 30)
                ->default(MedicineUnit::TYPE_LARGE)
                ->after('code');
            $table->index(['unit_type', 'is_active']);
        });
    }
};
