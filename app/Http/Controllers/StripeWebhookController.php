<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\League;
use App\Models\LeagueParticipant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $sigHeader = $request->header('Stripe-Signature', '');
        $connectAcct = $request->header('Stripe-Account'); // Connect subaccount (if used)
        $payload = $request->getContent();

        $secret = config('services.stripe.webhook_secret');
        if (! $secret) {
            Log::error('Stripe webhook secret not configured');

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
            // ---- Checkout for Leagues & Events (orders) ----
            case 'checkout.session.completed':
                $this->handleSessionCompleted($obj, $connectAcct);
                break;

            case 'payment_intent.succeeded':
                $this->handlePiSucceeded($obj, $connectAcct);
                break;

            case 'charge.succeeded':
                $this->handleChargeSucceeded($obj, $connectAcct);
                break;

                // ---- Subscriptions for Pro ----
            case 'customer.subscription.created':
                $this->handleSubscriptionUpsert($obj); // treat like updated
                break;

            case 'customer.subscription.updated':
                $this->handleSubscriptionUpsert($obj);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($obj);
                break;

            case 'invoice.paid':
                $this->handleInvoicePaid($obj);
                break;

            default:
                // ignore others
                break;
        }

        return response()->json(['ok' => true]);
    }

    // ---------------------------
    // League/Event order fulfillment
    // ---------------------------

    private function handleSessionCompleted($session, ?string $acct): void
    {
        $sessionId = $session->id ?? null;
        $clientRef = $session->client_reference_id ?? null;
        $order = null;

        if ($clientRef) {
            $order = Order::find((int) $clientRef);
        }
        if (! $order && $sessionId) {
            $order = Order::where('stripe_checkout_session_id', $sessionId)->first();
        }
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

            // If this was a Pro subscription checkout, it’s fine (handled by subscription events).
            return;
        }

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
        // This PI can fire for both orders and subscriptions.
        // Orders carry metadata.order_id from our checkout; subscription PIs often don't.
        $meta = $this->toArray($pi->metadata ?? null);
        $order = null;

        if (! empty($meta['order_id'])) {
            $order = Order::find((int) $meta['order_id']);
        }
        if (! $order) {
            $order = Order::where('stripe_payment_intent_id', $pi->id)->first();
        }

        if (! $order) {
            Log::info('payment_intent.succeeded without matching Order (likely subscription PI)', [
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
        if ($order->stripe_connected_account_id) {
            $opts['stripe_account'] = $order->stripe_connected_account_id;
        } elseif ($acct) {
            $opts['stripe_account'] = $acct;
        }

        // Idempotency: if already paid, just ensure charge/transfer are synced and return
        if ($order->status === \App\Models\Order::STATUS_PAID && $order->stripe_payment_intent_id === $piId) {
            $this->syncChargeAndTransfer($order, $piId, $opts);

            return;
        }

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

        $this->fulfillOrder($order);
    }

    private function syncChargeAndTransfer(Order $order, string $piId, array $opts = []): void
    {
        try {
            $pi = \Stripe\PaymentIntent::retrieve($piId, $opts);
            $chargeId = is_string($pi->latest_charge) ? $pi->latest_charge : ($pi->latest_charge->id ?? null);
            if ($chargeId && $order->stripe_charge_id !== $chargeId) {
                $order->stripe_charge_id = $chargeId;
                try {
                    $ch = \Stripe\Charge::retrieve($chargeId, $opts);
                    $order->stripe_transfer_id = $ch->transfer ?? $order->stripe_transfer_id;
                } catch (\Throwable $e) {
                    Log::warning('sync: charge retrieve failed', ['order_id' => $order->id, 'charge' => $chargeId, 'msg' => $e->getMessage()]);
                }
                $order->save();
            }
        } catch (\Throwable $e) {
            Log::warning('sync: PI retrieve failed', ['order_id' => $order->id, 'pi' => $piId, 'msg' => $e->getMessage()]);
        }
    }

    /** New: fulfillment for both leagues and events */
    private function fulfillOrder(Order $order): void
    {
        $item = $order->items()->first();
        if (! $item) {
            return;
        }

        $meta = $item->metadata ?? [];
        $leagueId = $meta['league_id'] ?? null;
        $leagueU = $meta['league_uuid'] ?? null;
        $eventId = $meta['event_id'] ?? null;
        $eventU = $meta['event_uuid'] ?? null;

        // League-backed: create LeagueParticipant (idempotent)
        if ($leagueId || $leagueU) {
            $league = $leagueId
                ? League::find($leagueId)
                : League::where('public_uuid', $leagueU)->first();

            if ($league) {
                $this->ensureLeagueParticipant($league, $order);
            } else {
                Log::warning('fulfillment: league not found', ['league_id' => $leagueId, 'league_uuid' => $leagueU, 'order_id' => $order->id]);
            }

            return;
        }

        // Standalone Event: optionally create event registration if model exists
        if ($eventId || $eventU) {
            $event = $eventId
                ? Event::find($eventId)
                : Event::where('public_uuid', $eventU)->first();

            if (! $event) {
                Log::warning('fulfillment: event not found', ['event_id' => $eventId, 'event_uuid' => $eventU, 'order_id' => $order->id]);

                return;
            }

            // Prefer EventParticipant, else EventRegistration, else just log
            if (class_exists(\App\Models\EventParticipant::class)) {
                $this->ensureEventParticipant($event, $order);
            } elseif (class_exists(\App\Models\EventRegistration::class)) {
                $this->ensureEventRegistration($event, $order);
            } else {
                Log::info('fulfillment: standalone event paid (no participant model wired)', [
                    'event_id' => $event->id,
                    'order_id' => $order->id,
                ]);
            }
        }
    }

    private function ensureLeagueParticipant(League $league, Order $order): void
    {
        // Detect existing by user_id or email
        $buyer = $order->buyer ?? null;
        $email = $order->buyer_email ?: ($buyer?->email ?? null);

        $exists = LeagueParticipant::query()
            ->where('league_id', $league->id)
            ->where(function ($q) use ($order, $email) {
                $q->where('user_id', $order->buyer_id);
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->first();

        if ($exists) {
            return;
        }

        $fullName = trim((string) ($buyer?->name ?? ''));
        [$first, $last] = $this->splitFullName($fullName, $email);

        LeagueParticipant::create([
            'league_id' => $league->id,
            'user_id' => $order->buyer_id,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email ?: null, // ✅ fix: set key properly
        ]);
    }

    /** Optional fulfillment helpers for events (only used if these models exist) */
    private function ensureEventParticipant(Event $event, Order $order): void
    {
        $model = \App\Models\EventParticipant::class;

        $buyer = $order->buyer ?? null;
        $email = $order->buyer_email ?: ($buyer?->email ?? null);

        $exists = $model::query()
            ->where('event_id', $event->id)
            ->where(function ($q) use ($order, $email) {
                $q->where('user_id', $order->buyer_id);
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->first();

        if ($exists) {
            return;
        }

        $fullName = trim((string) ($buyer?->name ?? ''));
        [$first, $last] = $this->splitFullName($fullName, $email);

        $model::create([
            'event_id' => $event->id,
            'user_id' => $order->buyer_id,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email ?: null,
        ]);
    }

    private function ensureEventRegistration(Event $event, Order $order): void
    {
        $model = \App\Models\EventRegistration::class;

        $buyer = $order->buyer ?? null;
        $email = $order->buyer_email ?: ($buyer?->email ?? null);

        $exists = $model::query()
            ->where('event_id', $event->id)
            ->where(function ($q) use ($order, $email) {
                $q->where('user_id', $order->buyer_id);
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->first();

        if ($exists) {
            return;
        }

        $fullName = trim((string) ($buyer?->name ?? ''));
        [$first, $last] = $this->splitFullName($fullName, $email);

        $model::create([
            'event_id' => $event->id,
            'user_id' => $order->buyer_id,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email ?: null,
        ]);
    }

    // Fixing Name Stuff
    private function splitFullName(?string $fullName, ?string $email = null): array
    {
        $full = trim(preg_replace('/\s+/', ' ', (string) $fullName));

        if ($full !== '') {
            $parts = explode(' ', $full);
            if (count($parts) === 1) {
                return [$parts[0], ''];
            }
            $first = array_shift($parts);
            $last = implode(' ', $parts);

            return [$first ?: '', $last ?: ''];
        }

        // Fallback: derive something readable from email local part
        if ($email) {
            $local = strstr($email, '@', true) ?: $email;
            $local = str_replace(['.', '_', '-'], ' ', $local);
            $local = ucwords(preg_replace('/\s+/', ' ', $local));

            return [$local ?: 'Member', ''];
        }

        return ['Member', ''];
    }

    // ---------------------------
    // Pro subscription fulfillment
    // ---------------------------

    private function handleSubscriptionUpsert($sub): void
    {
        // Find the user by metadata.user_id first (we set this in Checkout),
        // else by stripe_customer_id = $sub->customer.
        $user = null;

        $meta = $this->toArray($sub->metadata ?? null);
        if (! empty($meta['user_id'])) {
            $user = User::find((int) $meta['user_id']);
        }

        if (! $user && ! empty($sub->customer)) {
            $user = User::where('stripe_customer_id', $sub->customer)->first();
        }

        if (! $user) {
            // As a last resort, try fetching the customer’s email and find by email
            try {
                \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                $customer = \Stripe\Customer::retrieve($sub->customer);
                $email = $customer?->email ?? null;
                if ($email) {
                    $user = User::where('email', $email)->first();
                }
            } catch (\Throwable $e) {
                Log::warning('Subscription upsert: unable to fetch Stripe customer', [
                    'customer' => $sub->customer,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        if (! $user) {
            Log::warning('Subscription upsert: user not found for customer', [
                'customer' => $sub->customer ?? null,
                'sub' => $sub->id ?? null,
            ]);

            return;
        }

        // Update subscription id on user
        $user->stripe_subscription_id = $sub->id;

        $status = (string) $sub->status;
        $cancelAtPeriodEnd = (bool) ($sub->cancel_at_period_end ?? false);
        $currentPeriodEnd = ! empty($sub->current_period_end) ? Carbon::createFromTimestamp($sub->current_period_end) : null;
        $canceledAt = ! empty($sub->canceled_at) ? Carbon::createFromTimestamp($sub->canceled_at) : null;

        // Decide pro flags
        if (in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true)) {
            // canceled now
            $user->is_pro = false;
            $user->pro_expires_at = $canceledAt ?: now();
        } elseif ($cancelAtPeriodEnd && $currentPeriodEnd) {
            // scheduled to end at period end
            $user->is_pro = true;
            $user->pro_expires_at = $currentPeriodEnd;
        } elseif (in_array($status, ['active', 'trialing', 'past_due'], true)) {
            // fully active (or trial). Leave pro_expires_at open.
            $user->is_pro = true;
            $user->pro_expires_at = null;
        } else {
            // incomplete, unpaid (not canceled yet), etc. → treat as not pro
            $user->is_pro = false;
            $user->pro_expires_at = null;
        }

        $user->save();
    }

    private function handleSubscriptionDeleted($sub): void
    {
        // When Stripe deletes the sub object (after period ends or immediate cancel).
        $user = User::where('stripe_subscription_id', $sub->id ?? '')->first();

        if (! $user && ! empty($sub->customer)) {
            $user = User::where('stripe_customer_id', $sub->customer)->first();
        }

        if (! $user) {
            Log::warning('Subscription deleted: user not found', [
                'customer' => $sub->customer ?? null,
                'sub' => $sub->id ?? null,
            ]);

            return;
        }

        $canceledAt = ! empty($sub->canceled_at) ? Carbon::createFromTimestamp($sub->canceled_at) : now();

        $user->is_pro = false;
        $user->pro_expires_at = $canceledAt;
        // Keep stripe_subscription_id for history, or null it out if you prefer:
        // $user->stripe_subscription_id = null;
        $user->save();
    }

    private function handleInvoicePaid($invoice): void
    {
        if (empty($invoice->subscription) || empty($invoice->customer)) {
            return;
        }

        $user = User::where('stripe_subscription_id', $invoice->subscription)->first();
        if (! $user) {
            $user = User::where('stripe_customer_id', $invoice->customer)->first();
        }
        if (! $user) {
            return;
        }

        // On successful payment, mark Pro active and clear scheduled expiry (unless cancel_at_period_end is set).
        try {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            $sub = \Stripe\Subscription::retrieve($invoice->subscription);

            $cancelAtPeriodEnd = (bool) ($sub->cancel_at_period_end ?? false);
            $currentPeriodEnd = ! empty($sub->current_period_end) ? Carbon::createFromTimestamp($sub->current_period_end) : null;

            if ($cancelAtPeriodEnd && $currentPeriodEnd) {
                $user->is_pro = true;
                $user->pro_expires_at = $currentPeriodEnd;
            } else {
                $user->is_pro = true;
                $user->pro_expires_at = null;
            }
            $user->save();
        } catch (\Throwable $e) {
            Log::warning('Invoice paid: unable to fetch subscription', [
                'subscription' => $invoice->subscription,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    // ---------------------------
    // Utils
    // ---------------------------

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
