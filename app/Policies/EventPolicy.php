<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EventPolicy
{
    // Anyone in same company can view
    public function view(User $user, Event $event): bool
    {
        return (int) $user->company_id === (int) $event->company_id;
    }

    public function viewAny(User $user): bool
    {
        return (bool) $user->company_id;
    }

    // Create allowed for company members (route already gated by corporate middleware)
    public function create(User $user): bool
    {
        return (bool) $user->company_id;
    }

    // Update/manage: same company AND is event owner/manager (event_users pivot)
    public function update(User $user, Event $event): bool
    {
        if ((int) $user->company_id !== (int) $event->company_id) {
            return false;
        }

        return DB::table('event_users')
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'manager'])
            ->exists();
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }
}
