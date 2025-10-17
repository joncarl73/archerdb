<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ParticipantImport extends Model
{
    protected $fillable = [
        'public_uuid',
        'league_id',
        'user_id',
        'file_path',
        'original_name',
        'row_count',
        'unit_price_cents',
        'amount_cents',
        'currency',
        'status',
        'order_id',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'processed_at',
        'error_text',
    ];

    protected static function booted(): void
    {
        static::creating(function ($m) {
            if (empty($m->public_uuid)) {
                $m->public_uuid = (string) Str::uuid();
            }
        });
    }

    public function league()
    {
        return $this->belongsTo(League::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function isPayable(): bool
    {
        return in_array($this->status, ['pending_payment', 'failed', 'canceled'], true);
    }

    public function isProcessable(): bool
    {
        return $this->status === 'paid' && $this->processed_at === null;
    }
}
