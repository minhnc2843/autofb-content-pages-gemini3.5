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
        // Prioritize database over env during tests to allow setting mocks to take effect
        if (app()->runningUnitTests()) {
            $setting = static::where('key', $key)->first();
            if ($setting !== null) {
                if ($setting->is_secret && !empty($setting->value)) {
                    try {
                        return \Illuminate\Support\Facades\Crypt::decryptString($setting->value);
                    } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                        return $setting->value;
                    }
                }
                return $setting->value;
            }

            $envValue = env(strtoupper($key));
            return !empty($envValue) ? $envValue : $default;
        }

        // Check .env first in non-test environments
        $envValue = env(strtoupper($key));
        if (!empty($envValue)) {
            return $envValue;
        }

        // Then check database
        $setting = static::where('key', $key)->first();
        if ($setting) {
            if ($setting->is_secret && !empty($setting->value)) {
                try {
                    return \Illuminate\Support\Facades\Crypt::decryptString($setting->value);
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    return $setting->value;
                }
            }
            return $setting->value;
        }
        return $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function setValue(string $key, ?string $value, bool $isSecret = false): static
    {
        $valueToStore = $value;
        if ($isSecret && !empty($value)) {
            $valueToStore = \Illuminate\Support\Facades\Crypt::encryptString($value);
        }

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $valueToStore, 'is_secret' => $isSecret]
        );
    }
}
