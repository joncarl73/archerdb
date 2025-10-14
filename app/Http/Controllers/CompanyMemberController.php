<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompanyMemberController extends Controller
{
    public function index(Company $company)
    {
        Gate::authorize('update', $company); // use CompanyPolicy or a Gate, see below
        $members = User::query()->where('company_id', $company->id)->orderBy('name')->get();

        return view('corporate.company.members', compact('company', 'members'));
    }

    public function store(Request $request, Company $company)
    {
        Gate::authorize('update', $company);

        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::firstOrCreate(
            ['email' => $data['email']],
            ['name' => $data['email'], 'password' => bcrypt(str()->random(24))]
        );

        $user->company_id = $company->id;
        $user->save();

        return back()->with('success', 'User added to company.');
    }

    public function destroy(Company $company, User $user)
    {
        Gate::authorize('update', $company);

        if ($user->id === $company->owner_user_id) {
            return back()->withErrors('Cannot remove the company owner.');
        }

        if ($user->company_id === $company->id) {
            $user->company_id = null;
            $user->save();
        }

        return back()->with('success', 'User removed from company.');
    }
}
