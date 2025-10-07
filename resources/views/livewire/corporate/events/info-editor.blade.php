<?php

use App\Models\Event;
use App\Models\EventInfo;
use App\Models\League;
use App\Models\LeagueInfo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;

new class extends Component
{
    use \Livewire\WithFileUploads;

    public Event $event;

    public ?EventInfo $info = null;

    // Display helpers / legacy league link
    public ?League $league = null;

    public string $title = '';

    public ?string $registration_url = null; // stored on EventInfo (also used by OPEN leagues)

    public string $content_html = '';

    public bool $is_published = false;

    // UI fields (map to Event.starts_on/ends_on; mirror to League.registration_* if present)
    public ?string $registration_start_date = null;

    public ?string $registration_end_date = null;

    // Banner upload
    public $banner; // TemporaryUploadedFile

    public ?string $banner_url = null;

    // Payment (league or event)
    public ?int $price_cents = null;       // persisted cents (league OR event)

    public ?string $price_dollars = null;  // UI

    public string $currency = 'USD';

    public function mount(Event $event): void
    {
        // Authorize via Event; if policy not present yet, fall back to linked League
        try {
            Gate::authorize('update', $event);
        } catch (\Throwable $e) {
            if ($event->league) {
                Gate::authorize('update', $event->league);
            }
        }

        $this->event = $event->load(['info', 'league', 'league.info']);
        $this->league = $this->event->league;
        $this->info = $this->event->info ?: new EventInfo(['event_id' => $this->event->id]);

        // Title/content/publish
        $fallbackTitle = $this->league?->info?->title ?? $this->league?->title ?? $this->event->title;
        $this->title = (string) ($this->info->title ?? $fallbackTitle);
        $this->registration_url = $this->info->registration_url ?? $this->league?->info?->registration_url ?? null;
        $this->content_html = (string) ($this->info->content_html ?? $this->league?->info?->content_html ?? '');
        $this->is_published = (bool) ($this->info->is_published ?? $this->league?->info?->is_published ?? false);

        // Dates → YYYY-MM-DD (Event is source of truth; mirror league below when saving)
        $this->registration_start_date = $this->asYmd($this->event->starts_on ?? $this->league?->registration_start_date);
        $this->registration_end_date = $this->asYmd($this->event->ends_on ?? $this->league?->registration_end_date);

        // Payment defaults
        if ($this->league) {
            // League-backed: keep legacy price fields as before (closed leagues)
            $this->price_cents = $this->league->price_cents;
            $this->price_dollars = $this->league->price_cents !== null
                ? number_format(((int) $this->league->price_cents) / 100, 2, '.', '')
                : null;
            $this->currency = $this->league->currency ?: 'USD';
        } else {
            // Non-league events can be billable (Step 7)
            $this->price_cents = $this->event->price_cents;
            $this->price_dollars = $this->event->price_cents !== null
                ? number_format(((int) $this->event->price_cents) / 100, 2, '.', '')
                : null;
            $this->currency = $this->event->currency ?: 'USD';
        }

        // Banner image: prefer EventInfo, fall back to LeagueInfo
        $bannerPath = $this->info?->banner_path ?: $this->league?->info?->banner_path;
        if ($bannerPath && Storage::disk('public')->exists($bannerPath)) {
            $this->banner_url = Storage::url($bannerPath);
        }
    }

    private function upsertProductForLeague(): void
    {
        // Use your existing Stripe/Connect flow via the League productable
        if (! $this->league) {
            return;
        }

        $type = ($this->league->type->value ?? $this->league->type);
        $isClosed = $type === 'closed';
        if (! $isClosed) {
            return;
        }

        $owner = $this->league->owner()->first();
        $role = $this->normalizeRole($owner?->role);

        $sellerId = null;
        $feeBps = 0;
        $sellerStripe = null;

        if ($role === 'corporate') {
            $seller = \App\Models\Seller::firstOrCreate(
                ['owner_id' => $owner->id],
                ['name' => $owner->name.' — Organizer']
            );

            if (empty($seller->stripe_account_id)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'title' => 'This is a paid (closed) event, but the organizer is not connected to Stripe yet. Please complete Stripe onboarding first.',
                ]);
            }

            $sellerId = $seller->id;
            $sellerStripe = $seller->stripe_account_id;
            $feeBps = (int) ($seller->default_platform_fee_bps ?? config('payments.default_platform_fee_bps', 250));
        } else {
            $sellerId = $this->getPlatformSellerId();
            $feeBps = 0;
            $sellerStripe = null;
        }

        $product = \App\Models\Product::firstOrNew([
            'productable_type' => \App\Models\League::class,
            'productable_id' => $this->league->id,
        ]);

        $product->fill([
            'seller_id' => $sellerId,
            'name' => $this->title ?: ($this->league->title.' registration'),
            'currency' => $this->league->currency,
            'price_cents' => (int) $this->league->price_cents,
            'settlement_mode' => 'closed',
            'metadata' => ['league_public_uuid' => $this->league->public_uuid, 'event_id' => $this->event->id],
            'is_active' => true,
        ]);

        if (is_null($product->platform_fee_bps)) {
            $product->platform_fee_bps = $feeBps;
        }

        $product->save();

        $this->ensureStripeCatalog($product, $sellerStripe);
    }

    /** NEW: Event payments (Step 7) */
    private function upsertProductForEvent(): void
    {
        // Choose owner similar to leagues; prefer linked league owner if present.
        $owner = $this->league?->owner()->first() ?: ($this->event->owner()->first() ?? null);
        $role = $this->normalizeRole($owner?->role ?? null);

        $sellerId = null;
        $sellerStripe = null;
        $feeBps = 0;

        if ($role === 'corporate' && $owner) {
            $seller = \App\Models\Seller::firstOrCreate(
                ['owner_id' => $owner->id],
                ['name' => $owner->name.' — Organizer']
            );

            if (empty($seller->stripe_account_id)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'title' => 'Organizer is not connected to Stripe yet. Please complete Stripe onboarding.',
                ]);
            }

            $sellerId = $seller->id;
            $sellerStripe = $seller->stripe_account_id;
            $feeBps = (int) ($seller->default_platform_fee_bps ?? config('payments.default_platform_fee_bps', 250));
        } else {
            $sellerId = $this->getPlatformSellerId();
            $sellerStripe = null;
            $feeBps = 0;
        }

        // Upsert Product for Event
        $product = \App\Models\Product::firstOrNew([
            'productable_type' => \App\Models\Event::class,
            'productable_id' => $this->event->id,
        ]);

        $product->fill([
            'seller_id' => $sellerId,
            'name' => ($this->title ?: $this->event->title).' registration',
            'currency' => $this->event->currency ?: 'USD',
            'price_cents' => (int) $this->event->price_cents,
            'settlement_mode' => 'closed',
            'metadata' => [
                'event_public_uuid' => $this->event->public_uuid,
                'league_public_uuid' => $this->league?->public_uuid,
            ],
            'is_active' => true,
        ]);

        if (is_null($product->platform_fee_bps)) {
            $product->platform_fee_bps = $feeBps;
        }

        $product->save();

        // Ensure Stripe Product/Price on EVENT and persist IDs on events table
        [$sp, $price] = $this->ensureStripeCatalogForEvent($product, $sellerStripe);
        $this->event->stripe_account_id = $sellerStripe ?: null;
        $this->event->stripe_product_id = $sp;
        $this->event->stripe_price_id = $price;
        $this->event->save();
    }

    private function ensureStripeCatalog(\App\Models\Product $product, ?string $connectedAccountId): array
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $opts = [];
        if (! empty($connectedAccountId)) {
            $opts['stripe_account'] = $connectedAccountId;
        }

        $league = $this->league;
        $name = $product->name ?: ($league->title.' registration');

        $stripeProductId = $league->stripe_product_id;
        if (! $stripeProductId) {
            $sp = \Stripe\Product::create(['name' => $name], $opts);
            $stripeProductId = $sp->id;
        }

        $stripePriceId = $league->stripe_price_id;
        $needNewPrice = true;

        if ($stripePriceId) {
            try {
                $price = \Stripe\Price::retrieve($stripePriceId, $opts);
                if ((int) $price->unit_amount === (int) $product->price_cents
                    && strtolower($price->currency) === strtolower($product->currency)
                    && $price->product === $stripeProductId) {
                    $needNewPrice = false;
                }
            } catch (\Throwable $e) {
                $needNewPrice = true;
            }
        }

        if ($needNewPrice) {
            $newPrice = \Stripe\Price::create([
                'unit_amount' => (int) $product->price_cents,
                'currency' => strtolower($product->currency),
                'product' => $stripeProductId,
            ], $opts);
            $stripePriceId = $newPrice->id;
        }

        $league->stripe_account_id = $connectedAccountId ?: null;
        $league->stripe_product_id = $stripeProductId;
        $league->stripe_price_id = $stripePriceId;
        $league->save();

        return [$stripeProductId, $stripePriceId];
    }

    /** NEW: Stripe catalog helper for Events */
    private function ensureStripeCatalogForEvent(\App\Models\Product $product, ?string $connectedAccountId): array
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $opts = [];
        if (! empty($connectedAccountId)) {
            $opts['stripe_account'] = $connectedAccountId;
        }

        $event = $this->event;
        $name = $product->name ?: ($event->title.' registration');

        $stripeProductId = $event->stripe_product_id;
        if (! $stripeProductId) {
            $sp = \Stripe\Product::create(['name' => $name], $opts);
            $stripeProductId = $sp->id;
        }

        $stripePriceId = $event->stripe_price_id;
        $needNewPrice = true;

        if ($stripePriceId) {
            try {
                $price = \Stripe\Price::retrieve($stripePriceId, $opts);
                if ((int) $price->unit_amount === (int) $product->price_cents
                    && strtolower($price->currency) === strtolower($product->currency)
                    && $price->product === $stripeProductId) {
                    $needNewPrice = false;
                }
            } catch (\Throwable $e) {
                $needNewPrice = true;
            }
        }

        if ($needNewPrice) {
            $newPrice = \Stripe\Price::create([
                'unit_amount' => (int) $product->price_cents,
                'currency' => strtolower($product->currency),
                'product' => $stripeProductId,
            ], $opts);
            $stripePriceId = $newPrice->id;
        }

        return [$stripeProductId, $stripePriceId];
    }

    private function getPlatformSellerId(): int
    {
        $platformOwnerId = (int) (config('payments.platform_owner_user_id') ?? 0);
        $owner = $platformOwnerId
            ? \App\Models\User::find($platformOwnerId)
            : null;

        if (! $owner) {
            $owner = \App\Models\User::where('role', 'administrator')->orderBy('id')->first();
        }

        if (! $owner) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'title' => 'No administrator user found to own the platform Seller. Create an admin account or set payments.platform_owner_user_id.',
            ]);
        }

        $seller = \App\Models\Seller::firstOrCreate(
            ['owner_id' => $owner->id],
            [
                'name' => (config('app.name', 'ArcherDB').' — Platform'),
                'default_platform_fee_bps' => 0,
                'active' => true,
            ]
        );

        return $seller->id;
    }

    private function normalizeRole(null|string|\UnitEnum $role): string
    {
        if ($role instanceof \BackedEnum) {
            return strtolower((string) $role->value);
        }
        if ($role instanceof \UnitEnum) {
            return strtolower($role->name);
        }

        return strtolower((string) ($role ?? 'standard'));
    }

    private function asYmd($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function toCents(null|string|int|float $input): ?int
    {
        if ($input === null || $input === '') {
            return null;
        }
        $s = str_replace(['$', ' ', ','], '', trim((string) $input));
        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $s)) {
            return null;
        }
        $neg = $s[0] === '-';
        if ($neg) {
            $s = substr($s, 1);
        }
        $parts = explode('.', $s, 2);
        $dollars = (int) ($parts[0] ?: 0);
        $centsPart = isset($parts[1]) ? substr(str_pad($parts[1], 2, '0'), 0, 2) : '00';
        $cents = $dollars * 100 + (int) $centsPart;

        return $neg ? -$cents : $cents;
    }

    public function save(): void
    {
        // Authorize
        try {
            Gate::authorize('update', $this->event);
        } catch (\Throwable $e) {
            if ($this->league) {
                Gate::authorize('update', $this->league);
            }
        }

        $typeVal = $this->league ? ($this->league->type->value ?? $this->league->type) : null;
        $isOpenLeague = $typeVal === 'open';
        $isClosedLeague = $typeVal === 'closed';

        // For non-league events, enable billing (you can restrict by $this->event->kind if desired)
        $isBillableEvent = ! $this->league; // any non-league event can be billable (Step 7)
        // If you prefer to restrict: $isBillableEvent = !$this->league && in_array($this->event->kind, ['single.day','multi.day'], true);

        // Validate basics
        $this->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'registration_url' => [$isOpenLeague ? 'required' : 'nullable', 'url', 'max:255'],
            'content_html' => ['nullable', 'string'],
            'is_published' => ['boolean'],
            'registration_start_date' => [($isClosedLeague ? 'required' : 'nullable'), 'date'],
            'registration_end_date' => [($isClosedLeague ? 'required' : 'nullable'), 'date'],
            // price/currency required for CLOSED league or billable events
            'price_dollars' => [($isClosedLeague || $isBillableEvent) ? 'required' : 'nullable', 'string', 'max:20'],
            'currency' => [($isClosedLeague || $isBillableEvent) ? 'required' : 'nullable', 'string', 'size:3'],
        ], [
            'registration_url.required' => 'External URL is required for open events.',
            'price_dollars.required' => 'Price is required.',
        ]);

        // Convert dollars → cents as needed
        if ($isClosedLeague && $this->league) {
            $cents = $this->toCents($this->price_dollars);
            if ($cents === null || $cents < 100) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'price_dollars' => 'Enter a valid price of at least $1.00 (e.g., 150.00).',
                ]);
            }
            $this->price_cents = $cents;
        } elseif ($isBillableEvent) {
            $cents = $this->toCents($this->price_dollars);
            if ($this->is_published && ($cents === null || $cents < 100)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'price_dollars' => 'Enter a valid price of at least $1.00 (e.g., 25.00).',
                ]);
            }
            $this->price_cents = $cents;
        } else {
            $this->price_cents = null;
        }

        // Persist EventInfo
        $info = $this->event->info ?: new EventInfo(['event_id' => $this->event->id]);
        $info->fill([
            'title' => $this->title ?: ($this->league?->title ?? $this->event->title),
            'registration_url' => $this->registration_url,
            'content_html' => $this->content_html,
            'is_published' => $this->is_published,
        ]);
        $info->save();
        $this->info = $info;

        // Persist Event dates; mirror to League if present
        $start = $this->registration_start_date
            ? Carbon::createFromFormat('Y-m-d', $this->registration_start_date)->toDateString()
            : null;

        $end = $this->registration_end_date
            ? Carbon::createFromFormat('Y-m-d', $this->registration_end_date)->toDateString()
            : null;

        $this->event->starts_on = $start;
        $this->event->ends_on = $end;
        $this->event->save();

        if ($this->league) {
            $this->league->registration_start_date = $start;
            $this->league->registration_end_date = $end;
            if ($isClosedLeague) {
                $this->league->price_cents = $this->price_cents;
                $this->league->currency = strtoupper($this->currency ?: 'USD');
            } else {
                $this->league->price_cents = null;
            }
            $this->league->save();
        } elseif ($isBillableEvent) {
            // Persist event price + currency and upsert product/stripe artifacts
            $this->event->price_cents = $this->price_cents;
            $this->event->currency = strtoupper($this->currency ?: 'USD');
            $this->event->save();
            $this->upsertProductForEvent();
        }

        // Stripe product (closed league only, preserving your existing flow)
        if ($isClosedLeague && $this->league) {
            $this->upsertProductForLeague();
        }

        $this->dispatch('toast', type: 'success', message: 'Event info saved.');
    }

    public function uploadBanner(): void
    {
        try {
            Gate::authorize('update', $this->event);
        } catch (\Throwable $e) {
            if ($this->league) {
                Gate::authorize('update', $this->league);
            }
        }

        $this->validate(['banner' => ['required', 'image', 'max:8192', 'mimes:jpg,jpeg,png,webp,avif']]);

        $tmp = $this->banner->getRealPath();
        if (! $tmp || ! file_exists($tmp)) {
            $this->addError('banner', 'Upload failed.');

            return;
        }

        $raw = @file_get_contents($tmp);
        $src = $raw ? @imagecreatefromstring($raw) : null;
        if (! $src) {
            $path = "events/{$this->event->id}/banner.webp";
            \Storage::disk('public')->putFileAs("events/{$this->event->id}", $this->banner, 'banner.webp');
            $this->finalizeBanner($path);

            return;
        }

        $ow = imagesx($src);
        $oh = imagesy($src);
        $maxW = 1600;
        $scale = min(1, $maxW / max(1, $ow));
        $tw = max(1, (int) floor($ow * $scale));
        $th = max(1, (int) floor($oh * $scale));

        $dst = imagecreatetruecolor($tw, $th);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $ow, $oh);

        ob_start();
        $ok = function_exists('imagewebp') ? imagewebp($dst, null, 80) : false;
        if (! $ok) {
            $ok = imagejpeg($dst, null, 80);
        }
        $binary = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        if (! $ok || $binary === false) {
            $this->addError('banner', 'Could not process image.');

            return;
        }

        $ext = function_exists('imagewebp') ? 'webp' : 'jpg';
        $path = "events/{$this->event->id}/banner.{$ext}";

        \Storage::disk('public')->delete([
            "events/{$this->event->id}/banner.webp",
            "events/{$this->event->id}/banner.jpg",
        ]);
        \Storage::disk('public')->put($path, $binary, 'public');

        $this->finalizeBanner($path);
    }

    private function finalizeBanner(string $path): void
    {
        $info = $this->event->info ?: new EventInfo(['event_id' => $this->event->id]);
        $info->banner_path = $path;
        $info->save();
        $this->info = $info;
        $this->banner = null;
        $this->banner_url = \Storage::url($path);
        $this->dispatch('toast', type: 'success', message: 'Banner updated.');
    }

    public function removeBanner(): void
    {
        try {
            Gate::authorize('update', $this->event);
        } catch (\Throwable $e) {
            if ($this->league) {
                Gate::authorize('update', $this->league);
            }
        }

        if ($this->info?->banner_path) {
            \Storage::disk('public')->delete($this->info->banner_path);
            $this->info->update(['banner_path' => null]);
        }
        $this->banner_url = null;
        $this->dispatch('toast', type: 'success', message: 'Banner removed.');
    }
}; ?>

