<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sale_id',
        'medicine_id',
        'stock_batch_id',
        'batch_number_snapshot',
        'expiry_date_snapshot',
        'quantity',
        'unit_cost',
        'markup_percentage',
        'unit_price',
        'discount_amount',
        'tax_amount',
        'line_total',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expiry_date_snapshot' => 'date',
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'markup_percentage' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * Get the sale header for this item.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the medicine for this item.
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * Get the stock batch used by this item.
     */
    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }

    /**
     * Get sale return rows that reference this sale item.
     */
    public function saleReturnItems(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
