<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdminOrCorporate($user);
    }

    public function view(User $user, Event $event): bool
    {
        return $this->isAdminOrCorporate($user) || $event->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->isAdminOrCorporate($user);
    }

    public function update(User $user, Event $event): bool
    {
        return $this->isAdminOrCorporate($user) || $event->owner_id === $user->id;
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->isAdminOrCorporate($user);
    }

    private function isAdminOrCorporate(User $u): bool
    {
        $role = $u->role instanceof \BackedEnum ? $u->role->value : $u->role;

        return in_array($role, [UserRole::Administrator->value ?? 'administrator', UserRole::Corporate->value ?? 'corporate'], true);
    }
}