<section class="w-full">
    <div class="mx-auto max-w-5xl">
        <div class="mb-6">
            <h1 class="text-base font-semibold text-gray-900 dark:text-white">Event info: {{ $title ?: $event->title }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Upload a banner and write a rich info page for your event.</p>
        </div>

        {{-- Banner card --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-neutral-900">
            <div class="p-4 border-b border-gray-200 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Banner</h2>
                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Max ~8 MB. We’ll resize to 1600px wide and store efficiently (WebP/JPEG).</p>
            </div>
            <div class="p-4 flex items-start gap-4">
                <div class="w-56">
                    @if($banner_url)
                        <img src="{{ $banner_url }}" alt="Event banner" class="w-full rounded-lg ring-1 ring-black/5" />
                    @else
                        <div class="aspect-[16/9] w-full rounded-lg bg-neutral-100 dark:bg-neutral-800 ring-1 ring-black/5"></div>
                    @endif
                </div>
                <div class="flex-1">
                    <form wire:submit.prevent="uploadBanner" class="space-y-3">
                        <flux:input type="file" accept="image/*" wire:model="banner" :label="__('Upload banner')" />
                        @error('banner') <flux:text class="!text-red-600 !dark:text-red-400 text-sm">{{ $message }}</flux:text> @enderror

                        <div class="flex items-center gap-2">
                            <flux:button type="submit" variant="primary">Save banner</flux:button>
                            @if($banner_url)
                                <flux:button type="button" variant="ghost" wire:click="removeBanner">Remove</flux:button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Content + Registration config --}}
        <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-neutral-900">
            <div class="p-4 border-b border-gray-200 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Info content</h2>
            </div>

            <div class="p-4 space-y-4">
                <flux:input wire:model.defer="title" :label="__('Title')" type="text" placeholder="Optional — defaults to event title" />

                @php
                    $typeVal = $league ? ($league->type->value ?? $league->type) : null;
                    $isOpenLeague   = $typeVal === 'open';
                    $isClosedLeague = $typeVal === 'closed';
                    $isBillableEvent = !$league; // Step 7: non-league events can sell registrations
                @endphp

                {{-- OPEN League: external registration URL --}}
                @if($isOpenLeague)
                    <flux:input
                        wire:model.defer="registration_url"
                        :label="__('External registration URL')"
                        type="url"
                        placeholder="https://example.com/register"
                    />
                @else
                    {{-- For non-league or non-open events, allow optional link (not required) --}}
                    <flux:input
                        wire:model.defer="registration_url"
                        :label="__('Registration URL (optional)')"
                        type="url"
                        placeholder="https://example.com/register"
                    />
                @endif

                {{-- Registration window (UI → Event.starts_on/ends_on; mirrored to League if present) --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model.defer="registration_start_date"
                        :label="__('Registration opens')"
                        type="date"
                    />
                    <flux:input
                        wire:model.defer="registration_end_date"
                        :label="__('Registration closes')"
                        type="date"
                    />
                </div>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                    These dates control when registration-related UI appears across ArcherDB.
                </p>

                {{-- Pricing: CLOSED League OR billable non-league Event --}}
                @if($isClosedLeague || $isBillableEvent)
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input
                            wire:model.defer="price_dollars"
                            :label="__('Price (in USD)')"
                            type="text"
                            inputmode="decimal"
                            placeholder="25.00"
                        />
                        <flux:input
                            wire:model.defer="currency"
                            :label="__('Currency')"
                            type="text"
                            maxlength="3"
                        />
                    </div>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        Enter the price in dollars (minimum $1.00).
                    </p>
                @endif

                {{-- Quill WYSIWYG --}}
                <div>
                    <flux:label>Content</flux:label>

                    <script id="event-info-initial" type="application/json">
                        {!! json_encode($content_html ?? '') !!}
                    </script>

                    <div wire:ignore>
                        <div id="quill-editor"
                            class="rounded-md border border-neutral-300 dark:border-white/10 min-h-[220px]"></div>
                    </div>

                    <input id="content_html" type="hidden" wire:model="content_html" value="{{ $content_html }}">
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <flux:checkbox wire:model="is_published" />
                        <flux:label class="text-sm">Published</flux:label>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:button variant="primary" wire:click="save">Save</flux:button>
                        @if($league)
                          <flux:button as="a" href="{{ route('corporate.leagues.show', $league) }}" variant="ghost">Back to league</flux:button>
                        @else
                          <flux:button as="a" href="{{ route('events.info.edit', $event) }}" variant="ghost">Back</flux:button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Quill assets (CDN) --}}
    <link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>

    <style>
    #quill-editor .ql-toolbar { border-radius: .375rem .375rem 0 0; }
    #quill-editor .ql-container { border-radius: 0 0 .375rem .375rem; min-height: 220px; }
    .ql-snow .ql-toolbar { background-color: #ffffff; border-color: #e5e7eb; }
    .ql-snow .ql-container { background-color: #ffffff; border-color: #e5e7eb; }
    .ql-snow .ql-editor   { color: #0a0a0a; }
    .ql-editor::placeholder { color: #9ca3af; }
    .ql-snow a { color: #4f46e5; }

    .dark .ql-snow .ql-toolbar { background-color: #0b0f19; border-color: rgba(255,255,255,0.08); }
    .dark .ql-snow .ql-container { background-color: #0b0f19; border-color: rgba(255,255,255,0.08); }
    .dark .ql-snow .ql-editor { color: #e5e7eb; }
    .dark .ql-editor::placeholder { color: #9ca3af; }
    .dark .ql-snow .ql-stroke { stroke: #e5e7eb; }
    .dark .ql-snow .ql-fill { fill: #e5e7eb; }
    .dark .ql-snow a { color: #8b93ff; }
    </style>

    <script>
    (function () {
      function initialHTML() {
        const script = document.getElementById('event-info-initial');
        if (script) {
          try { return JSON.parse(script.textContent || ''); } catch (_) {}
        }
        const hidden = document.getElementById('content_html');
        return hidden ? hidden.value || '' : '';
      }

      function initQuillOnce() {
        if (!window.Quill) { setTimeout(initQuillOnce, 50); return; }

        const el = document.getElementById('quill-editor');
        const hidden = document.getElementById('content_html');
        if (!el || !hidden || el.__quillInited) return;

        const quill = new Quill(el, {
          theme: 'snow',
          placeholder: 'Write your event info...',
          modules: {
            toolbar: [
              [{ header: [2,3,false] }],
              ['bold','italic','underline','strike'],
              [{ list: 'ordered' }, { list: 'bullet' }],
              [{ align: [] }],
              ['link','clean']
            ]
          }
        });

        el.__quillInited = true;
        window.__eventQuill = quill;

        const html = initialHTML();
        if (html && typeof quill.clipboard?.dangerouslyPasteHTML === 'function') {
          quill.clipboard.dangerouslyPasteHTML(html);
        } else if (html) {
          quill.root.innerHTML = html;
        }

        quill.on('text-change', function () {
          const current = el.querySelector('.ql-editor')?.innerHTML ?? '';
          if (hidden.value !== current) {
            hidden.value = current;
            hidden.dispatchEvent(new Event('input'));
          }
        });
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initQuillOnce);
      } else {
        initQuillOnce();
      }

      document.addEventListener('livewire:load', () => setTimeout(initQuillOnce, 0));
      document.addEventListener('livewire:navigated', () => setTimeout(initQuillOnce, 0));
    })();
    </script>
</section>
