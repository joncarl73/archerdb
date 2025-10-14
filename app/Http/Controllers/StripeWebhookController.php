<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessParticipantImport;
use App\Models\Event;
use App\Models\League;
use App\Models\LeagueParticipant;
use App\Models\Order;
use App\Models\ParticipantImport;
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
            // ---- Checkout for Leagues & Events (orders) + Participant Import ----
            case 'checkout.session.completed':
                $this->handleSessionCompleted($obj, $connectAcct);
                break;

            case 'checkout.session.expired':
                $this->handleSessionExpired($obj);
                break;

            case 'payment_intent.succeeded':
                $this->handlePiSucceeded($obj, $connectAcct);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePiFailed($obj);
                break;

            case 'charge.succeeded':
                $this->handleChargeSucceeded($obj, $connectAcct);
                break;

                // ---- Subscriptions for Pro ----
            case 'customer.subscription.created':
                $this->handleSubscriptionUpsert($obj);
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
    // League/Event order fulfillment + Participant Import
    // ---------------------------

    private function handleSessionCompleted($session, ?string $acct): void
    {
        $sessionId = $session->id ?? null;

        // Detect kind first (participants_import piggy-backs on Checkout)
        $kind = $this->readKind($session);

        if ($kind === 'participants_import') {
            $this->handleParticipantsImportSessionCompleted($session, $acct);

            return;
        }

        // Legacy / standard league & event orders:
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

            // For subscriptions, fulfillment happens in subscription events.
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

    private function handleSessionExpired($session): void
    {
        // If this was a participants_import flow, mark it canceled so admin can retry.
        $kind = $this->readKind($session);
        if ($kind !== 'participants_import') {
            return;
        }

        $meta = $this->toArray($session->metadata ?? null);
        $orderId = isset($meta['order_id']) ? (int) $meta['order_id'] : null;
        $importId = isset($meta['participant_import_id']) ? (int) $meta['participant_import_id'] : null;

        if ($orderId && ($order = Order::find($orderId))) {
            $order->status = Order::STATUS_CANCELED;
            $order->save();
        }
        if ($importId && ($import = ParticipantImport::find($importId))) {
            $import->status = 'canceled';
            $import->save();
        }

        Log::info('participants_import: checkout.session.expired processed', [
            'order_id' => $orderId,
            'import_id' => $importId,
        ]);
    }

    private function handlePiSucceeded($pi, ?string $acct): void
    {
        // This PI can fire for both orders and subscriptions.
        $meta = $this->toArray($pi->metadata ?? null);
        $kind = $meta['kind'] ?? null;

        if ($kind === 'participants_import') {
            $this->handleParticipantsImportPiSucceeded($pi, $acct);

            return;
        }

        // Standard orders
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

    private function handlePiFailed($pi): void
    {
        $meta = $this->toArray($pi->metadata ?? null);
        $kind = $meta['kind'] ?? null;

        if ($kind !== 'participants_import') {
            // nothing to do for standard orders here; failures roll up to session
            return;
        }

        $orderId = isset($meta['order_id']) ? (int) $meta['order_id'] : null;
        $importId = isset($meta['participant_import_id']) ? (int) $meta['participant_import_id'] : null;

        if ($orderId && ($order = Order::find($orderId))) {
            $order->status = Order::STATUS_FAILED;
            $order->save();
        }
        if ($importId && ($import = ParticipantImport::find($importId))) {
            $import->status = 'failed';
            $import->error_text = 'Payment failed.';
            $import->save();
        }

        Log::info('participants_import: payment_intent.payment_failed processed', [
            'order_id' => $orderId,
            'import_id' => $importId,
        ]);
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

        // Idempotency: already paid with same PI → just sync charge/transfer and fulfill
        if ($order->status === \App\Models\Order::STATUS_PAID && $order->stripe_payment_intent_id === $piId) {
            $this->syncChargeAndTransfer($order, $piId, $opts);
            // For participant imports, fulfillment is different (no league/event participant).
            if ($this->orderIsParticipantsImport($order)) {
                $this->fulfillParticipantsImportFromOrder($order);
            } else {
                $this->fulfillOrder($order);
            }

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

        // Fulfill
        if ($this->orderIsParticipantsImport($order)) {
            $this->fulfillParticipantsImportFromOrder($order);
        } else {
            $this->fulfillOrder($order);
        }
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

    /** Fulfillment for both leagues and standalone events */
    private function fulfillOrder(Order $order): void
    {
        $item = $order->items()->first();
        if (! $item) {
            // No items → nothing to fulfill (e.g. manual orders)
            return;
        }

        $meta = $item->metadata ?? [];
        $leagueId = $meta['league_id'] ?? null;
        $leagueU = $meta['league_uuid'] ?? null;
        $eventId = $meta['event_id'] ?? null;
        $eventU = $meta['event_uuid'] ?? null;

        // League-backed: create/update LeagueParticipant (idempotent)
        if ($leagueId || $leagueU) {
            $league = $leagueId
                ? League::find($leagueId)
                : League::where('public_uuid', $leagueU)->first();

            if ($league) {
                $this->ensureLeagueParticipant($league, $order, $meta);
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

    /**
     * Ensure a LeagueParticipant exists. Stamps event_id and optionally division/line-time
     * from order item metadata when present. If participant already exists but event_id is NULL,
     * backfills it.
     */
    private function ensureLeagueParticipant(League $league, Order $order, array $itemMeta = []): void
    {
        $buyer = $order->buyer ?? null;
        $email = $order->buyer_email ?: ($buyer?->email ?? null);

        // Prefer event_id from league; fall back to metadata if provided.
        $eventIdFromLeague = $league->event_id ?: null;
        $eventIdFromMeta = $itemMeta['event_id'] ?? null;
        $eventId = $eventIdFromLeague ?: ($eventIdFromMeta ?: null);

        $exists = LeagueParticipant::query()
            ->where('league_id', $league->id)
            ->where(function ($q) use ($order, $email) {
                $q->where('user_id', $order->buyer_id);
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->first();

        // If it exists, backfill missing event_id (and optional fields) if needed.
        if ($exists) {
            $dirty = false;

            if (! $exists->event_id && $eventId) {
                $exists->event_id = (int) $eventId;
                $dirty = true;
            }

            // Optionally backfill other fields one-time if empty
            $maybe = function ($key) use ($itemMeta) {
                return array_key_exists($key, $itemMeta) ? $itemMeta[$key] : null;
            };

            if (! $exists->event_division_id && $maybe('event_division_id')) {
                $exists->event_division_id = (int) $itemMeta['event_division_id'];
                $dirty = true;
            }
            if (! $exists->preferred_line_time_id && $maybe('preferred_line_time_id')) {
                $exists->preferred_line_time_id = (int) $itemMeta['preferred_line_time_id'];
                $dirty = true;
            }
            if (! $exists->assigned_line_time_id && $maybe('assigned_line_time_id')) {
                $exists->assigned_line_time_id = (int) $itemMeta['assigned_line_time_id'];
                $dirty = true;
            }
            if (! $exists->assigned_lane_number && $maybe('assigned_lane_number')) {
                $exists->assigned_lane_number = (int) $itemMeta['assigned_lane_number'];
                $dirty = true;
            }
            if (! $exists->assigned_lane_slot && $maybe('assigned_lane_slot')) {
                $exists->assigned_lane_slot = (string) $itemMeta['assigned_lane_slot'];
                $dirty = true;
            }
            if ($maybe('assignment_status') && $exists->assignment_status === 'pending') {
                $exists->assignment_status = (string) $itemMeta['assignment_status'];
                $dirty = true;
            }

            if ($dirty) {
                $exists->saveQuietly();
            }

            return;
        }

        // Create a new participant
        $fullName = trim((string) ($buyer?->name ?? ''));
        [$first, $last] = $this->splitFullName($fullName, $email);

        $payload = [
            'league_id' => $league->id,
            'event_id' => $eventId ? (int) $eventId : null, // ✅ stamp event_id
            'user_id' => $order->buyer_id,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email ?: null,
        ];

        // Optional metadata → participant fields
        if (! empty($itemMeta['event_division_id'])) {
            $payload['event_division_id'] = (int) $itemMeta['event_division_id'];
        }
        if (! empty($itemMeta['preferred_line_time_id'])) {
            $payload['preferred_line_time_id'] = (int) $itemMeta['preferred_line_time_id'];
        }
        if (! empty($itemMeta['assigned_line_time_id'])) {
            $payload['assigned_line_time_id'] = (int) $itemMeta['assigned_line_time_id'];
        }
        if (! empty($itemMeta['assigned_lane_number'])) {
            $payload['assigned_lane_number'] = (int) $itemMeta['assigned_lane_number'];
        }
        if (! empty($itemMeta['assigned_lane_slot'])) {
            $payload['assigned_lane_slot'] = (string) $itemMeta['assigned_lane_slot'];
        }
        if (! empty($itemMeta['assignment_status'])) {
            $payload['assignment_status'] = (string) $itemMeta['assignment_status'];
        }

        LeagueParticipant::create($payload);
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
            // Last resort: fetch Stripe customer and try by email
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

        if (in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true)) {
            $user->is_pro = false;
            $user->pro_expires_at = $canceledAt ?: now();
        } elseif ($cancelAtPeriodEnd && $currentPeriodEnd) {
            $user->is_pro = true;
            $user->pro_expires_at = $currentPeriodEnd;
        } elseif (in_array($status, ['active', 'trialing', 'past_due'], true)) {
            $user->is_pro = true;
            $user->pro_expires_at = null;
        } else {
            $user->is_pro = false;
            $user->pro_expires_at = null;
        }

        $user->save();
    }

    private function handleSubscriptionDeleted($sub): void
    {
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
        // $user->stripe_subscription_id = null; // optional
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
    // Participants Import flow
    // ---------------------------

    private function handleParticipantsImportSessionCompleted($session, ?string $acct): void
    {
        // Find Order first (we created an order shell for the import)
        $order = $this->findOrderFromSession($session);
        if (! $order) {
            Log::warning('participants_import: order not found on session.completed', [
                'session_id' => $session->id ?? null,
                'metadata' => $this->toArray($session->metadata ?? null),
            ]);

            return;
        }

        $piId = is_string($session->payment_intent ?? null)
            ? $session->payment_intent
            : ($session->payment_intent->id ?? null);

        if (! $piId) {
            Log::warning('participants_import: no payment_intent on session', [
                'order_id' => $order->id,
                'session_id' => $session->id ?? null,
            ]);

            return;
        }

        // Mark Order as paid + then fulfill import
        $this->attachPiChargeAndMarkPaid($order, $piId, $acct);
        // (attachPiChargeAndMarkPaid will route to fulfillParticipantsImportFromOrder when it detects the "kind")
    }

    private function handleParticipantsImportPiSucceeded($pi, ?string $acct): void
    {
        $meta = $this->toArray($pi->metadata ?? null);
        $orderId = isset($meta['order_id']) ? (int) $meta['order_id'] : null;

        if (! $orderId) {
            // a PI without order_id shouldn't happen for imports, but guard anyway
            Log::warning('participants_import: PI succeeded but no order_id in metadata', ['pi' => $pi->id ?? null, 'meta' => $meta]);

            return;
        }

        $order = Order::find($orderId);
        if (! $order) {
            Log::warning('participants_import: PI succeeded but order not found', ['order_id' => $orderId]);

            return;
        }

        $this->attachPiChargeAndMarkPaid($order, $pi->id, $acct);
        // (attachPiChargeAndMarkPaid will route appropriately)
    }

    private function fulfillParticipantsImportFromOrder(Order $order): void
    {
        $meta = (array) ($order->meta_json ?? []);
        $importId = isset($meta['participant_import_id']) ? (int) $meta['participant_import_id'] : null;

        if (! $importId) {
            // try via associated checkout session metadata if present
            $import = $this->findImportByOrderOrPi($order);
        } else {
            $import = ParticipantImport::find($importId);
        }

        if (! $import) {
            Log::warning('participants_import: fulfillment import not found', ['order_id' => $order->id, 'meta' => $meta]);

            return;
        }

        // Mark as paid and kick off processing if not already processed
        if ($import->status !== 'paid') {
            $import->status = 'paid';
            $import->stripe_checkout_session_id = $order->stripe_checkout_session_id ?: ($import->stripe_checkout_session_id ?? null);
            $import->stripe_payment_intent_id = $order->stripe_payment_intent_id ?: ($import->stripe_payment_intent_id ?? null);
            $import->save();
        }

        if ($import->processed_at === null && $import->status === 'paid') {
            dispatch(new ProcessParticipantImport($import->id));
        }
    }

    private function findImportByOrderOrPi(Order $order): ?ParticipantImport
    {
        if ($order->meta_json && ! empty($order->meta_json['participant_import_id'])) {
            $candidate = ParticipantImport::find((int) $order->meta_json['participant_import_id']);
            if ($candidate) {
                return $candidate;
            }
        }

        if ($order->stripe_checkout_session_id) {
            $found = ParticipantImport::where('stripe_checkout_session_id', $order->stripe_checkout_session_id)->first();
            if ($found) {
                return $found;
            }
        }

        if ($order->stripe_payment_intent_id) {
            $found = ParticipantImport::where('stripe_payment_intent_id', $order->stripe_payment_intent_id)->first();
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    private function orderIsParticipantsImport(Order $order): bool
    {
        // No meta_json in your schema. Detect by presence of a matching ParticipantImport.
        if (class_exists(\App\Models\ParticipantImport::class)) {
            $import = $this->findImportByOrderOrPi($order);

            return (bool) $import;
        }

        return false;
    }

    private function findOrderFromSession($session): ?Order
    {
        $clientRef = $session->client_reference_id ?? null;
        $sessionId = $session->id ?? null;
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

        return $order;
    }

    private function readKind($stripeObj): ?string
    {
        $meta = $this->toArray($stripeObj->metadata ?? null);

        return $meta['kind'] ?? null;
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
