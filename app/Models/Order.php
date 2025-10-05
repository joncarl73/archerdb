<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public const STATUS_INITIATED = 'initiated';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'seller_id', 'buyer_id', 'buyer_email', 'currency',
        'subtotal_cents', 'application_fee_cents', 'total_cents',
        'status', 'stripe_checkout_session_id', 'stripe_payment_intent_id',
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
