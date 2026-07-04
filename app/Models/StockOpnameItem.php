<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockOpnameItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'stock_opname_id',
        'medicine_id',
        'stock_batch_id',
        'storage_location_id',
        'system_quantity',
        'physical_quantity',
        'difference_quantity',
        'adjustment_value',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:2',
            'physical_quantity' => 'decimal:2',
            'difference_quantity' => 'decimal:2',
            'adjustment_value' => 'decimal:2',
        ];
    }

    /**
     * Get the opname document for this item.
     */
    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    /**
     * Get the medicine for this item.
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * Get the stock batch for this item.
     */
    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }

    /**
     * Get the storage location for this item.
     */
    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    /**
     * Get follow-up adjustment for this opname item.
     */
    public function followUp(): HasOne
    {
        return $this->hasOne(StockAdjustmentFollowUp::class, 'stock_opname_item_id');
    }
}
