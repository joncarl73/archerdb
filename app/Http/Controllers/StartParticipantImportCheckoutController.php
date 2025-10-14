<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\Order;
use App\Models\ParticipantImport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class StartParticipantImportCheckoutController extends Controller
{
    public function __invoke(Request $request, League $league, ParticipantImport $import)
    {
        Gate::authorize('update', $league);
        abort_if($import->league_id !== $league->id, 404);
        abort_unless($import->isPayable(), 422, 'Import is not payable.');

        /** @var User $user */
        $user = Auth::user();

        // ✅ Always bill to ArcherDB (platform) for participants imports
        $platformSellerId = (int) config('services.platform.seller_id');
        if (! $platformSellerId) {
            abort(500, 'PLATFORM_SELLER_ID is not configured.');
        }

        // Amounts (your orders schema)
        $subtotal = (int) $import->amount_cents;  // $2 × rows
        $appFee = 0;
        $total = $subtotal + $appFee;

        // Create the Order against the platform seller
        $order = new Order;
        $order->seller_id = $platformSellerId;   // <-- platform/ArcherDB
        $order->buyer_id = $user->id;
        $order->buyer_email = $user->email;
        $order->currency = 'usd';
        $order->subtotal_cents = $subtotal;
        $order->application_fee_cents = $appFee;
        $order->total_cents = $total;
        $order->status = 'initiated';
        $order->save();

        // Create a standard (platform) Checkout Session — NO Connect, NO transfer_data
        $stripe = new StripeClient(config('services.stripe.secret'));

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'payment_intent_data' => [
                'metadata' => [
                    'kind' => 'participants_import',
                    'order_id' => (string) $order->id,
                    'participant_import_id' => (string) $import->id,
                    'league_id' => (string) $league->id,
                    'user_id' => (string) $user->id,
                ],
            ],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => 200, // $2.00 per participant
                    'product_data' => [
                        'name' => 'Participant import for '.$league->title,
                        'metadata' => [
                            'kind' => 'participants_import',
                            'league_id' => (string) $league->id,
                        ],
                    ],
                ],
                'quantity' => $import->row_count,
            ]],
            'metadata' => [
                'kind' => 'participants_import',
                'order_id' => (string) $order->id,
                'participant_import_id' => (string) $import->id,
                'league_id' => (string) $league->id,
                'user_id' => (string) $user->id,
            ],
            'success_url' => route('corporate.leagues.participants.import.return', ['league' => $league->id]).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('corporate.leagues.participants.import.confirm', ['league' => $league->id, 'import' => $import->id]),
        ]);

        // Link session → order and staged import
        $order->stripe_checkout_session_id = $session->id;
        $order->save();

        $import->order_id = $order->id;
        $import->stripe_checkout_session_id = $session->id;
        $import->status = 'pending_payment';
        $import->save();

        Log::info('Participant import checkout started (platform seller)', [
            'order_id' => $order->id,
            'import_id' => $import->id,
            'session' => $session->id,
        ]);

        return redirect()->away($session->url);
    }
}
