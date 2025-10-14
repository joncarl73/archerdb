<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeagueCollaboratorController extends Controller
{
    public function index(League $league)
    {
        $this->authorize('update', $league); // only owners/company owners/admin
        $collabs = $league->collaborators()->withPivot('role')->orderBy('name')->get();

        return view('corporate.leagues.access', compact('league', 'collabs'));
    }

    public function store(Request $request, League $league)
    {
        $this->authorize('update', $league);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in(['owner', 'manager'])],
        ]);

        $user = User::firstOrCreate(
            ['email' => $data['email']],
            ['name' => $data['email'], 'password' => bcrypt(str()->random(24))]
        );

        $league->collaborators()->syncWithoutDetaching([$user->id => ['role' => $data['role']]]);

        return back()->with('success', 'Collaborator added.');
    }

    public function update(Request $request, League $league, User $user)
    {
        $this->authorize('update', $league);

        $data = $request->validate(['role' => ['required', Rule::in(['owner', 'manager'])]]);

        // Guard: keep at least one owner
        if ($data['role'] !== 'owner') {
            $ownerCount = $league->collaborators()->wherePivot('role', 'owner')->count();
            $isOwner = $league->collaborators()->where('users.id', $user->id)->wherePivot('role', 'owner')->exists();
            if ($isOwner && $ownerCount <= 1) {
                return back()->withErrors('Cannot demote the last owner.');
            }
        }

        $league->collaborators()->updateExistingPivot($user->id, ['role' => $data['role']]);

        return back()->with('success', 'Role updated.');
    }

    public function destroy(League $league, User $user)
    {
        $this->authorize('update', $league);

        $isOwner = $league->collaborators()->where('users.id', $user->id)->wherePivot('role', 'owner')->exists();
        $ownerCount = $league->collaborators()->wherePivot('role', 'owner')->count();
        if ($isOwner && $ownerCount <= 1) {
            return back()->withErrors('Cannot remove the last owner.');
        }

        $league->collaborators()->detach($user->id);

        return back()->with('success', 'Collaborator removed.');
    }
}
