<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\LeagueParticipant;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $sigHeader = $request->header('Stripe-Signature', '');
        $connectAcct = $request->header('Stripe-Account'); // present for connect events
        $payload = $request->getContent();

        $secret = config('services.stripe.webhook_secret');
        if (! $secret) {
            Log::error('Stripe webhook secret not configured'); // fail fast in dev

            return response()->json(['error' => 'secret missing'], 500);
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe webhook invalid payload', ['msg' => $e->getMessage()]);

            return response()->json(['error' => 'invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Stripe webhook bad signature', ['msg' => $e->getMessage()]);

            return response()->json(['error' => 'bad signature'], 400);
        }

        $type = $event->type;
        $obj = $event->data->object ?? null;

        Log::info('Stripe webhook received', [
            'type' => $type,
            'id' => $event->id,
            'acct' => $connectAcct,
        ]);

        switch ($type) {
            case 'checkout.session.completed':
                $this->handleSessionCompleted($obj, $connectAcct);
                break;

            case 'payment_intent.succeeded':
                $this->handlePiSucceeded($obj, $connectAcct);
                break;

            case 'charge.succeeded': // used to backfill charge/transfer if needed
                $this->handleChargeSucceeded($obj, $connectAcct);
                break;

            default:
                // ignore others
                break;
        }

        return response()->json(['ok' => true]);
    }

    private function handleSessionCompleted($session, ?string $acct): void
    {
        $sessionId = $session->id ?? null;
        $clientRef = $session->client_reference_id ?? null;
        $order = null;

        // Resolve by client_reference_id (we set this to Order ID)
        if ($clientRef) {
            $order = Order::find((int) $clientRef);
        }

        // Fallback: by stored session id
        if (! $order && $sessionId) {
            $order = Order::where('stripe_checkout_session_id', $sessionId)->first();
        }

        // Fallback: by metadata.order_id
        if (! $order) {
            $meta = $this->toArray($session->metadata ?? null);
            if (! empty($meta['order_id'])) {
                $order = Order::find((int) $meta['order_id']);
            }
        }

        if (! $order) {
            Log::warning('Order not found for checkout.session.completed', [
                'session_id' => $sessionId,
                'client_reference_id' => $clientRef,
                'metadata' => $this->toArray($session->metadata ?? null),
            ]);

            return;
        }

        // Attach PI/Charge/Transfer and mark paid
        $piId = is_string($session->payment_intent ?? null)
            ? $session->payment_intent
            : ($session->payment_intent->id ?? null);

        if ($piId) {
            $this->attachPiChargeAndMarkPaid($order, $piId, $acct);
        } else {
            Log::warning('No payment_intent on session', ['order_id' => $order->id, 'session_id' => $sessionId]);
        }
    }

    private function handlePiSucceeded($pi, ?string $acct): void
    {
        $meta = $this->toArray($pi->metadata ?? null);
        $order = null;

        if (! empty($meta['order_id'])) {
            $order = Order::find((int) $meta['order_id']);
        }

        if (! $order) {
            $order = Order::where('stripe_payment_intent_id', $pi->id)->first();
        }

        if (! $order) {
            Log::warning('No Order matched for payment_intent.succeeded', [
                'pi' => $pi->id ?? null,
                'meta' => $meta,
                'acct' => $acct,
            ]);

            return;
        }

        $this->attachPiChargeAndMarkPaid($order, $pi->id, $acct);
    }

    private function handleChargeSucceeded($charge, ?string $acct): void
    {
        $order = Order::where('stripe_charge_id', $charge->id)->first();

        if (! $order && isset($charge->payment_intent)) {
            $piId = is_string($charge->payment_intent) ? $charge->payment_intent : ($charge->payment_intent->id ?? null);
            $order = $piId ? Order::where('stripe_payment_intent_id', $piId)->first() : null;
        }

        if (! $order) {
            return;
        }

        $order->stripe_charge_id = $charge->id;
        $order->stripe_transfer_id = $charge->transfer ?? null;
        $order->save();
    }

    private function attachPiChargeAndMarkPaid(Order $order, string $piId, ?string $acct): void
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $opts = [];
        // For direct charges, fetch from the connected account
        if ($order->stripe_connected_account_id) {
            $opts['stripe_account'] = $order->stripe_connected_account_id;
        } elseif ($acct) {
            $opts['stripe_account'] = $acct; // header from webhook
        }

        // Retrieve PI to get latest_charge and (if direct charge) transfer id on the charge
        $chargeId = null;
        $transferId = null;

        try {
            $pi = \Stripe\PaymentIntent::retrieve($piId, $opts);
            $chargeId = is_string($pi->latest_charge) ? $pi->latest_charge : ($pi->latest_charge->id ?? null);

            if ($chargeId) {
                try {
                    $ch = \Stripe\Charge::retrieve($chargeId, $opts);
                    $transferId = $ch->transfer ?? null;
                } catch (\Throwable $e) {
                    Log::warning('Charge retrieve failed', ['order_id' => $order->id, 'charge' => $chargeId, 'msg' => $e->getMessage()]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('PI retrieve failed; will still mark paid', ['order_id' => $order->id, 'pi' => $piId, 'msg' => $e->getMessage()]);
        }

        $order->stripe_payment_intent_id = $piId;
        if ($chargeId) {
            $order->stripe_charge_id = $chargeId;
        }
        if ($transferId) {
            $order->stripe_transfer_id = $transferId;
        }
        $order->status = \App\Models\Order::STATUS_PAID;
        $order->save();

        // Auto-register participant on success (closed leagues)
        $this->ensureParticipantCreated($order);
    }

    private function ensureParticipantCreated(Order $order): void
    {
        $item = $order->items()->first();
        if (! $item) {
            return;
        }

        $meta = $item->metadata ?? [];
        $leagueId = $meta['league_id'] ?? null;
        if (! $leagueId) {
            return;
        }

        $league = League::find($leagueId);
        if (! $league) {
            return;
        }

        // Avoid duplicates – tie by user_id if present
        $exists = LeagueParticipant::where('league_id', $league->id)
            ->where('user_id', $order->buyer_id)
            ->first();

        if (! $exists) {
            LeagueParticipant::create([
                'league_id' => $league->id,
                'user_id' => $order->buyer_id,
                'first_name' => $order->buyer?->first_name ?? '—',
                'last_name' => $order->buyer?->last_name ?? '—',
                'email' => $order->buyer_email,
            ]);
        }
    }

    private function toArray($stripeObject): array
    {
        if (! $stripeObject) {
            return [];
        }
        if (is_array($stripeObject)) {
            return $stripeObject;
        }
        if ($stripeObject instanceof \Stripe\StripeObject) {
            return $stripeObject->toArray();
        }

        return (array) $stripeObject;
    }
}
