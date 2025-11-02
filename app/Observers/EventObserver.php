<?php

// app/Observers/EventObserver.php
use App\Models\Product;

class EventObserver
{
    public function saved(Event $event): void
    {
        // If events have price fields or you infer from a ruleset/page input:
        if (! is_null($event->price_cents)) {
            Product::updateOrCreate(
                [
                    'productable_type' => Event::class,
                    'productable_id' => $event->id,
                ],
                [
                    'seller_id' => optional($event->company)->seller?->id ?? config('app.default_seller_id'),
                    'name' => $event->title,
                    'currency' => $event->currency ?? 'USD',
                    'price_cents' => $event->price_cents,
                    'settlement_mode' => $event->type, // 'open' | 'closed'
                    'is_active' => $event->is_published,
                ]
            );
        }
    }
}
