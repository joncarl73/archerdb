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
        abort_if($import->league_id !== $league->id, 404, 'Import does not belong to this league.');
        abort_unless($import->isPayable(), 422, 'Import is not payable.');

        /** @var User $user */
        $user = Auth::user();

        // ✅ Always bill to ArcherDB (platform) for participants imports
        $platformSellerId = (int) config('services.platform.seller_id');
        if (! $platformSellerId) {
            Log::error('participants.checkout.error.platform_seller_missing', [
                'league_id' => $league->id,
                'import_id' => $import->id,
            ]);
            abort(500, 'PLATFORM_SELLER_ID is not configured.');
        }

        // Pull staged pricing (created during CSV stage)
        $rowCount = (int) $import->row_count;
        $unitCents = max(0, (int) ($import->unit_price_cents ?? 200)); // fallback only if missing
        $currencyUp = strtoupper((string) ($import->currency ?? 'USD'));
        $currencyLo = strtolower($currencyUp);

        // Recompute subtotal from staged values (source of truth)
        $computedSubtotal = $rowCount * $unitCents;
        $stagedSubtotal = (int) ($import->amount_cents ?? $computedSubtotal);

        // If staging somehow drifted, normalize it now so UI and charge match 1:1
        if ($stagedSubtotal !== $computedSubtotal) {
            Log::warning('participants.checkout.mismatch_corrected', [
                'import_id' => $import->id,
                'was_amount' => $stagedSubtotal,
                'should_be' => $computedSubtotal,
                'rows' => $rowCount,
                'unit' => $unitCents,
            ]);
            $import->amount_cents = $computedSubtotal;
            $import->unit_price_cents = $unitCents;
            $import->currency = $currencyLo;
            $import->save();
            $stagedSubtotal = $computedSubtotal;
        }

        if ($rowCount < 1 || $stagedSubtotal < 1) {
            Log::warning('participants.checkout.blocked.empty_import', [
                'league_id' => $league->id,
                'import_id' => $import->id,
                'row_count' => $rowCount,
                'subtotal' => $stagedSubtotal,
            ]);

            return back()->withErrors(['import' => 'No billable participants were found in this import.']);
        }

        // Idempotency: if we already created a Checkout Session for this import, reuse it.
        if ($import->stripe_checkout_session_id) {
            try {
                $stripe = new StripeClient(config('services.stripe.secret'));
                $existing = $stripe->checkout->sessions->retrieve($import->stripe_checkout_session_id);
                if (! empty($existing->url)) {
                    Log::info('participants.checkout.resuming_existing_session', [
                        'import_id' => $import->id,
                        'session' => $existing->id,
                    ]);

                    return redirect()->away($existing->url);
                }
            } catch (\Throwable $e) {
                Log::warning('participants.checkout.resume_failed_creating_new', [
                    'import_id' => $import->id,
                    'session' => $import->stripe_checkout_session_id,
                    'msg' => $e->getMessage(),
                ]);
                // continue to create a fresh session
            }
        }

        // No Connect application fee for platform-billed participant imports
        $applicationFeeCents = 0;
        $total = $stagedSubtotal + $applicationFeeCents;

        // Create the Order against the platform seller
        $order = new Order;
        $order->seller_id = $platformSellerId; // ArcherDB
        $order->buyer_id = $user->id;
        $order->buyer_email = $user->email;
        $order->currency = $currencyUp;
        $order->subtotal_cents = $stagedSubtotal;
        $order->application_fee_cents = $applicationFeeCents;
        $order->total_cents = $total;
        $order->status = \App\Models\Order::STATUS_INITIATED; // 'initiated'
        $order->stripe_checkout_session_id = null;
        $order->save();

        // Optional: create an order item if your Order has an `items()` relation
        if (method_exists($order, 'items')) {
            try {
                $order->items()->create([
                    'product_id' => null, // not a catalog product; omit or create a virtual product if you prefer
                    'unit_price_cents' => $unitCents,
                    'quantity' => $rowCount,
                    'line_total_cents' => $stagedSubtotal,
                    'metadata' => [
                        'kind' => 'participants_import',
                        'league_id' => $league->id,
                        'participant_import_id' => $import->id,
                    ],
                    'name' => 'Participant import for '.$league->title, // if your items table has a name column
                ]);
            } catch (\Throwable $e) {
                Log::warning('participants.checkout.order_item_create_failed', [
                    'order_id' => $order->id,
                    'import_id' => $import->id,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        // Build Stripe Checkout (standard platform charge — no Connect)
        $stripe = new StripeClient(config('services.stripe.secret'));

        $meta = [
            'kind' => 'participants_import',
            'order_id' => (string) $order->id,
            'participant_import_id' => (string) $import->id,
            'league_id' => (string) $league->id,
            'user_id' => (string) $user->id,
            'unit_price_cents' => (string) $unitCents,
            'currency' => $currencyUp,
        ];

        $payload = [
            'mode' => 'payment',
            'payment_intent_data' => [
                'metadata' => $meta,
            ],
            'line_items' => [[
                'price_data' => [
                    'currency' => $currencyLo,
                    'unit_amount' => $unitCents, // ← staged unit price
                    'product_data' => [
                        'name' => 'Participant import for '.$league->title,
                        'metadata' => [
                            'kind' => 'participants_import',
                            'league_id' => (string) $league->id,
                        ],
                    ],
                ],
                'quantity' => $rowCount,
            ]],
            'metadata' => $meta,
            'success_url' => route('corporate.leagues.participants.import.return', ['league' => $league->id]).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('corporate.leagues.participants.import.confirm', ['league' => $league->id, 'import' => $import->id]),
        ];

        try {
            $session = $stripe->checkout->sessions->create($payload);
        } catch (\Throwable $e) {
            Log::error('participants.checkout.session_create_failed', [
                'order_id' => $order->id,
                'import_id' => $import->id,
                'league_id' => $league->id,
                'payload' => [
                    'currency' => $currencyLo,
                    'row_count' => $rowCount,
                    'unit_cents' => $unitCents,
                    'subtotal' => $stagedSubtotal,
                ],
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Optionally mark the order failed here
            // $order->status = \App\Models\Order::STATUS_FAILED;
            // $order->save();

            return back()->withErrors(['checkout' => 'Could not start Stripe Checkout: '.$e->getMessage()]);
        }

        // Link session → order & import
        $order->stripe_checkout_session_id = $session->id;
        $order->save();

        $import->order_id = $order->id;
        $import->stripe_checkout_session_id = $session->id;
        $import->status = 'pending_payment';
        $import->save();

        Log::info('participants.checkout.started', [
            'order_id' => $order->id,
            'import_id' => $import->id,
            'session' => $session->id,
            'currency' => $currencyUp,
            'unit' => $unitCents,
            'qty' => $rowCount,
            'subtotal' => $stagedSubtotal,
        ]);

        return redirect()->away($session->url);
    }
}
