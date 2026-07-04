<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineUnit extends Model
{
    use HasFactory;

    public const TYPE_LARGE = 'large';
    public const TYPE_SMALL = 'small';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'unit_type',
        'name',
        'description',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Filter the query to a specific unit type.
     */
    public function scopeForUnitType(Builder $query, string $unitType): Builder
    {
        return $query->where('unit_type', $unitType);
    }

    /**
     * Filter the query to active records.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
