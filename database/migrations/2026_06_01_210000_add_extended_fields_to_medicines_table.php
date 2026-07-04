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
        if (
            Schema::hasColumn('medicines', 'medicine_type')
            && Schema::hasColumn('medicines', 'category_name')
            && Schema::hasColumn('medicines', 'medicine_group')
            && Schema::hasColumn('medicines', 'large_unit')
            && Schema::hasColumn('medicines', 'small_unit')
            && Schema::hasColumn('medicines', 'small_unit_per_large_unit')
        ) {
            return;
        }

        Schema::table('medicines', function (Blueprint $table) {
            if (! Schema::hasColumn('medicines', 'medicine_type')) {
                $table->string('medicine_type')->nullable()->after('name');
            }

            if (! Schema::hasColumn('medicines', 'category_name')) {
                $table->string('category_name')->nullable()->after('medicine_type');
            }

            if (! Schema::hasColumn('medicines', 'medicine_group')) {
                $table->string('medicine_group')->nullable()->after('category_name');
            }

            if (! Schema::hasColumn('medicines', 'large_unit')) {
                $table->string('large_unit')->nullable()->after('medicine_group');
            }

            if (! Schema::hasColumn('medicines', 'small_unit')) {
                $table->string('small_unit')->nullable()->after('large_unit');
            }

            if (! Schema::hasColumn('medicines', 'small_unit_per_large_unit')) {
                $table->unsignedInteger('small_unit_per_large_unit')->nullable()->after('small_unit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('medicines', 'medicine_type') ? 'medicine_type' : null,
            Schema::hasColumn('medicines', 'category_name') ? 'category_name' : null,
            Schema::hasColumn('medicines', 'medicine_group') ? 'medicine_group' : null,
            Schema::hasColumn('medicines', 'large_unit') ? 'large_unit' : null,
            Schema::hasColumn('medicines', 'small_unit') ? 'small_unit' : null,
            Schema::hasColumn('medicines', 'small_unit_per_large_unit') ? 'small_unit_per_large_unit' : null,
        ]));

        if ($columns !== []) {
            Schema::table('medicines', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
