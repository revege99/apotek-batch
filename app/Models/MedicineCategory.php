<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineCategory extends Model
{
    use HasFactory;

    public const TYPE_MEDICINE_TYPE = 'type';
    public const TYPE_CATEGORY = 'category';
    public const TYPE_GROUP = 'group';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'classification_type',
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
     * Filter the query to a specific classification type.
     */
    public function scopeForClassificationType(Builder $query, string $classificationType): Builder
    {
        return $query->where('classification_type', $classificationType);
    }

    /**
     * Filter the query to active records.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
