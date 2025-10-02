{{-- resources/views/landing/index.blade.php --}}
@extends('landing.layouts.layout')

@section('title', 'ArcherDB — Archery training & league scoring')

@push('meta')
  <meta name="description" content="ArcherDB helps archers train smarter, score faster, and run leagues with live results." />
@endpush

@section('content')
  {{-- HERO --}}
  <section class="relative overflow-hidden">
    {{-- Background image --}}
    <div class="absolute inset-0 -z-10">
      <img 
        src="{{ asset('img/hero-bg.png') }}" 
        alt="Archery background" 
        class="h-full w-full object-cover"
      >
      {{-- Optional overlay gradient to improve text contrast --}}
      <div class="absolute inset-0 bg-gradient-to-b from-black/40 via-black/20 to-white dark:from-black/60 dark:via-black/40 dark:to-neutral-950"></div>
    </div>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-20 sm:py-28 relative">
      <div class="max-w-3xl">
        <p class="mb-3 inline-flex items-center gap-2 rounded-full border border-neutral-200 px-3 py-1 text-xs font-medium text-neutral-100 dark:border-neutral-800 dark:text-neutral-300 bg-black/30 backdrop-blur-sm">
          <span class="h-2 w-2 rounded-full bg-primary-400"></span>
          Public beta in progress
        </p>
        <h1 class="text-4xl font-extrabold tracking-tight sm:text-6xl text-white">
          Train smarter. <span class="text-primary-400">Score faster.</span> Compete together.
        </h1>
        <p class="mt-6 text-base sm:text-lg text-neutral-200 dark:text-neutral-300">
          Personal training, rich stats, and league scoring—built for clubs, coaches, and competitive archers.
        </p>
        <div class="mt-8 flex flex-col sm:flex-row gap-3">
          <flux:button as="a" href="{{ route('register') }}" variant="primary" color="red" icon="check-circle">
              Get Started Free
          </flux:button>
          <flux:button as="a" href="#features" variant="primary" color="blue" icon="information-circle">
              Learn More
          </flux:button>
        </div>
      </div>
    </div>
  </section>


  {{-- FEATURES --}}
  <section id="features" class="py-16 sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="mx-auto max-w-2xl text-center">
        <h2 class="text-3xl font-bold sm:text-4xl">Everything for archers & leagues</h2>
        <p class="mt-3 text-neutral-600 dark:text-neutral-300">From solo practice to club nights and corporate leagues.</p>
      </div>

      <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
          ['title' => 'Personal Training', 'desc' => 'Per-end scoring, X/10/9 counts, auto summaries.'],
          ['title' => 'Live League Scoring', 'desc' => 'Kiosk & personal modes with public boards.'],
          ['title' => 'Flexible Targets', 'desc' => 'WA 40cm triple-spot, single-spot, compound/recurve.'],
          ['title' => 'Stats & Insights', 'desc' => 'PRs, groupings, X-rate, trends, distance splits.'],
          ['title' => 'Mobile-Ready', 'desc' => 'Phones, tablets, and kiosk screens.'],
          ['title' => 'Team Tools', 'desc' => 'Events, lanes, registrations, results.'],
        ] as $f)
          <div class="rounded-2xl border border-neutral-200 p-6 dark:border-neutral-800">
            <div class="mb-3 h-9 w-9 rounded-xl bg-primary-600/10 dark:bg-primary-500/10"></div>
            <h3 class="font-semibold">{{ $f['title'] }}</h3>
            <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">{{ $f['desc'] }}</p>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- PARTNERS --}}
  <section id="partners" class="py-10 sm:py-12 bg-neutral-50 dark:bg-neutral-900">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="mx-auto max-w-2xl text-center">
        <h2 class="text-sm font-semibold tracking-wider text-neutral-500 dark:text-neutral-400 uppercase">
          Trusted by clubs & partners
        </h2>
      </div>

      {{-- Logo rail (grayscale, toned down opacity; lighten on hover) --}}
      <div class="mt-8 grid grid-cols-2 gap-x-8 gap-y-8 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ([
          ['src'=>'/img/partners/lancaster_archery_academy.jpg','alt'=>'Lancaster Archery Academy'],
          ['src'=>'/img/partners/lancaster_archery_supply.jpg','alt'=>'Lancaster Archery Supply'],
          ['src'=>'/img/partners/easton.jpg','alt'=>'Easton Archery'],
          ['src'=>'/img/partners/lancaster_archery_academy.jpg','alt'=>'Lancaster Archery Academy'],
          ['src'=>'/img/partners/lancaster_archery_supply.jpg','alt'=>'Lancaster Archery Supply'],
          ['src'=>'/img/partners/easton.jpg','alt'=>'Easton Archery'],
        ] as $p)
          <div class="flex items-center justify-center">
            <img
              src="{{ $p['src'] }}"
              alt="{{ $p['alt'] }}"
              class="max-h-full w-auto grayscale opacity-60 hover:opacity-90 transition-opacity duration-200
                    dark:brightness-125"
            />
          </div>
        @endforeach
      </div>

      {{-- Optional: fine print --}}
      <p class="mt-6 text-center text-xs text-neutral-500 dark:text-neutral-400">
        Logos shown are for placement only and may include current or prospective partners.
      </p>
    </div>
  </section>


  {{-- CTA --}}
  <section class="relative py-16 sm:py-24 overflow-hidden">
    <div class="absolute inset-0 -z-10">
      {{-- Light mode: top neutral-50 → bottom white --}}
      {{-- Dark mode: top neutral-900 → bottom neutral-950 --}}
      <div class="h-full w-full bg-gradient-to-b from-neutral-50 to-white dark:from-neutral-900 dark:to-neutral-950"></div>
    </div>

    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center">
      <h2 class="text-3xl font-bold sm:text-4xl">Ready to shoot higher scores?</h2>
      <p class="mt-3 text-neutral-600 dark:text-neutral-300">
        Create your account and start a session in under a minute.
      </p>
      <div class="mt-8">
        <flux:button as="a" href="{{ route('register') }}" variant="primary" color="yellow" icon="hand-thumb-up">
          Create Free Account
        </flux:button>
      </div>
    </div>
  </section>


  {{-- PRICING (optional placeholder) --}}
  <section id="pricing" class="py-16 sm:py-24">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
      <div class="mx-auto max-w-2xl text-center">
        <h2 class="text-3xl font-bold sm:text-4xl">Simple pricing</h2>
        <p class="mt-3 text-neutral-600 dark:text-neutral-300">Start free. Upgrade when your club is ready.</p>
      </div>
      <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
          ['name'=>'Personal','price'=>'Free','features'=>['Unlimited sessions','Basic stats','Mobile scoring']],
          ['name'=>'Club','price'=>'$29/mo','features'=>['Leagues & lanes','Live boards','Registrations']],
          ['name'=>'Enterprise','price'=>'Contact','features'=>['SLA support','SSO / SAML','Custom reporting']],
        ] as $p)
          <div class="rounded-2xl border border-neutral-200 p-6 dark:border-neutral-800">
            <h3 class="text-lg font-semibold">{{ $p['name'] }}</h3>
            <p class="mt-2 text-2xl font-extrabold">{{ $p['price'] }}</p>
            <ul class="mt-4 space-y-2 text-sm text-neutral-600 dark:text-neutral-300">
              @foreach ($p['features'] as $feat)
                <li class="flex items-start gap-2">
                  <span class="mt-1 h-1.5 w-1.5 rounded-full bg-primary-600 dark:bg-primary-500"></span>
                  <span>{{ $feat }}</span>
                </li>
              @endforeach
            </ul>
            <a href="{{ route('register') }}" class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-neutral-900 px-4 py-2 text-white hover:bg-neutral-800 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-white">
              Choose {{ $p['name'] }}
            </a>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- UPCOMING EVENTS (replaces FAQ) --}}
  <section id="events" class="py-16 sm:py-24 bg-neutral-50 dark:bg-neutral-900">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="mx-auto max-w-2xl text-center">
        <h2 class="text-3xl font-bold sm:text-4xl">Upcoming events</h2>
        <p class="mt-3 text-neutral-600 dark:text-neutral-300">
          Registration windows currently open and opening soon.
        </p>
      </div>

      <div class="mt-10">
        <livewire:landing.upcoming-events />
      </div>
    </div>
  </section>

@endsection
