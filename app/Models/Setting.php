<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'is_secret',
    ];

    protected $casts = [
        'is_secret' => 'boolean',
    ];

    /**
     * Get a setting value by key.
     * Prioritizes .env over database.
     */
    public static function getValue(string $key, $default = null): ?string
    {
        // Check .env first
        $envValue = env(strtoupper($key));
        if (!empty($envValue)) {
            return $envValue;
        }

        // Then check database
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function setValue(string $key, ?string $value, bool $isSecret = false): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'is_secret' => $isSecret]
        );
    }
}
