<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Simple key/value store for system-wide settings, editable by admins.
 * Values are persisted as text and read through the typed helpers below.
 */
class Setting extends Model
{
    /** Fee charged for every day a return is late (per-day rate). */
    public const OVERDUE_FEE_PER_DAY = 'overdue_fee_per_day';

    /** JSON array of admin-authored platform policies (terms, privacy, etc.). */
    public const PLATFORM_POLICIES = 'platform_policies';

    protected $fillable = ['key', 'value'];

    /** Read a setting value, falling back to the given default. */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    /** Create or update a setting value. */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }
}
