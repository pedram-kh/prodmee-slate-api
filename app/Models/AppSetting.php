<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /**
     * Store an encrypted value under a key (used for the Anthropic API key).
     */
    public static function putEncrypted(string $key, ?string $plain): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $plain === null ? null : encrypt($plain)]
        );
    }

    public static function getEncrypted(string $key): ?string
    {
        $row = static::find($key);
        if (! $row || $row->value === null) {
            return null;
        }
        try {
            return decrypt($row->value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
