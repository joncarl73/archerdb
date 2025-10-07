<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StartEventCheckoutController extends Controller
{
    public function __invoke(Request $request, string $uuid)
    {
        $event = Event::query()->where('public_uuid', $uuid)->firstOrFail();

        // Basic guards
        if (! $event->price_cents || ! $event->currency) {
            return back()->withErrors(['checkout' => 'This event is not configured for checkout (price/currency missing).']);
        }

        // Upsert a Product on the event (productable morph)
        $product = $event->products()->firstOrCreate(
            [],
            [
                'seller_id' => null, // or your platform seller id
                'name' => $event->title.' registration',
                'currency' => $event->currency,
                'price_cents' => (int) $event->price_cents,
                'settlement_mode' => 'closed',
                'metadata' => ['event_public_uuid' => $event->public_uuid],
                'is_active' => true,
            ]
        );

        // Create Order
        $order = Order::create([
            'seller_id' => $product->seller_id,
            'buyer_id' => Auth::id(),
            'status' => Order::STATUS_INITIATED,
            'currency' => $product->currency,
            'total_cents' => (int) $product->price_cents,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'unit_price_cents' => (int) $product->price_cents,
            'quantity' => 1,
            'line_total_cents' => (int) $product->price_cents,
            'metadata' => [
                'event_id' => $event->id,
                'event_uuid' => $event->public_uuid,
            ],
        ]);

        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $meta = [
            'order_id' => (string) $order->id,
            'event_id' => (string) $event->id,
            'product_id' => (string) $product->id,
            'buyer_id' => (string) Auth::id(),
            'event_uuid' => (string) $event->public_uuid,
        ];

        // (Optionally) direct charges to connected account on $event->stripe_account_id
        $sellerStripe = $event->stripe_account_id ?: null;
        $applicationFee = 0; // compute if you charge a fee

        $lineItem = [
            'price_data' => [
                'currency' => strtolower($product->currency),
                'unit_amount' => (int) $product->price_cents,
                'product_data' => ['name' => $product->name],
            ],
            'quantity' => 1,
        ];

        $successUrl = route('checkout.return').'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('public.event.info', ['uuid' => $event->public_uuid]);

        $payload = [
            'mode' => 'payment',
            'line_items' => [$lineItem],
            'customer_email' => Auth::user()->email,
            'client_reference_id' => (string) $order->id,
            'metadata' => $meta,
            'payment_intent_data' => ['metadata' => $meta],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ];

        $createOpts = [];
        if ($sellerStripe) {
            $payload['payment_intent_data']['application_fee_amount'] = $applicationFee;
            $createOpts['stripe_account'] = $sellerStripe;
        }

        $session = \Stripe\Checkout\Session::create($payload, $createOpts);
        $order->stripe_checkout_session_id = $session->id;
        $order->save();

        return redirect($session->url);
    }
}
