<?php

use App\Models\MedicineCategory;
use App\Models\MedicineUnit;
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
        Schema::table('medicine_categories', function (Blueprint $table) {
            $table->string('classification_type', 30)->default(MedicineCategory::TYPE_CATEGORY)->after('code');
            $table->index(['classification_type', 'is_active']);
        });

        Schema::table('medicine_units', function (Blueprint $table) {
            $table->string('unit_type', 30)->default(MedicineUnit::TYPE_LARGE)->after('code');
            $table->index(['unit_type', 'is_active']);
        });

        $this->backfillMedicineCategories();
        $this->backfillMedicineUnits();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medicine_units', function (Blueprint $table) {
            $table->dropIndex(['unit_type', 'is_active']);
            $table->dropColumn('unit_type');
        });

        Schema::table('medicine_categories', function (Blueprint $table) {
            $table->dropIndex(['classification_type', 'is_active']);
            $table->dropColumn('classification_type');
        });
    }

    /**
     * Backfill category master data from existing medicines.
     */
    private function backfillMedicineCategories(): void
    {
        $mappings = [
            MedicineCategory::TYPE_MEDICINE_TYPE => 'medicine_type',
            MedicineCategory::TYPE_CATEGORY => 'category_name',
            MedicineCategory::TYPE_GROUP => 'medicine_group',
        ];

        foreach ($mappings as $type => $field) {
            $names = DB::table('medicines')
                ->whereNotNull($field)
                ->where($field, '!=', '')
                ->distinct()
                ->orderBy($field)
                ->pluck($field);

            foreach ($names as $name) {
                $normalizedName = trim((string) $name);

                if ($normalizedName === '') {
                    continue;
                }

                $exists = DB::table('medicine_categories')
                    ->where('classification_type', $type)
                    ->where('name', $normalizedName)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('medicine_categories')->insert([
                    'code' => $this->nextCategoryCode($type),
                    'classification_type' => $type,
                    'name' => $normalizedName,
                    'description' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Backfill unit master data from existing medicines.
     */
    private function backfillMedicineUnits(): void
    {
        $mappings = [
            MedicineUnit::TYPE_LARGE => 'large_unit',
            MedicineUnit::TYPE_SMALL => 'small_unit',
        ];

        foreach ($mappings as $type => $field) {
            $names = DB::table('medicines')
                ->whereNotNull($field)
                ->where($field, '!=', '')
                ->distinct()
                ->orderBy($field)
                ->pluck($field);

            foreach ($names as $name) {
                $normalizedName = trim((string) $name);

                if ($normalizedName === '') {
                    continue;
                }

                $exists = DB::table('medicine_units')
                    ->where('unit_type', $type)
                    ->where('name', $normalizedName)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('medicine_units')->insert([
                    'code' => $this->nextUnitCode($type),
                    'unit_type' => $type,
                    'name' => $normalizedName,
                    'description' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Generate the next category code inside the migration.
     */
    private function nextCategoryCode(string $type): string
    {
        $prefixes = [
            MedicineCategory::TYPE_MEDICINE_TYPE => 'JNS',
            MedicineCategory::TYPE_CATEGORY => 'KTG',
            MedicineCategory::TYPE_GROUP => 'GLG',
        ];

        $prefix = $prefixes[$type] ?? 'KAT';
        $existingCodes = DB::table('medicine_categories')
            ->where('classification_type', $type)
            ->pluck('code')
            ->all();

        $nextNumber = collect($existingCodes)
            ->map(function ($code): int {
                if (! is_string($code) || ! preg_match('/(\d+)$/', $code, $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max() + 1;

        do {
            $candidate = sprintf('%s%04d', $prefix, $nextNumber);
            $nextNumber++;
        } while (DB::table('medicine_categories')->where('code', $candidate)->exists());

        return $candidate;
    }

    /**
     * Generate the next unit code inside the migration.
     */
    private function nextUnitCode(string $type): string
    {
        $prefixes = [
            MedicineUnit::TYPE_LARGE => 'SBR',
            MedicineUnit::TYPE_SMALL => 'SKC',
        ];

        $prefix = $prefixes[$type] ?? 'SAT';
        $existingCodes = DB::table('medicine_units')
            ->where('unit_type', $type)
            ->pluck('code')
            ->all();

        $nextNumber = collect($existingCodes)
            ->map(function ($code): int {
                if (! is_string($code) || ! preg_match('/(\d+)$/', $code, $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max() + 1;

        do {
            $candidate = sprintf('%s%04d', $prefix, $nextNumber);
            $nextNumber++;
        } while (DB::table('medicine_units')->where('code', $candidate)->exists());

        return $candidate;
    }
};
