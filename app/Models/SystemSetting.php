<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value', 'type'];

    protected $casts = [
        'type' => 'string',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = self::where('key', $key)->first();
        if (! $row) {
            return $default;
        }

        return match ($row->type) {
            'bool' => filter_var($row->value, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $row->value,
            'json' => json_decode($row->value, true),
            default => $row->value,
        };
    }

    public static function set(string $key, mixed $value, string $type = 'string'): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) || is_array($value) || is_object($value)
                ? json_encode($value)
                : (string) $value,
             'type' => $type],
        );
    }
}