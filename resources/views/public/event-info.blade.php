<x-layouts.app :title="($event->info->title ?? $event->title)">
    @php
        use Illuminate\Support\Carbon;

        // Linked league (optional)
        $league = $event->league ?? null;

        // Banner URL: prefer event info, fall back to league info
        $bannerPath = $event->info->banner_path ?? ($league->info->banner_path ?? null);
        $bannerUrl  = $bannerPath ? \Storage::disk('public')->exists($bannerPath) ? \Storage::url($bannerPath) : null : null;

        // Content HTML: prefer event info, then league info
        $contentHtml = $event->info->content_html ?? ($league->info->content_html ?? '');

        // Registration URL (external link, e.g. open league)
        $registrationUrl = $event->info->registration_url ?? ($league->info->registration_url ?? null);

        // Dates (registration window from Event)
        $start = $event->starts_on ? Carbon::parse($event->starts_on) : null;
        $end   = $event->ends_on   ? Carbon::parse($event->ends_on)   : null;
        $today = Carbon::now(config('app.timezone'))->startOfDay();

        $window = 'anytime';
        if ($start || $end) {
            if ($start && $today->lt($start->copy()->startOfDay())) {
                $window = 'before';
            } elseif ($end && $today->gt($end->copy()->endOfDay())) {
                $window = 'after';
            } else {
                $window = 'during';
            }
        }

        // Determine billing mode
        $typeVal = $league ? ($league->type->value ?? $league->type) : null;
        $isOpenLeague   = $league && $typeVal === 'open';
        $isClosedLeague = $league && $typeVal === 'closed';
        $isBillableEvent = !$league; // Step 7: non-league events can sell registrations

        // Price & currency
        if ($isClosedLeague) {
            $priceCents = $league->price_cents;
            $currency   = strtoupper($league->currency ?? 'USD');
        } else {
            $priceCents = $event->price_cents;
            $currency   = strtoupper($event->currency ?? 'USD');
        }
        $priceDisplay = $priceCents !== null ? number_format($priceCents / 100, 2) : null;

        // Already registered?
        $alreadyRegistered = false;
        if (auth()->check()) {
            $uid   = auth()->id();
            $email = auth()->user()->email;

            $alreadyRegistered = \App\Models\LeagueParticipant::query()
                ->when($league, fn($q) => $q->where('league_id', $league->id))
                ->when(!$league, fn($q) => $q->where('event_id', $event->id))
                ->where(function ($q) use ($uid, $email) {
                    $q->where('user_id', $uid)
                      ->orWhere(function ($qq) use ($email) {
                          if ($email) $qq->where('email', $email);
                      });
                })
                ->exists();
        }

        $location = $event->location ?? ($league->location ?? '—');
        $fmt = fn($d) => $d ? $d->format('Y-m-d') : null;

        // Can we run checkout?
        $eventCheckoutReady  = $isBillableEvent && $event->stripe_price_id && $priceCents;
        $leagueCheckoutReady = $isClosedLeague && $league->stripe_price_id && $priceCents;
    @endphp

    <div class="mx-auto w-full max-w-5xl space-y-6">

        {{-- Banner --}}
        @if($bannerUrl)
            <div class="overflow-hidden rounded-xl ring-1 ring-black/5">
                <img src="{{ $bannerUrl }}" alt="{{ $event->title }} banner" class="h-64 w-full object-cover sm:h-80 md:h-64">
            </div>
        @endif

        {{-- Errors / flash --}}
        @if ($errors->any())
            <div class="rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-900/20 dark:text-rose-200">
                <div class="font-semibold mb-1">Unable to start registration:</div>
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-900/20 dark:text-rose-200">
                {{ session('error') }}
            </div>
        @endif

        {{-- Heading & meta --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-white/10 dark:bg-neutral-900">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ $event->info->title ?? $event->title }}
                    </h1>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ $location }} •
                        {{ $league ? (ucfirst($league->type->value ?? $league->type).' League') : 'Event' }} •
                        Starts {{ $fmt($start) ?: '—' }}
                        @if($end) • Ends {{ $fmt($end) }} @endif
                    </p>
                </div>

                {{-- Registration status / CTA --}}
                <div class="mt-2 sm:mt-0">
                    @if($alreadyRegistered)
                        <div class="inline-flex items-center gap-2 rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-200 dark:ring-emerald-900/40">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-7.5 9.5a.75.75 0 01-1.127.062l-3.5-3.5a.75.75 0 011.06-1.06l2.88 2.88 6.98-8.844a.75.75 0 011.064-.09z"/>
                            </svg>
                            Registered for this event
                        </div>
                        @if(isset($start) || isset($end))
                            <div class="mt-1 text-right text-xs text-neutral-500 dark:text-neutral-400">
                                @if(!empty($start)) Opens {{ $fmt($start) }}. @endif
                                @if(!empty($end)) Closes {{ $fmt($end) }}. @endif
                            </div>
                        @endif
                    @else
                        {{-- CLOSED league: on-site registration + checkout (keep existing flow) --}}
                        @if($isClosedLeague)
                            @if($window === 'during')
                                @if($leagueCheckoutReady)
                                    @auth
                                        <form method="POST" action="{{ route('checkout.league.start', ['league' => $league->public_uuid]) }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-indigo-500 dark:hover:bg-indigo-400"
                                            >
                                                Register now — {{ $currency }} {{ $priceDisplay }}
                                            </button>
                                        </form>
                                    @else
                                        <a href="{{ route('login') }}?redirect={{ urlencode(request()->fullUrl()) }}"
                                           class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                                            Log in to register — {{ $currency }} {{ $priceDisplay }}
                                        </a>
                                    @endauth
                                @else
                                    <div class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-inset ring-amber-200 dark:bg-amber-900/20 dark:text-amber-200 dark:ring-amber-900/40">
                                        Registration product isn’t configured yet. Set a price on the info page.
                                    </div>
                                @endif
                                @if(isset($start) || isset($end))
                                    <div class="mt-1 text-right text-xs text-neutral-500 dark:text-neutral-400">
                                        @if(!empty($start)) Opens {{ $fmt($start) }}. @endif
                                        @if(!empty($end)) Closes {{ $fmt($end) }}. @endif
                                    </div>
                                @endif
                            @elseif($window === 'before')
                                <div class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-inset ring-amber-200 dark:bg-amber-900/20 dark:text-amber-200 dark:ring-amber-900/40">
                                    Registration opens {{ $fmt($start ?? null) }}.
                                </div>
                            @elseif($window === 'after')
                                <div class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-200 dark:bg-rose-900/20 dark:text-rose-200 dark:ring-rose-900/40">
                                    Registration closed{{ !empty($end) ? ' on '.$fmt($end) : '' }}.
                                </div>
                            @else
                                {{-- No dates configured → allow immediate registration if priced --}}
                                @if($leagueCheckoutReady)
                                    @auth
                                        <form method="POST" action="{{ route('checkout.league.start', ['league' => $league->public_uuid]) }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                                            >
                                                Register — {{ $currency }} {{ $priceDisplay }}
                                            </button>
                                        </form>
                                    @else
                                        <a href="{{ route('login') }}?redirect={{ urlencode(request()->fullUrl()) }}"
                                           class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                                            Log in to register — {{ $currency }} {{ $priceDisplay }}
                                        </a>
                                    @endauth
                                @endif
                            @endif
                        @endif

                        {{-- OPEN league: external URL --}}
                        @if($isOpenLeague)
                            @if($window === 'during')
                                @if($registrationUrl)
                                    <a href="{{ $registrationUrl }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                                        Register now
                                        <svg class="ml-2 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" clip-rule="evenodd"
                                                  d="M12.293 2.293a1 1 0 011.414 0l4 4a.997.997 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a.997.997 0 010-1.414l8-8zM5 13l2 2 8-8-2-2-8 8z"/>
                                        </svg>
                                    </a>
                                @else
                                    <div class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-200 dark:ring-emerald-900/40">
                                        Registration is open.
                                        @if(!empty($end)) Closes {{ $fmt($end) }}. @endif
                                    </div>
                                @endif
                                @if(isset($start) || isset($end))
                                    <div class="mt-1 text-right text-xs text-neutral-500 dark:text-neutral-400">
                                        @if(!empty($start)) Opens {{ $fmt($start) }}. @endif
                                        @if(!empty($end)) Closes {{ $fmt($end) }}. @endif
                                    </div>
                                @endif
                            @elseif($window === 'before')
                                <div class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-inset ring-amber-200 dark:bg-amber-900/20 dark:text-amber-200 dark:ring-amber-900/40">
                                    Registration opens {{ $fmt($start ?? null) }}.
                                </div>
                            @elseif($window === 'after')
                                <div class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-200 dark:bg-rose-900/20 dark:text-rose-200 dark:ring-rose-900/40">
                                    Registration closed{{ !empty($end) ? ' on '.$fmt($end) : '' }}.
                                </div>
                            @else
                                {{-- No dates configured --}}
                                @if($registrationUrl)
                                    <a href="{{ $registrationUrl }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                                        Register
                                    </a>
                                @endif
                            @endif
                        @endif

                        {{-- BILLABLE non-league EVENT: internal checkout for event --}}
                        @if($isBillableEvent)
                            @if($window === 'during' || $window === 'anytime')
                                @if($eventCheckoutReady)
                                    @auth
                                        <form method="POST" action="{{ route('checkout.start.event', $event->public_uuid) }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-indigo-500 dark:hover:bg-indigo-400"
                                            >
                                                Register now — {{ $currency }} {{ $priceDisplay }}
                                            </button>
                                        </form>
                                    @else
                                        <a href="{{ route('login') }}?redirect={{ urlencode(request()->fullUrl()) }}"
                                           class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                                            Log in to register — {{ $currency }} {{ $priceDisplay }}
                                        </a>
                                    @endauth
                                    @if(isset($start) || isset($end))
                                        <div class="mt-1 text-right text-xs text-neutral-500 dark:text-neutral-400">
                                            @if(!empty($start)) Opens {{ $fmt($start) }}. @endif
                                            @if(!empty($end)) Closes {{ $fmt($end) }}. @endif
                                        </div>
                                    @endif
                                @else
                                    <div class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-inset ring-amber-200 dark:bg-amber-900/20 dark:text-amber-200 dark:ring-amber-900/40">
                                        Registration product isn’t configured yet. Set a price on the event info page.
                                    </div>
                                @endif
                            @elseif($window === 'before')
                                <div class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-inset ring-amber-200 dark:bg-amber-900/20 dark:text-amber-200 dark:ring-amber-900/40">
                                    Registration opens {{ $fmt($start ?? null) }}.
                                </div>
                            @elseif($window === 'after')
                                <div class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-200 dark:bg-rose-900/20 dark:text-rose-200 dark:ring-rose-900/40">
                                    Registration closed{{ !empty($end) ? ' on '.$fmt($end) : '' }}.
                                </div>
                            @endif
                        @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- Content --}}
        <div class="prose prose-neutral max-w-none rounded-xl border border-neutral-200 bg-white p-5 dark:prose-invert dark:border-white/10 dark:bg-neutral-900">
            {!! $contentHtml !!}
        </div>

        {{-- Optional: small facts footer (only if a league is linked) --}}
        @if($league)
            <div class="rounded-xl border border-neutral-200 bg-white p-4 text-sm text-neutral-600 dark:border-white/10 dark:bg-neutral-900 dark:text-neutral-300">
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <div><span class="font-medium text-neutral-900 dark:text-neutral-100">Ends/day:</span> {{ $league->ends_per_day }}</div>
                    <div><span class="font-medium text-neutral-900 dark:text-neutral-100">Arrows/end:</span> {{ $league->arrows_per_end }}</div>
                    <div><span class="font-medium text-neutral-900 dark:text-neutral-100">X-ring value:</span> {{ $league->x_ring_value }}</div>
                    <div><span class="font-medium text-neutral-900 dark:text-neutral-100">Lanes:</span> {{ $league->lanes_count }} ({{ $league->lane_breakdown }})</div>
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
