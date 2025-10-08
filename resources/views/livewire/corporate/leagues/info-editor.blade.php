<?php

use App\Models\League;
use App\Models\LeagueInfo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;

new class extends Component
{
    use \Livewire\WithFileUploads;

    public League $league;

    public ?LeagueInfo $info = null;

    public string $title = '';

    public ?string $registration_url = null; // For OPEN leagues only

    public string $content_html = '';

    public bool $is_published = false;

    public ?string $registration_start_date = null;

    public ?string $registration_end_date = null;

    public $banner; // TemporaryUploadedFile

    public ?string $banner_url = null;

    // Payment (CLOSED leagues)
    public ?int $price_cents = null;          // persisted cents

    public ?string $price_dollars = null;     // UI field (e.g., "150.00")

    public string $currency = 'USD';

    public function mount(League $league): void
    {
        Gate::authorize('update', $league);

        $this->league = $league->load('info');
        $this->info = $league->info ?: new LeagueInfo(['league_id' => $league->id]);

        $this->title = (string) ($this->info->title ?? $league->title);
        $this->registration_url = $this->info->registration_url ?? null;
        $this->content_html = (string) ($this->info->content_html ?? '');
        $this->is_published = (bool) ($this->info->is_published ?? false);

        // Dates → YYYY-MM-DD strings for <input type="date">
        $this->registration_start_date = $this->asYmd($league->registration_start_date);
        $this->registration_end_date = $this->asYmd($league->registration_end_date);

        // Payment fields
        $this->price_cents = $league->price_cents;
        $this->price_dollars = $league->price_cents !== null
            ? number_format(((int) $league->price_cents) / 100, 2, '.', '')
            : null;
        $this->currency = $league->currency ?: 'USD';

        if ($this->info?->banner_path && Storage::disk('public')->exists($this->info->banner_path)) {
            $this->banner_url = Storage::url($this->info->banner_path);
        }
    }

    private function upsertProductForLeague(): void
    {
        $isClosed = ($this->league->type->value ?? $this->league->type) === 'closed';
        if (! $isClosed) {
            return;
        }

        $owner = $this->league->owner()->first();
        $role = $this->normalizeRole($owner?->role);

        // Determine seller + platform fee
        $sellerId = null;
        $feeBps = 0;
        $sellerStripe = null;

        if ($role === 'corporate') {
            $seller = \App\Models\Seller::firstOrCreate(
                ['owner_id' => $owner->id],
                ['name' => $owner->name.' — Organizer']
            );

            // If they haven't onboarded yet, we can't create catalog on their account
            if (empty($seller->stripe_account_id)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'title' => 'This is a paid (closed) event, but the organizer is not connected to Stripe yet. Please complete Stripe onboarding first.',
                ]);
            }

            $sellerId = $seller->id;
            $sellerStripe = $seller->stripe_account_id;
            $feeBps = (int) ($seller->default_platform_fee_bps ?? config('payments.default_platform_fee_bps', 500));
        } else {
            // Admin/internal: platform seller, 0 bps (platform keeps full)
            $sellerId = $this->getPlatformSellerId();
            $feeBps = 0;
            $sellerStripe = null; // create catalog on platform account
        }

        // Upsert our local Product record (polymorphic)
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
            'metadata' => ['league_public_uuid' => $this->league->public_uuid],
            'is_active' => true,
        ]);

        if (is_null($product->platform_fee_bps)) {
            $product->platform_fee_bps = $feeBps;
        }

        $product->save();

        // ⬇️ Ensure Stripe Product/Price exist on the correct account & persist on league
        $this->ensureStripeCatalog($product, $sellerStripe);
    }

    private function ensureStripeCatalog(\App\Models\Product $product, ?string $connectedAccountId): array
    {
        // Create/reuse Stripe Product & Price on the correct account and
        // persist their IDs on the leagues table.
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $opts = [];
        if (! empty($connectedAccountId)) {
            $opts['stripe_account'] = $connectedAccountId; // direct charge account
        }

        $league = $this->league;
        $name = $product->name ?: ($league->title.' registration');

        // 1) Product
        $stripeProductId = $league->stripe_product_id;
        if (! $stripeProductId) {
            $sp = \Stripe\Product::create(['name' => $name], $opts);
            $stripeProductId = $sp->id;
        }

        // 2) Price (re-use if amount/currency/product matches; else create new)
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

        // 3) Persist on leagues table for later checkout usage
        $league->stripe_account_id = $connectedAccountId ?: null;
        $league->stripe_product_id = $stripeProductId;
        $league->stripe_price_id = $stripePriceId;
        $league->save();

        return [$stripeProductId, $stripePriceId];
    }

    /**
     * Returns the Seller ID to use for platform/internal sales.
     */
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
        $s = trim((string) $input);
        // Remove $ symbol, spaces, and thousands separators
        $s = str_replace(['$', ' ', ','], '', $s);

        // Allow digits with optional . and up to 2 decimals
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
        Gate::authorize('update', $this->league);

        $type = ($this->league->type->value ?? $this->league->type);
        $isOpen = $type === 'open';
        $isClosed = $type === 'closed';

        // Validate basics; price in dollars (string) for CLOSED
        $this->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'registration_url' => [$isOpen ? 'required' : 'nullable', 'url', 'max:255'],
            'content_html' => ['nullable', 'string'],
            'is_published' => ['boolean'],
            'registration_start_date' => [$isClosed ? 'required' : 'nullable', 'date'],
            'registration_end_date' => [$isClosed ? 'required' : 'nullable', 'date'],
            'price_dollars' => [$isClosed ? 'required' : 'nullable', 'string', 'max:20'],
            'currency' => [$isClosed ? 'required' : 'nullable', 'string', 'size:3'],
        ], [
            'registration_url.required' => 'External URL is required for open events.',
            'price_dollars.required' => 'Price is required for closed events.',
        ]);

        // Convert dollars → cents (and enforce min $1.00 if closed)
        if ($isClosed) {
            $cents = $this->toCents($this->price_dollars);
            if ($cents === null || $cents < 100) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'price_dollars' => 'Enter a valid price of at least $1.00 (e.g., 150.00).',
                ]);
            }
            $this->price_cents = $cents;
        } else {
            $this->price_cents = null;
        }

        // Persist Info
        $info = $this->league->info ?: new LeagueInfo(['league_id' => $this->league->id]);
        $info->fill([
            'title' => $this->title ?: $this->league->title,
            'registration_url' => $this->registration_url,
            'content_html' => $this->content_html,
            'is_published' => $this->is_published,
        ]);
        $info->save();
        $this->info = $info;

        // Persist League dates + payment fields
        $start = $this->registration_start_date
            ? Carbon::createFromFormat('Y-m-d', $this->registration_start_date)->toDateString()
            : null;

        $end = $this->registration_end_date
            ? Carbon::createFromFormat('Y-m-d', $this->registration_end_date)->toDateString()
            : null;

        $this->league->registration_start_date = $start;
        $this->league->registration_end_date = $end;

        if ($isClosed) {
            $this->league->price_cents = $this->price_cents;
            $this->league->currency = strtoupper($this->currency ?: 'USD');
        } else {
            $this->league->price_cents = null;
        }

        $this->league->save();

        if ($isClosed) {
            $this->upsertProductForLeague();
        }

        $this->dispatch('toast', type: 'success', message: 'League info saved.');
    }

    public function uploadBanner(): void
    {
        Gate::authorize('update', $this->league);
        $this->validate(['banner' => ['required', 'image', 'max:8192', 'mimes:jpg,jpeg,png,webp,avif']]);

        $tmp = $this->banner->getRealPath();
        if (! $tmp || ! file_exists($tmp)) {
            $this->addError('banner', 'Upload failed.');

            return;
        }

        $raw = @file_get_contents($tmp);
        $src = $raw ? @imagecreatefromstring($raw) : null;
        if (! $src) {
            $path = "leagues/{$this->league->id}/banner.webp";
            \Storage::disk('public')->putFileAs("leagues/{$this->league->id}", $this->banner, 'banner.webp');
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
        $path = "leagues/{$this->league->id}/banner.{$ext}";

        \Storage::disk('public')->delete([
            "leagues/{$this->league->id}/banner.webp",
            "leagues/{$this->league->id}/banner.jpg",
        ]);
        \Storage::disk('public')->put($path, $binary, 'public');

        $this->finalizeBanner($path);
    }

    private function finalizeBanner(string $path): void
    {
        $info = $this->league->info ?: new LeagueInfo(['league_id' => $this->league->id]);
        $info->banner_path = $path;
        $info->save();
        $this->info = $info;
        $this->banner = null;
        $this->banner_url = \Storage::url($path);
        $this->dispatch('toast', type: 'success', message: 'Banner updated.');
    }

    public function removeBanner(): void
    {
        Gate::authorize('update', $this->league);

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
            <h1 class="text-base font-semibold text-gray-900 dark:text-white">League info: {{ $league->title }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Upload a banner and write a rich info page for your league.</p>
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
                        <img src="{{ $banner_url }}" alt="League banner" class="w-full rounded-lg ring-1 ring-black/5" />
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
                <flux:input wire:model.defer="title" :label="__('Title')" type="text" placeholder="Optional — defaults to league title" />

                @php
                    $typeVal = ($league->type->value ?? $league->type);
                    $isOpenLeague = $typeVal === 'open';
                    $isClosedLeague = $typeVal === 'closed';
                @endphp

                {{-- OPEN: external registration URL --}}
                @if($isOpenLeague)
                    <flux:input
                        wire:model.defer="registration_url"
                        :label="__('External registration URL')"
                        type="url"
                        placeholder="https://example.com/register"
                    />
                @endif

                {{-- Registration window (both types; required for CLOSED) --}}
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

                {{-- CLOSED: price & currency --}}
                @if($isClosedLeague)
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input
                            wire:model.defer="price_dollars"
                            :label="__('Price (in USD)')"
                            type="text"
                            inputmode="decimal"
                            placeholder="150.00"
                        />
                        <flux:input
                            wire:model.defer="currency"
                            :label="__('Currency')"
                            type="text"
                            maxlength="3"
                        />
                    </div>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        Enter the price in dollars (e.g., 150.00).
                    </p>
                @endif

                {{-- Quill WYSIWYG --}}
                <div>
                    <flux:label>Content</flux:label>

                    <script id="league-info-initial" type="application/json">
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
                        <flux:button as="a" href="{{ route('corporate.leagues.show', $league) }}" variant="ghost">Back to league</flux:button>
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
        const script = document.getElementById('league-info-initial');
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
          placeholder: 'Write your league info...',
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
        window.__leagueQuill = quill;

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
