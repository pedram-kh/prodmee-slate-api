<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginCode extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at->isFuture()
            && $this->attempts < config('slate.otp.max_attempts');
    }
}
