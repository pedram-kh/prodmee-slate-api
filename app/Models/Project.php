<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $guarded = ['id'];

    /**
     * Server-side port of visibleProjects(): admins see everything; members and
     * external collaborators see only projects they are attached to (by relation).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $relation = $user->isExternal() ? 'external' : 'member';

        return $query->whereHas('users', function (Builder $q) use ($user, $relation) {
            $q->where('users.id', $user->id)->where('project_user.relation', $relation);
        });
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        $relation = $user->isExternal() ? 'external' : 'member';

        return $this->users()
            ->wherePivot('relation', $relation)
            ->where('users.id', $user->id)
            ->exists();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('relation')->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        return $this->users()->wherePivot('relation', 'member');
    }

    public function collaborators(): BelongsToMany
    {
        return $this->users()->wherePivot('relation', 'external');
    }

    public function pitches(): HasMany
    {
        return $this->hasMany(Pitch::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(ProjectLink::class);
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(ChecklistState::class);
    }
}
