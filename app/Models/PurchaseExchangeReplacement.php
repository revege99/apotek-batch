<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseExchangeReplacement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'exchange_replacement_number',
        'purchase_exchange_id',
        'purchase_invoice_id',
        'supplier_id',
        'exchange_replacement_date',
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
            'exchange_replacement_date' => 'date',
        ];
    }

    /**
     * Get the purchase exchange source for this replacement.
     */
    public function purchaseExchange(): BelongsTo
    {
        return $this->belongsTo(PurchaseExchange::class);
    }

    /**
     * Get the purchase invoice source for this exchange replacement.
     */
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    /**
     * Get the supplier for this exchange replacement.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the items for this exchange replacement.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseExchangeReplacementItem::class);
    }

    /**
     * Get the creator for this exchange replacement.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
