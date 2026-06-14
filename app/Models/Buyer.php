<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Buyer extends Model
{
    protected $guarded = ['id'];

    public function pitches(): HasMany
    {
        return $this->hasMany(Pitch::class);
    }
}
