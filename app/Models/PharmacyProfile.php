<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PharmacyProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'owner_name',
        'phone',
        'email',
        'city',
        'province',
        'postal_code',
        'tax_number',
        'license_number',
        'app_license_status',
        'app_license_expires_at',
        'app_license_activated_at',
        'address',
        'invoice_footer',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'app_license_expires_at' => 'datetime',
            'app_license_activated_at' => 'datetime',
        ];
    }

    /**
     * Filter the query to active pharmacy profiles.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
