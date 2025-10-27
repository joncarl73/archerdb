<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        // Corporate users can view events for their company; admins see all.
        return $user->isAdmin() || ($event->company_id && $user->company_id === $event->company_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isCorporate();
    }

    public function update(User $user, Event $event): bool
    {
        return $user->isAdmin() || ($event->company_id && $user->company_id === $event->company_id);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }
}
