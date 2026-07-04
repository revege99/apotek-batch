<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningStockEntryItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'opening_stock_entry_id',
        'medicine_id',
        'storage_location_id',
        'stock_batch_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'purchase_price',
        'selling_price',
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
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
        ];
    }

    /**
     * Get owning document.
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(OpeningStockEntry::class, 'opening_stock_entry_id');
    }

    /**
     * Get medicine.
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * Get storage location.
     */
    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    /**
     * Get created stock batch.
     */
    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }
}
