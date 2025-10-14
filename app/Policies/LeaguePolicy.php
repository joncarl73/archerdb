<?php

namespace App\Policies;

use App\Models\League;
use App\Models\User;

class LeaguePolicy
{
    public function view(User $user, League $league): bool
    {
        if ($user->role === \App\Enums\UserRole::Administrator) {
            return true;
        }

        // company owner: implicit full access
        $isCompanyOwner = \App\Models\Company::query()
            ->where('id', $league->company_id)
            ->where('owner_user_id', $user->id)
            ->exists();

        if ($isCompanyOwner) {
            return true;
        }

        // creator (owner_id) OR explicit collaborator (owner/manager)
        $pivotRole = $user->leagueRole($league->id);

        return $league->owner_id === $user->id
            || in_array($pivotRole, ['owner', 'manager'], true);
    }

    public function create(User $user): bool
    {
        // Corporate (incl. members you promoted) and Admin can create
        return in_array($user->role, [\App\Enums\UserRole::Corporate, \App\Enums\UserRole::Administrator], true);
    }

    // "update" = league settings access (rename, publish/unpublish, etc.)
    public function update(User $user, League $league): bool
    {
        if ($user->role === \App\Enums\UserRole::Administrator) {
            return true;
        }

        $isCompanyOwner = \App\Models\Company::query()
            ->where('id', $league->company_id)
            ->where('owner_user_id', $user->id)
            ->exists();

        // Only league owner (pivot owner is enforced by us at creation) or company owner
        return $isCompanyOwner || $user->leagueRole($league->id) === 'owner' || $league->owner_id === $user->id;
    }

    // "manage" = operational actions (participants, kiosks, check-ins, results)
    public function manage(User $user, League $league): bool
    {
        if ($user->role === \App\Enums\UserRole::Administrator) {
            return true;
        }

        $isCompanyOwner = \App\Models\Company::query()
            ->where('id', $league->company_id)
            ->where('owner_user_id', $user->id)
            ->exists();

        return $isCompanyOwner || in_array($user->leagueRole($league->id), ['owner', 'manager'], true) || $league->owner_id === $user->id;
    }

    public function delete(User $user, League $league): bool
    {
        // same as update — only true owners/company owner/admin
        return $this->update($user, $league);
    }
}
