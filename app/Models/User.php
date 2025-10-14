<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'role' => UserRole::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'role' => UserRole::class,
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function archerProfile()
    {
        return $this->hasOne(\App\Models\ArcherProfile::class);
    }

    public function loadouts()
    {
        return $this->hasMany(\App\Models\Loadout::class);
    }

    public function trainingSessions(): HasMany
    {
        return $this->hasMany(\App\Models\TrainingSession::class);
    }

    public function isPro(): bool
    {
        if (! $this->is_pro) {
            return false;
        }

        // If we track expiry, respect it
        if ($this->pro_expires_at && now()->greaterThan($this->pro_expires_at)) {
            return false;
        }

        return true;
    }

    public function ensureStripeCustomerId(): string
    {
        if ($this->stripe_customer_id) {
            return $this->stripe_customer_id;
        }

        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
        $customer = \Stripe\Customer::create([
            'email' => $this->email,
            'name' => trim(($this->name ?? '') ?: ($this->first_name.' '.$this->last_name)),
            'metadata' => ['user_id' => $this->id],
        ]);

        $this->stripe_customer_id = $customer->id;
        $this->save();

        return $this->stripe_customer_id;
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    // Convenience checker
    public function isCorporate(): bool
    {
        return $this->role === 'corporate';
    }

    public function isCompanyOwner(?int $companyId): bool
    {
        if (! $companyId) {
            return false;
        }

        return \App\Models\Company::query()
            ->where('id', $companyId)
            ->where('owner_user_id', $this->id)
            ->exists();
    }

    public function leagueRole(int $leagueId): ?string
    {
        $row = \DB::table('league_users')
            ->where(['league_id' => $leagueId, 'user_id' => $this->id])
            ->first();

        return $row?->role;
    }
}
