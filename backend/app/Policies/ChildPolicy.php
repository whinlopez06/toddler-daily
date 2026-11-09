<?php

namespace App\Policies;

use App\Models\Child;
use App\Models\User;

class ChildPolicy
{
    public function view(User $user, Child $child): bool
    {
        return $user->family !== null && $child->family_id === $user->family->id && $user->family->deletion_requested_at === null;
    }
    public function update(User $user, Child $child): bool { return $this->view($user, $child); }
}
