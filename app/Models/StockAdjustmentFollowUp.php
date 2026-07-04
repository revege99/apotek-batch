<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockAdjustmentFollowUp extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'stock_opname_item_id',
        'adjustment_number',
        'adjustment_date',
        'difference_type',
        'settlement_type',
        'status',
        'employee_name',
        'replacement_batch_number',
        'replacement_expiry_date',
        'replacement_purchase_price',
        'replacement_storage_location_id',
        'notes',
        'processed_at',
        'processed_by',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'adjustment_date' => 'date',
            'replacement_expiry_date' => 'date',
            'replacement_purchase_price' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function opnameItem(): BelongsTo
    {
        return $this->belongsTo(StockOpnameItem::class, 'stock_opname_item_id');
    }

    public function batchSelections(): HasMany
    {
        return $this->hasMany(StockAdjustmentFollowUpBatch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function replacementStorageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'replacement_storage_location_id');
    }

    public function recovery(): HasOne
    {
        return $this->hasOne(StockAdjustmentRecovery::class, 'stock_adjustment_follow_up_id');
    }
}
