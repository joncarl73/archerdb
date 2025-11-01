<?php

// app/Policies/RulesetPolicy.php

namespace App\Policies;

use App\Models\Ruleset;
use App\Models\User;

class RulesetPolicy
{
    public function create(User $user): bool
    {
        return (bool) $user->company_id;
    }

    public function update(User $user, Ruleset $ruleset): bool
    {
        return $ruleset->company_id === $user->company_id;
    }

    public function delete(User $user, Ruleset $ruleset): bool
    {
        return $ruleset->company_id === $user->company_id;
    }
}
