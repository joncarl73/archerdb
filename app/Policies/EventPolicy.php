<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Global override: platform admins can do everything.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->role === UserRole::Administrator ? true : null;
    }

    /**
     * Can see the events index.
     * Let anyone who could plausibly interact with events view the list.
     * (Tweak this if you want it tighter.)
     */
    public function viewAny(User $user): bool
    {
        // If you want this open to all authenticated users, just return true.
        return true;
    }

    /**
     * Anyone who can see the league (owner/company owner/admin/collab) can see the event.
     */
    public function view(User $user, Event $event): bool
    {
        if ((int) $user->id === (int) $event->owner_id) {
            return true;
        }

        if ($event->company && (int) $event->company->owner_user_id === (int) $user->id) {
            return true;
        }

        // collaborators (owner or manager) can view
        return $event->collaborators()
            ->where('users.id', $user->id)
            ->exists();
    }

    /**
     * Who can create a NEW event (class-level ability).
     * Adjust the relationship checks to match your app:
     *  - If you have company ownership/management, allow those users to create.
     *  - Otherwise, return true to let any authenticated user create events.
     */
    public function create(User $user): bool
    {
        // Example: allow users who own or manage at least one company.
        // Swap these to your real relationships, or just `return true;` to allow all signed-in users.
        $ownsACompany = method_exists($user, 'ownedCompanies') && $user->ownedCompanies()->exists();
        $managesACompany = method_exists($user, 'managedCompanies') && $user->managedCompanies()->exists();

        return $ownsACompany || $managesACompany || true; // <- keep `true` if creation is broadly allowed
    }

    /**
     * Editable by event owner, company owner, platform admin, or
     * event collaborators with OWNER role (managers excluded).
     */
    public function update(User $user, Event $event): bool
    {
        if ((int) $user->id === (int) $event->owner_id) {
            return true;
        }

        if ($event->company && (int) $event->company->owner_user_id === (int) $user->id) {
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

        if ((int) $user->id === (int) $event->owner_id) {
            return true;
        }

        if ($event->company && (int) $event->company->owner_user_id === (int) $user->id) {
            return true;
        }

        return $event->collaborators()
            ->where('users.id', $user->id)
            ->whereIn('event_users.role', ['owner', 'manager']) // adjust pivot table/column if needed
            ->exists();
    }

    /**
     * Manage participants (CSV imports, list, etc.)
     * Allowed for event owner, company owner, platform admin,
     * and collaborators with role owner OR manager.
     */
    public function manageParticipants(User $user, Event $event): bool
    {
        if ((int) $user->id === (int) $event->owner_id) {
            return true;
        }

        if ($event->company && (int) $event->company->owner_user_id === (int) $user->id) {
            return true;
        }

        return $event->collaborators()
            ->where('users.id', $user->id)
            ->whereIn('event_users.role', ['owner', 'manager']) // adjust pivot table/column if needed
            ->exists();
    }
}
