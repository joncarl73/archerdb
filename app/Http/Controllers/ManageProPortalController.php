<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ManageProPortalController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $customerId = $user->ensureStripeCustomerId();

        $portal = \Stripe\BillingPortal\Session::create([
            'customer' => $customerId,
            'return_url' => route('pro.landing'),
        ]);

        return redirect()->away($portal->url);
    }
}
