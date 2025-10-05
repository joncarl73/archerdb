<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StripeReturnController extends Controller
{
    // After onboarding returns
    public function return(Request $request)
    {
        $seller = Seller::where('owner_id', Auth::id())->firstOrFail();
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        try {
            $acct = $stripe->accounts->retrieve($seller->stripe_account_id, []);
            $card = $acct->capabilities->card_payments ?? null;
            $xfer = $acct->capabilities->transfers ?? null;

            $ok = ($card === 'active' && $xfer === 'active');

            return redirect()->route('dashboard')->with(
                $ok ? 'status' : 'warning',
                $ok ? 'Stripe account connected and ready to accept payments.'
                    : 'Stripe setup is not complete yet. Please finish onboarding.'
            );
        } catch (\Throwable $e) {
            return redirect()->route('dashboard')->with('warning', 'Could not verify Stripe account status.');
        }
    }

    // Refresh link (resume onboarding)
    public function refresh(Request $request)
    {
        $seller = Seller::where('owner_id', Auth::id())->firstOrFail();
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        // Re-request capabilities just in case
        try {
            $stripe->accounts->update($seller->stripe_account_id, [
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
            ]);
        } catch (\Throwable $e) {
        }

        $link = $stripe->accountLinks->create([
            'account' => $seller->stripe_account_id,
            'refresh_url' => route('payments.onboard.refresh'),
            'return_url' => route('payments.onboard.return'),
            'type' => 'account_onboarding',
        ]);

        return redirect()->away($link->url);
    }
}
