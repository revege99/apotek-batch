<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'setting_group',
        'setting_key',
        'label',
        'value',
        'type',
        'notes',
    ];

    /**
     * Get a setting value by key.
     */
    public static function valueOf(string $key, ?string $default = null): ?string
    {
        return static::query()
            ->where('setting_key', $key)
            ->value('value') ?? $default;
    }
}
