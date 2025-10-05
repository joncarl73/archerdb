<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResumeConnectOnboardingController extends Controller
{
    public function __invoke(Request $request)
    {
        $seller = Seller::where('owner_id', Auth::id())->firstOrFail();
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        $link = $stripe->accountLinks->create([
            'account' => $seller->stripe_account_id,
            'refresh_url' => route('seller.connect.refresh'),
            'return_url' => route('dashboard'),
            'type' => 'account_onboarding',
        ]);

        return redirect()->away($link->url);
    }
}
