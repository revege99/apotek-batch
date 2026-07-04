<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseInvoiceItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'purchase_invoice_id',
        'medicine_id',
        'storage_location_id',
        'purchase_unit',
        'unit_content',
        'batch_number',
        'expiry_date',
        'quantity',
        'bonus_quantity',
        'unit_price',
        'discount_percentage',
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
            'expiry_date' => 'date',
            'unit_content' => 'decimal:2',
            'quantity' => 'decimal:2',
            'bonus_quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * Get the invoice for this item.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    /**
     * Get the medicine for this item.
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * Get the storage location for this item.
     */
    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    /**
     * Get the stock batch created from this invoice item.
     */
    public function stockBatch(): HasOne
    {
        return $this->hasOne(StockBatch::class);
    }

    /**
     * Get the purchase return items referencing this invoice item.
     */
    public function purchaseReturnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
