<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockMovement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'movement_date',
        'movement_type',
        'reference_table',
        'reference_id',
        'medicine_id',
        'stock_batch_id',
        'storage_location_id',
        'quantity_in',
        'quantity_out',
        'balance_after',
        'unit_cost',
        'notes',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'movement_date' => 'datetime',
            'quantity_in' => 'decimal:2',
            'quantity_out' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    /**
     * Get the medicine for this movement.
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * Get the batch for this movement.
     */
    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }

    /**
     * Get the storage location for this movement.
     */
    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    /**
     * Get the creator for this movement.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the employee reimbursement recovery for this stock adjustment.
     */
    public function adjustmentRecovery(): HasOne
    {
        return $this->hasOne(StockAdjustmentRecovery::class);
    }
}
