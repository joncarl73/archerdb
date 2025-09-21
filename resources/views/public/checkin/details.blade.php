{{-- resources/views/public/checkin/details.blade.php --}}
@extends('layouts.public')

@section('title', $league->title.' • Check-in')

@section('secondary-nav')
<header class="pt-6 pb-4 sm:pb-6">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <h1 class="text-base font-semibold text-gray-900 dark:text-white">
      {{ $league->title }} — Check-in
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
      {{ $p->first_name }} {{ $p->last_name }}, choose the week you’re shooting and your lane.
    </p>
  </div>
</header>
@endsection

@section('content')
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
  <form method="post" action="{{ route('public.checkin.details.submit', [$league->public_uuid, $p->id]) }}" class="max-w-2xl space-y-6">
    @csrf

    <div>
      {{-- League week --}}
      <label for="week_number" class="block text-sm font-medium text-gray-900 dark:text-white">League week</label>
      <select id="week_number" name="week_number" required
              class="mt-2 block w-full rounded-md border-0 bg-white px-3 py-2 text-gray-900 shadow-xs ring-1 ring-inset ring-gray-300
                    focus:ring-2 focus:ring-indigo-600 sm:text-sm dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10">
        <option value="" disabled selected>Select week…</option>
        @foreach($weeks as $w)
          <option value="{{ $w->week_number }}">
            Week {{ $w->week_number }} — {{ \Illuminate\Support\Carbon::parse($w->date)->format('M j, Y') }}
          </option>
        @endforeach
      </select>
      @error('week_number') <p class="mt-2 text-sm text-rose-500">{{ $message }}</p> @enderror
    </div>

    <div>
    <label for="lane" class="block text-sm font-medium text-gray-900 dark:text-white">
        Lane
    </label>
    <select id="lane" name="lane"
        class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-xs
            focus:border-indigo-500 focus:ring-2 focus:ring-indigo-600 dark:border-white/10 dark:bg-white/5
            dark:text-gray-200 dark:focus:border-indigo-400 dark:focus:ring-indigo-400">
        <option value="">{{ __('Select…') }}</option>
        @foreach($laneOptions as $opt)
        <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
        @endforeach
    </select>

    @error('lane')
        <p class="mt-2 text-sm text-rose-500">{{ $message }}</p>
    @enderror

    <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
        Lanes reflect the league’s configured breakdown:
        <span class="font-medium">Single</span> (1 per lane),
        <span class="font-medium">A/B</span> (2 per lane),
        or <span class="font-medium">A/B/C/D</span> (4 per lane).
    </p>
    </div>


    <div>
      <button
        class="inline-flex items-center gap-x-1 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs
               hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600
               dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
        Check in
      </button>
    </div>
  </form>
</div>
@endsection
