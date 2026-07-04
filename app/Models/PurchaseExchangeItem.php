<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseExchangeItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'purchase_exchange_id',
        'purchase_invoice_item_id',
        'medicine_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'quantity' => 'decimal:2',
        ];
    }

    /**
     * Get the exchange header for this item.
     */
    public function purchaseExchange(): BelongsTo
    {
        return $this->belongsTo(PurchaseExchange::class);
    }

    /**
     * Get the originating purchase invoice item.
     */
    public function purchaseInvoiceItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceItem::class);
    }

    /**
     * Get the medicine for this item.
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * Get the replacement items that replenish this exchange item.
     */
    public function replacementItems(): HasMany
    {
        return $this->hasMany(PurchaseExchangeReplacementItem::class);
    }
}
