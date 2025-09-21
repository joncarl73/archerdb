<?php

namespace App\Policies;

use App\Models\League;
use App\Models\User;

class LeaguePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === \App\Enums\UserRole::Corporate || $user->role === \App\Enums\UserRole::Administrator;
    }

    public function view(User $user, League $league): bool
    {
        return $user->id === $league->owner_id || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->role === \App\Enums\UserRole::Corporate || $user->role === \App\Enums\UserRole::Administrator;
    }

    public function update(User $user, League $league): bool
    {
        return $user->id === $league->owner_id || $user->isAdmin();
    }

    public function delete(User $user, League $league): bool
    {
        return $user->id === $league->owner_id || $user->isAdmin();
    }
}
