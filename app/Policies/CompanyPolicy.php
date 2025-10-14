<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function update(User $user, Company $company): bool
    {
        // Only the company owner or admin may manage company members/settings
        return $user->id === $company->owner_user_id || $user->role === \App\Enums\UserRole::Administrator;
    }
}
