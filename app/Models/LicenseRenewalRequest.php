<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LicenseRenewalRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pharmacy_profile_id',
        'requested_by',
        'duration_days',
        'status',
        'notes',
        'current_expires_at',
        'projected_expires_at',
        'generated_by',
        'generated_at',
        'activated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_expires_at' => 'datetime',
            'projected_expires_at' => 'datetime',
            'generated_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    /**
     * Pharmacy profile for the request.
     */
    public function pharmacyProfile(): BelongsTo
    {
        return $this->belongsTo(PharmacyProfile::class);
    }

    /**
     * User that submitted the request.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * User that generated the code.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Activation code generated for the request.
     */
    public function activationCode(): HasOne
    {
        return $this->hasOne(LicenseActivationCode::class);
    }
}
