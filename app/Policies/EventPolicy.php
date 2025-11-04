<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Anyone who can see the league (owner/company owner/admin/collab) can see the event.
     */
    public function view(User $user, Event $event): bool
    {
        // event owner
        if ((int) $user->id === (int) $event->owner_id) {
            return true;
        }

        // company owner
        if ($event->company && (int) $event->company->owner_user_id === (int) $user->id) {
            return true;
        }

        // platform admin
        if ($user->role === \App\Enums\UserRole::Administrator) {
            return true;
        }

        // any collaborator (owner or manager) can view
        // NOTE: assumes $event->collaborators() many-to-many with pivot 'role'
        return $event->collaborators()
            ->where('users.id', $user->id)
            ->exists();
    }

    /**
     * Editable by event owner, company owner, platform admin, or
     * event collaborators with OWNER role (managers excluded).
     */
    public function update(User $user, Event $event): bool
    {
        // event owner
        if ((int) $user->id === (int) $event->owner_id) {
            return true;
        }

        // company owner
        if ($event->company && (int) $event->company->owner_user_id === (int) $user->id) {
            return true;
        }

        // platform admin
        if ($user->role === \App\Enums\UserRole::Administrator) {
            return true;
        }

        // collaborators with OWNER role only
        return $event->collaborators()
            ->where('users.id', $user->id)
            ->wherePivot('role', 'owner')
            ->exists();
    }

    /**
     * Same rule as update.
     */
    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    /**
     * Event kiosk manager:
     * - Only when the event is in 'tablet' scoring mode
     * - Allowed for event owner, company owner, platform admin,
     *   and collaborators with role owner OR manager.
     */
    public function manageKiosks(User $user, Event $event): bool
    {
        $mode = $event->scoring_mode->value ?? $event->scoring_mode;
        if ($mode !== 'tablet') {
            return false;
        }

        // event owner
        if ((int) $user->id === (int) $event->owner_id) {
            return true;
        }

        // company owner
        if ($event->company && (int) $event->company->owner_user_id === (int) $user->id) {
            return true;
        }

        // platform admin
        if ($user->role === \App\Enums\UserRole::Administrator) {
            return true;
        }

        // collaborators: owners OR managers can manage kiosks
        // If your pivot table is named differently, adjust 'event_users.role' to match.
        return $event->collaborators()
            ->where('users.id', $user->id)
            ->whereIn('event_users.role', ['owner', 'manager'])
            ->exists();
    }

    /**
     * Manage participants (CSV imports, list, etc.)
     * Allowed for event owner, company owner, platform admin,
     * and collaborators with role owner OR manager.
     */
    public function manageParticipants(User $user, Event $event): bool
    {
        // event owner
        if ((int) $user->id === (int) $event->owner_id) {
            return true;
        }

        // company owner
        if ($event->company && (int) $event->company->owner_user_id === (int) $user->id) {
            return true;
        }

        // platform admin
        if ($user->role === \App\Enums\UserRole::Administrator) {
            return true;
        }

        // collaborators: owners OR managers can manage participants
        // If your pivot table alias differs, change 'event_users.role' accordingly.
        return $event->collaborators()
            ->where('users.id', $user->id)
            ->whereIn('event_users.role', ['owner', 'manager'])
            ->exists();
    }
}
