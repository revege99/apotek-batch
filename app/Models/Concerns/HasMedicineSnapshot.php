<?php

namespace App\Models\Concerns;

use App\Models\Medicine;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasMedicineSnapshot
{
    public function initializeHasMedicineSnapshot(): void
    {
        $this->mergeFillable([
            'medicine_code_snapshot',
            'medicine_name_snapshot',
            'medicine_unit_snapshot',
        ]);
    }

    public static function bootHasMedicineSnapshot(): void
    {
        static::saving(function ($model): void {
            if (! $model->medicine_id) {
                return;
            }

            $medicine = Medicine::query()
                ->select(['id', 'code', 'name', 'small_unit'])
                ->find($model->medicine_id);

            if ($medicine === null) {
                return;
            }

            $model->medicine_code_snapshot = $medicine->code;
            $model->medicine_name_snapshot = $medicine->name;
            $model->medicine_unit_snapshot = $medicine->small_unit;
        });
    }

    protected function snapshotMedicineRelation(): BelongsTo
    {
        return $this->belongsTo(Medicine::class, 'medicine_id')->withDefault(function (Medicine $medicine, $model): void {
            $medicine->code = $model->medicine_code_snapshot ?: '-';
            $medicine->name = $model->medicine_name_snapshot ?: 'Obat terhapus';
            $medicine->small_unit = $model->medicine_unit_snapshot ?: 'unit';
            $medicine->is_active = false;
        });
    }
}
