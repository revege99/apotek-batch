<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturnItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'purchase_return_id',
        'purchase_invoice_item_id',
        'medicine_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'unit_price',
        'line_total',
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
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * Get the return header for this item.
     */
    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
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
     * Get the replacement items that replenish this return item.
     */
    public function replacementItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnReplacementItem::class);
    }
}
