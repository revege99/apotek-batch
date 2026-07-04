<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseExchangeReplacementItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'purchase_exchange_replacement_id',
        'purchase_exchange_item_id',
        'purchase_invoice_item_id',
        'medicine_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'notes',
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
     * Get the exchange replacement header for this item.
     */
    public function purchaseExchangeReplacement(): BelongsTo
    {
        return $this->belongsTo(PurchaseExchangeReplacement::class);
    }

    /**
     * Get the purchase exchange item source for this replacement item.
     */
    public function purchaseExchangeItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseExchangeItem::class);
    }

    /**
     * Get the originating purchase invoice item.
     */
    public function purchaseInvoiceItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceItem::class);
    }

    /**
     * Get the medicine for this exchange replacement item.
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
