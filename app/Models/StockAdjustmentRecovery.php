<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustmentRecovery extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'stock_movement_id',
        'stock_adjustment_follow_up_id',
        'employee_name',
        'replacement_amount',
        'paid_amount',
        'paid_at',
        'status',
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
            'replacement_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    /**
     * Get the stock movement behind this recovery.
     */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function followUp(): BelongsTo
    {
        return $this->belongsTo(StockAdjustmentFollowUp::class, 'stock_adjustment_follow_up_id');
    }

    /**
     * Get the user who created or updated this recovery record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StockAdjustmentRecoveryPayment::class, 'stock_adjustment_recovery_id');
    }
}
