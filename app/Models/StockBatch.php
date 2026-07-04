<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockBatch extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'medicine_id',
        'purchase_invoice_item_id',
        'storage_location_id',
        'batch_number',
        'expiry_date',
        'received_at',
        'purchase_price',
        'selling_price',
        'initial_quantity',
        'quantity_in',
        'quantity_out',
        'quantity_balance',
        'status',
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
            'received_at' => 'date',
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'initial_quantity' => 'decimal:2',
            'quantity_in' => 'decimal:2',
            'quantity_out' => 'decimal:2',
            'quantity_balance' => 'decimal:2',
        ];
    }

    /**
     * Get the medicine for this batch.
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * Get the purchase item that created this batch.
     */
    public function purchaseInvoiceItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceItem::class);
    }

    /**
     * Get the storage location for this batch.
     */
    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    /**
     * Get stock movements recorded for this batch.
     */
    public function stockMovementEntries(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
