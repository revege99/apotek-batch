<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseExchange extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'exchange_number',
        'purchase_invoice_id',
        'supplier_id',
        'exchange_date',
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
            'exchange_date' => 'date',
        ];
    }

    /**
     * Get the purchase invoice for this exchange.
     */
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    /**
     * Get the supplier for this exchange.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the items for this exchange.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseExchangeItem::class);
    }

    /**
     * Get the replacement realizations for this exchange.
     */
    public function replacements(): HasMany
    {
        return $this->hasMany(PurchaseExchangeReplacement::class);
    }

    /**
     * Get the creator for this exchange.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
