<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Range;
use App\Models\User;

class RangePolicy
{
    protected function isCorp(User $user): bool
    {
        return in_array($user->role, [UserRole::Administrator, UserRole::Corporate], true)
            && (int) $user->company_id > 0;
    }

    public function viewAny(User $user): bool
    {
        return $this->isCorp($user);
    }

    public function view(User $user, Range $range): bool
    {
        return $this->isCorp($user) && (int) $range->company_id === (int) $user->company_id;
    }

    public function create(User $user): bool
    {
        return $this->isCorp($user);
    }

    public function update(User $user, Range $range): bool
    {
        return $this->view($user, $range);
    }

    public function delete(User $user, Range $range): bool
    {
        return $this->view($user, $range);
    }
}
