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
        $foreignColumns = [
            'medicine_category_id',
            'medicine_unit_id',
            'default_supplier_id',
            'default_location_id',
        ];

        foreach ($foreignColumns as $column) {
            if (Schema::hasColumn('medicines', $column)) {
                Schema::table('medicines', function (Blueprint $table) use ($column) {
                    $table->dropConstrainedForeignId($column);
                });
            }
        }

        $plainColumns = [
            'barcode',
            'generic_name',
            'selling_price',
            'is_taxable',
            'tax_percentage',
            'requires_prescription',
            'notes',
        ];

        $columnsToDrop = array_values(array_filter(
            $plainColumns,
            fn (string $column): bool => Schema::hasColumn('medicines', $column)
        ));

        if ($columnsToDrop !== []) {
            Schema::table('medicines', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            if (! Schema::hasColumn('medicines', 'barcode')) {
                $table->string('barcode')->nullable()->unique()->after('code');
            }

            if (! Schema::hasColumn('medicines', 'generic_name')) {
                $table->string('generic_name')->nullable()->index()->after('small_unit_per_large_unit');
            }

            if (! Schema::hasColumn('medicines', 'medicine_category_id')) {
                $table->foreignId('medicine_category_id')->nullable()->after('composition')->constrained('medicine_categories')->nullOnDelete();
            }

            if (! Schema::hasColumn('medicines', 'medicine_unit_id')) {
                $table->foreignId('medicine_unit_id')->nullable()->after('medicine_category_id')->constrained('medicine_units')->nullOnDelete();
            }

            if (! Schema::hasColumn('medicines', 'default_supplier_id')) {
                $table->foreignId('default_supplier_id')->nullable()->after('medicine_unit_id')->constrained('suppliers')->nullOnDelete();
            }

            if (! Schema::hasColumn('medicines', 'default_location_id')) {
                $table->foreignId('default_location_id')->nullable()->after('principal_id')->constrained('storage_locations')->nullOnDelete();
            }

            if (! Schema::hasColumn('medicines', 'selling_price')) {
                $table->decimal('selling_price', 14, 2)->default(0)->after('purchase_price');
            }

            if (! Schema::hasColumn('medicines', 'is_taxable')) {
                $table->boolean('is_taxable')->default(false)->after('selling_price')->index();
            }

            if (! Schema::hasColumn('medicines', 'tax_percentage')) {
                $table->decimal('tax_percentage', 5, 2)->default(0)->after('is_taxable');
            }

            if (! Schema::hasColumn('medicines', 'requires_prescription')) {
                $table->boolean('requires_prescription')->default(false)->after('tax_percentage')->index();
            }

            if (! Schema::hasColumn('medicines', 'notes')) {
                $table->text('notes')->nullable()->after('is_active');
            }
        });
    }
};
