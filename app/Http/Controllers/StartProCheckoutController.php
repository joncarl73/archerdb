<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StartProCheckoutController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = Auth::user();

        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        // 1) Ensure a reusable Customer exists and is saved on the user
        if (empty($user->stripe_customer_id)) {
            $customer = \Stripe\Customer::create([
                'email' => $user->email,
                'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->name ?? $user->email),
                'metadata' => ['user_id' => (string) $user->id, 'purpose' => 'pro_subscription'],
            ]);
            $user->stripe_customer_id = $customer->id;
            $user->save();
        }

        $priceId = config('services.stripe.pro_price_id'); // env: STRIPE_PRO_PRICE_ID
        if (! $priceId) {
            abort(500, 'Missing STRIPE_PRO_PRICE_ID');
        }

        // 2) Create a Subscription Checkout Session
        $session = \Stripe\Checkout\Session::create([
            'mode' => 'subscription',
            'customer' => $user->stripe_customer_id,           // <-- reuses the SAME customer
            'client_reference_id' => (string) $user->id,                   // <-- lets webhook map immediately
            'line_items' => [['price' => $priceId, 'quantity' => 1]],
            'metadata' => ['user_id' => (string) $user->id, 'pro' => '1'],
            'subscription_data' => [
                'metadata' => ['user_id' => (string) $user->id, 'pro' => '1'],
            ],
            'allow_promotion_codes' => true,
            'success_url' => route('pro.return').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('pro.landing'),
        ]);

        return redirect()->away($session->url);
    }
}
