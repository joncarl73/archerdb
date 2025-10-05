<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StartConnectForCurrentSellerController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = Auth::user();

        // Seller for this organizer
        $seller = Seller::firstOrCreate(
            ['owner_id' => $user->id],
            ['name' => $user->name.' — Organizer', 'active' => true]
        );

        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        if (! $seller->stripe_account_id) {
            // Create Express account + request capabilities
            $acct = $stripe->accounts->create([
                'type' => 'express',
                'email' => $user->email,
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
            ]);
            $seller->stripe_account_id = $acct->id;
            $seller->save();
        } else {
            // Ensure capabilities are requested for existing accounts
            try {
                $stripe->accounts->update($seller->stripe_account_id, [
                    'capabilities' => [
                        'card_payments' => ['requested' => true],
                        'transfers' => ['requested' => true],
                    ],
                ]);
            } catch (\Throwable $e) {
                // fine for Standard accounts; ignore
            }
        }

        // Send them to onboarding (your route names)
        $link = $stripe->accountLinks->create([
            'account' => $seller->stripe_account_id,
            'refresh_url' => route('payments.onboard.refresh'),
            'return_url' => route('payments.onboard.return'),
            'type' => 'account_onboarding',
        ]);

        return redirect()->away($link->url);
    }
}
