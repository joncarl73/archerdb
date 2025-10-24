<?php

namespace App\Policies;

use App\Models\League;
use App\Models\User;

class LeaguePolicy
{
    public function view(User $user, League $league): bool
    {
        // league owner
        if ($user->id === (int) $league->owner_id) {
            return true;
        }

        // company owner
        if ($league->company && (int) $league->company->owner_user_id === (int) $user->id) {
            return true;
        }

        // platform admin
        if ($user->role === \App\Enums\UserRole::Administrator) {
            return true;
        }

        // any collaborator (owner or manager) can view
        return $league->collaborators()
            ->where('users.id', $user->id)
            ->exists();
    }

    public function update(User $user, League $league): bool
    {
        // league owner
        if ($user->id === (int) $league->owner_id) {
            return true;
        }

        // company owner
        if ($league->company && (int) $league->company->owner_user_id === (int) $user->id) {
            return true;
        }

        // platform admin
        if ($user->role === \App\Enums\UserRole::Administrator) {
            return true;
        }

        // collaborator with OWNER role (explicitly exclude managers)
        return $league->collaborators()
            ->where('users.id', $user->id)
            ->wherePivot('role', 'owner')
            ->exists();
    }

    public function delete(User $user, League $league): bool
    {
        // Same rule as update
        return $this->update($user, $league);
    }

    public function manageKiosks(User $user, League $league): bool
    {
        // Only relevant if the league is in tablet mode
        $mode = $league->scoring_mode->value ?? $league->scoring_mode;
        if ($mode !== 'tablet') {
            return false;
        }

        // league owner
        if ((int) $user->id === (int) $league->owner_id) {
            return true;
        }

        // company owner
        if ($league->company && (int) $league->company->owner_user_id === (int) $user->id) {
            return true;
        }

        // platform admin
        if ($user->role === \App\Enums\UserRole::Administrator) {
            return true;
        }

        // collaborators: owners OR managers can manage kiosks
        return $league->collaborators()
            ->where('users.id', $user->id)
            ->whereIn('league_users.role', ['owner', 'manager'])
            ->exists();
    }
}
