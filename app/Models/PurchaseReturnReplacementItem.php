<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnReplacementItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'purchase_return_replacement_id',
        'purchase_return_item_id',
        'purchase_invoice_item_id',
        'medicine_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'unit_price',
        'line_total',
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
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * Get the replacement header for this item.
     */
    public function purchaseReturnReplacement(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturnReplacement::class);
    }

    /**
     * Get the purchase return item source for this replacement item.
     */
    public function purchaseReturnItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturnItem::class);
    }

    /**
     * Get the originating purchase invoice item.
     */
    public function purchaseInvoiceItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceItem::class);
    }

    /**
     * Get the medicine for this replacement item.
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
