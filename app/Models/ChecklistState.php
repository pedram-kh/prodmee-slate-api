<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistState extends Model
{
    protected $table = 'project_checklist';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['done' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
