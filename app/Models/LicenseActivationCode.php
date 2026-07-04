<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseActivationCode extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pharmacy_profile_id',
        'license_renewal_request_id',
        'generated_by',
        'used_by',
        'code',
        'license_type',
        'duration_days',
        'fixed_expires_at',
        'previous_expires_at',
        'status',
        'used_at',
        'applied_from',
        'applied_until',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'fixed_expires_at' => 'datetime',
            'previous_expires_at' => 'datetime',
            'applied_from' => 'datetime',
            'applied_until' => 'datetime',
        ];
    }

    /**
     * Pharmacy profile for the code.
     */
    public function pharmacyProfile(): BelongsTo
    {
        return $this->belongsTo(PharmacyProfile::class);
    }

    /**
     * Renewal request related to the code.
     */
    public function renewalRequest(): BelongsTo
    {
        return $this->belongsTo(LicenseRenewalRequest::class, 'license_renewal_request_id');
    }

    /**
     * User that generated the code.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * User that used the code.
     */
    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }
}
