{{-- resources/views/landing/league-info.blade.php --}}
@extends('landing.layouts.layout')

@section('title', $league->info->title ?? $league->title)

@php
  $fmt = fn($d) => $d ? $d->format('Y-m-d') : null;
@endphp

@section('content')
  {{-- Optional full-width banner --}}
  @if($bannerUrl)
    <section class="relative overflow-hidden">
      <img src="{{ $bannerUrl }}" alt="{{ $league->title }} banner" class="h-64 w-full object-cover sm:h-80 md:h-96">
      <div class="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-white to-transparent
                  dark:from-neutral-950 dark:to-transparent"></div>
    </section>
  @endif

  <section class="py-10 sm:py-12">
    <div class="mx-auto w-full max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

      {{-- Heading + meta in landing card style --}}
      <div class="rounded-2xl border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">
              {{ $league->info->title ?? $league->title }}
            </h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
              {{ $league->location ?: '—' }} •
              {{ ucfirst($league->type->value ?? $league->type) }} League •
              Starts {{ optional($league->start_date)->format('Y-m-d') ?: '—' }} •
              {{ $league->length_weeks }} weeks
            </p>
          </div>

          {{-- Registration status / CTA (Flux buttons) --}}
          <div class="mt-1 sm:mt-0 text-right">
            @if($window === 'during')
              @if($registrationUrl)
                <x-flux::button as="a" href="{{ $registrationUrl }}" target="_blank" rel="noopener"
                  variant="primary" size="sm">
                  Register now
                </x-flux::button>
                @if($start || $end)
                  <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                    @if($start) Opens {{ $fmt($start) }}. @endif
                    @if($end) Closes {{ $fmt($end) }}. @endif
                  </div>
                @endif
              @else
                <div class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800 ring-1 ring-inset ring-emerald-200
                            dark:bg-emerald-900/20 dark:text-emerald-200 dark:ring-emerald-900/40">
                  Registration is open. @if($end) Closes {{ $fmt($end) }}. @endif
                </div>
              @endif

            @elseif($window === 'before')
              <div class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-inset ring-amber-200
                          dark:bg-amber-900/20 dark:text-amber-200 dark:ring-amber-900/40">
                Registration opens {{ $fmt($start) }}.
              </div>

            @elseif($window === 'after')
              <div class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-200
                          dark:bg-rose-900/20 dark:text-rose-200 dark:ring-rose-900/40">
                Registration closed{{ $end ? ' on '.$fmt($end) : '' }}.
              </div>

            @else
              @if($registrationUrl)
                <x-flux::button as="a" href="{{ $registrationUrl }}" target="_blank" rel="noopener"
                  variant="primary" size="md">
                  Register
                </x-flux::button>
              @endif
            @endif
          </div>
        </div>
      </div>

      {{-- Content (landing prose) --}}
      <div class="prose prose-neutral max-w-none rounded-2xl border border-neutral-200 bg-white p-6
                  dark:prose-invert dark:border-neutral-800 dark:bg-neutral-900">
        {!! $contentHtml !!}
      </div>

      {{-- Facts strip --}}
      <div class="rounded-2xl border border-neutral-200 bg-white p-4 text-sm text-neutral-600
                  dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300">
        <div class="flex flex-wrap gap-x-6 gap-y-2">
          <div><span class="font-medium text-neutral-900 dark:text-neutral-100">Ends/day:</span> {{ $league->ends_per_day }}</div>
          <div><span class="font-medium text-neutral-900 dark:text-neutral-100">Arrows/end:</span> {{ $league->arrows_per_end }}</div>
          <div><span class="font-medium text-neutral-900 dark:text-neutral-100">X-ring value:</span> {{ $league->x_ring_value }}</div>
          <div><span class="font-medium text-neutral-900 dark:text-neutral-100">Lanes:</span> {{ $league->lanes_count }} ({{ $league->lane_breakdown }})</div>
        </div>
      </div>

      {{-- Optional: back link / CTA bar --}}
      <div class="flex items-center justify-between">
        <a href="{{ route('home') }}"
           class="text-sm text-neutral-600 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200">
          ← Back to home
        </a>
      </div>
    </div>
  </section>
@endsection
