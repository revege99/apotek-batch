<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicine extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'medicine_type',
        'category_name',
        'medicine_group',
        'large_unit',
        'small_unit',
        'small_unit_per_large_unit',
        'minimum_stock',
        'composition',
        'purchase_price',
        'principal_id',
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
            'small_unit_per_large_unit' => 'integer',
            'minimum_stock' => 'decimal:2',
            'purchase_price' => 'decimal:2',
        ];
    }

    /**
     * Get the principal for the medicine.
     */
    public function principal(): BelongsTo
    {
        return $this->belongsTo(Principal::class);
    }

    /**
     * Get stock batches for this medicine.
     */
    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * Get sale items for this medicine.
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
