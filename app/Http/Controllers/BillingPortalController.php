<?php

// app/Http/Controllers/BillingPortalController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillingPortalController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = Auth::user();
        if (! $user->stripe_customer_id) {
            // create on the fly if they never visited checkout but want portal
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            $customer = \Stripe\Customer::create([
                'email' => $user->email,
                'name' => $user->name,
                'metadata' => ['user_id' => (string) $user->id],
            ]);
            $user->stripe_customer_id = $customer->id;
            $user->save();
        }

        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $portal = \Stripe\BillingPortal\Session::create([
            'customer' => $user->stripe_customer_id,
            'return_url' => route('pro.landing'),
        ]);

        return redirect($portal->url);
    }
}
