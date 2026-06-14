<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $project->isVisibleTo($user);
    }

    /**
     * Admins and members may edit projects they can see. External
     * collaborators are read-only (mirrors executeActions' writer check).
     */
    public function update(User $user, Project $project): bool
    {
        return $user->isWriter() && $project->isVisibleTo($user);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isWriter() && $project->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->isWriter();
    }
}
