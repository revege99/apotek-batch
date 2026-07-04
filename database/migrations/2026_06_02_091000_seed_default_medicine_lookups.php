<?php

use App\Models\MedicineCategory;
use App\Models\MedicineUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->seedCategories();
        $this->seedUnits();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep master data created by users intact on rollback.
    }

    /**
     * Seed default medicine classifications.
     */
    private function seedCategories(): void
    {
        $defaults = [
            MedicineCategory::TYPE_MEDICINE_TYPE => [
                ['code' => 'JNS0001', 'name' => 'Tablet'],
                ['code' => 'JNS0002', 'name' => 'Kapsul'],
                ['code' => 'JNS0003', 'name' => 'Sirup'],
                ['code' => 'JNS0004', 'name' => 'Salep'],
            ],
            MedicineCategory::TYPE_CATEGORY => [
                ['code' => 'KTG0001', 'name' => 'Analgesik'],
                ['code' => 'KTG0002', 'name' => 'Antibiotik'],
                ['code' => 'KTG0003', 'name' => 'Vitamin'],
                ['code' => 'KTG0004', 'name' => 'Antasida'],
            ],
            MedicineCategory::TYPE_GROUP => [
                ['code' => 'GLG0001', 'name' => 'Obat bebas'],
                ['code' => 'GLG0002', 'name' => 'Obat bebas terbatas'],
                ['code' => 'GLG0003', 'name' => 'Obat keras'],
            ],
        ];

        foreach ($defaults as $type => $items) {
            foreach ($items as $item) {
                $exists = DB::table('medicine_categories')
                    ->where('classification_type', $type)
                    ->where('name', $item['name'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('medicine_categories')->insert([
                    'code' => $item['code'],
                    'classification_type' => $type,
                    'name' => $item['name'],
                    'description' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Seed default medicine units.
     */
    private function seedUnits(): void
    {
        $defaults = [
            MedicineUnit::TYPE_LARGE => [
                ['code' => 'SBR0001', 'name' => 'Box'],
                ['code' => 'SBR0002', 'name' => 'Botol'],
                ['code' => 'SBR0003', 'name' => 'Strip'],
                ['code' => 'SBR0004', 'name' => 'Dus'],
            ],
            MedicineUnit::TYPE_SMALL => [
                ['code' => 'SKC0001', 'name' => 'Tablet'],
                ['code' => 'SKC0002', 'name' => 'Kapsul'],
                ['code' => 'SKC0003', 'name' => 'Sachet'],
                ['code' => 'SKC0004', 'name' => 'Tube'],
            ],
        ];

        foreach ($defaults as $type => $items) {
            foreach ($items as $item) {
                $exists = DB::table('medicine_units')
                    ->where('unit_type', $type)
                    ->where('name', $item['name'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('medicine_units')->insert([
                    'code' => $item['code'],
                    'unit_type' => $type,
                    'name' => $item['name'],
                    'description' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
