<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sale_number',
        'sale_date',
        'status',
        'payment_method',
        'customer_id',
        'customer_group_id',
        'customer_name',
        'customer_phone',
        'customer_group_markup_percentage',
        'subtotal',
        'discount_amount',
        'social_amount',
        'tax_amount',
        'grand_total',
        'paid_amount',
        'change_amount',
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
            'sale_date' => 'datetime',
            'customer_group_markup_percentage' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'social_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
        ];
    }

    /**
     * Get the customer for this sale.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the customer group used for this sale.
     */
    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    /**
     * Get the sale items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Get the sale returns recorded for this sale.
     */
    public function saleReturns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    /**
     * Get receivable payments recorded for this sale.
     */
    public function customerPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * Get the creator for this sale.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
