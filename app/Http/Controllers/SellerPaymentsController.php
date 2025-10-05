<?php

// app/Http/Controllers/SellerPaymentsController.php

namespace App\Http\Controllers;

use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Stripe\StripeClient;

class SellerPaymentsController extends Controller
{
    public function __construct(private StripeClient $stripe) {}

    public function startOnboarding(Request $request, Seller $seller)
    {
        Gate::authorize('update', $seller); // add a policy: seller.owner_id == auth()->id() OR admin

        if (! $seller->stripe_account_id) {
            $acct = $this->stripe->accounts->create(['type' => 'express']);
            $seller->stripe_account_id = $acct->id;
            $seller->save();
        }

        $link = $this->stripe->accountLinks->create([
            'account' => $seller->stripe_account_id,
            'refresh_url' => route('payments.onboard.refresh'),
            'return_url' => route('payments.onboard.return'),
            'type' => 'account_onboarding',
        ]);

        return redirect()->away($link->url);
    }

    public function onboardReturn(Request $request)
    {
        return redirect()->back()->with('success', 'Stripe onboarding complete (or in progress).');
    }

    public function onboardRefresh(Request $request)
    {
        return redirect()->back()->with('error', 'Please resume Stripe onboarding.');
    }
}
