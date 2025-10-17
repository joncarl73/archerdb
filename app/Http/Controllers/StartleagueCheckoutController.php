<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StartLeagueCheckoutController extends Controller
{
    public function __invoke(Request $request, League $league)
    {
        Log::info('Checkout start hit', [
            'route' => 'checkout.league.start',
            'league_id' => $league->id ?? null,
            'league_uuid' => $league->public_uuid ?? null,
            'user_id' => Auth::id(),
        ]);

        // Must be CLOSED
        $type = ($league->type->value ?? $league->type);
        if ($type !== 'closed') {
            Log::warning('Checkout blocked: league not closed', ['league_id' => $league->id, 'type' => $type]);

            return back()->withErrors(['registration' => 'This league does not accept on-site registration.']);
        }

        // Registration window
        $today = now()->toDateString();
        if ($league->registration_start_date && $today < $league->registration_start_date) {
            Log::warning('Checkout blocked: before registration window', [
                'start' => $league->registration_start_date, 'today' => $today,
            ]);

            return back()->withErrors(['registration' => 'Registration has not opened yet.']);
        }
        if ($league->registration_end_date && $today > $league->registration_end_date) {
            Log::warning('Checkout blocked: after registration window', [
                'end' => $league->registration_end_date, 'today' => $today,
            ]);

            return back()->withErrors(['registration' => 'Registration is closed.']);
        }

        // Product presence
        $product = Product::query()
            ->where('productable_type', League::class)
            ->where('productable_id', $league->id)
            ->where('is_active', true)
            ->first();

        if (! $product || ! $product->price_cents || ! $product->currency) {
            Log::warning('Checkout blocked: product missing/misconfigured', [
                'has_product' => (bool) $product,
                'price_cents' => $product->price_cents ?? null,
                'currency' => $product->currency ?? null,
            ]);

            return back()->withErrors([
                'product' => 'Registration product is not configured. Set price & currency on the info page.',
            ]);
        }

        // Seller + flat fee (cents)
        $seller = $product->seller;                  // null for internal/admin
        $sellerStripe = $seller?->stripe_account_id;       // acct_... for corporate

        // Flat fee selection: product → seller default → config
        $flatFeeCents = $product->platform_fee_cents
            ?? ($seller?->default_platform_fee_cents)
            ?? (int) config('payments.default_platform_fee_cents', 0);

        $flatFeeCents = max(0, (int) $flatFeeCents);
        $applicationFee = $sellerStripe
            ? min($flatFeeCents, (int) $product->price_cents)  // do not exceed price
            : 0;

        // Create Order first
        $order = Order::create([
            'seller_id' => $product->seller_id,
            'buyer_id' => Auth::id(),
            'buyer_email' => Auth::user()->email,
            'currency' => strtoupper($product->currency),
            'subtotal_cents' => (int) $product->price_cents,
            'application_fee_cents' => $applicationFee,
            'total_cents' => (int) $product->price_cents,
            'status' => \App\Models\Order::STATUS_INITIATED,
            'stripe_checkout_session_id' => null,
            'stripe_connected_account_id' => $sellerStripe, // for downstream reconciliation
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'unit_price_cents' => (int) $product->price_cents,
            'quantity' => 1,
            'line_total_cents' => (int) $product->price_cents,
            'metadata' => [
                'league_id' => $league->id,
                'league_uuid' => $league->public_uuid,
            ],
        ]);

        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $meta = [
            'order_id' => (string) $order->id,
            'league_id' => (string) $league->id,
            'product_id' => (string) $product->id,
            'buyer_id' => (string) Auth::id(),
            'league_uuid' => (string) $league->public_uuid,
        ];

        $lineItem = [
            'price_data' => [
                'currency' => strtolower($product->currency),
                'unit_amount' => (int) $product->price_cents,
                'product_data' => ['name' => $product->name ?: ($league->title.' registration')],
            ],
            'quantity' => 1,
        ];

        $successUrl = route('checkout.return').'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('public.league.info', ['uuid' => $league->public_uuid]);

        // DIRECT CHARGES:
        // - Create the PaymentIntent on the connected account (so THEY pay Stripe processing fees)
        // - Still collect your platform fee via application_fee_amount
        $payload = [
            'mode' => 'payment',
            'line_items' => [$lineItem],
            'customer_email' => Auth::user()->email,
            'client_reference_id' => (string) $order->id,
            'metadata' => $meta,
            'payment_intent_data' => [
                'metadata' => $meta,
                // application_fee_amount is allowed for direct charges; Stripe deducts this for the platform
                'application_fee_amount' => $applicationFee,
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ];

        // IMPORTANT:
        // For DIRECT CHARGES, pass stripe_account in request options.
        // DO NOT set transfer_data[destination] here (that would be destination charges).
        $createOpts = [];
        if ($sellerStripe) {
            $createOpts['stripe_account'] = $sellerStripe;
        }

        try {
            $session = \Stripe\Checkout\Session::create($payload, $createOpts);
        } catch (\Throwable $e) {
            Log::error('Stripe Checkout create failed', [
                'order_id' => $order->id,
                'league_id' => $league->id,
                'seller_acct' => $sellerStripe,
                'msg' => $e->getMessage(),
            ]);

            return back()->withErrors(['checkout' => 'Could not start Stripe Checkout: '.$e->getMessage()]);
        }

        $order->stripe_checkout_session_id = $session->id;
        $order->save();

        Log::info('Redirecting to Stripe Checkout', ['order_id' => $order->id, 'session' => $session->id]);

        return redirect($session->url);
    }
}
