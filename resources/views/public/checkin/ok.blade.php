{{-- resources/views/public/checkin/ok.blade.php --}}
@extends('layouts.public')

@section('title', 'Check-in status')

@section('secondary-nav')
<header class="pt-6 pb-4 sm:pb-6">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <h1 class="text-base font-semibold text-gray-900 dark:text-white">
      @if(!empty($repeat))
        You’re already checked in
      @else
        You’re all set
      @endif
    </h1>
  </div>
</header>
@endsection

@section('content')
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
  <div class="max-w-xl rounded-xl border border-gray-200 p-6 shadow-sm dark:border-white/10 dark:bg-white/5">
    @php
      $who  = $name ? "{$name}, " : '';
      $wk   = $week ? "week {$week}" : "this session";
      $ln   = $lane ? " (Lane {$lane})" : '';
    @endphp

    @if(!empty($repeat))
      <p class="text-sm text-gray-700 dark:text-gray-300">
        {{ $who }}you had already checked in for {{ $wk }}{{ $ln }}. You’re good to go—good shooting!
      </p>
    @else
      <p class="text-sm text-gray-700 dark:text-gray-300">
        {{ $who }}you’ve been checked in for {{ $wk }}{{ $ln }}. Good shooting!
      </p>
    @endif

    <div class="mt-4">
      <a href="{{ route('public.checkin.participants', $league->public_uuid) }}"
         class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
        Check in another archer
      </a>
    </div>
  </div>
</div>
@endsection
