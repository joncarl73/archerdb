{{-- resources/views/public/checkin/participants.blade.php --}}
@extends('layouts.public')

@section('title', $league->title.' • Check-in')

@section('secondary-nav')
<header class="pt-6 pb-4 sm:pb-6">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <h1 class="text-base font-semibold text-gray-900 dark:text-white">
      {{ $league->title }} — Check-in
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
      Choose your name to start check-in.
    </p>
  </div>
</header>
@endsection

@section('content')
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
  <form method="post" action="{{ route('public.checkin.participants.submit', $league->public_uuid) }}" class="max-w-2xl">
    @csrf
    <label for="participant_id" class="block text-sm font-medium text-gray-900 dark:text-white">Participant</label>
    <select id="participant_id" name="participant_id" required
            class="mt-2 block w-full rounded-md border-0 bg-white px-3 py-2 text-gray-900 shadow-xs ring-1 ring-inset ring-gray-300
                   focus:ring-2 focus:ring-indigo-600 sm:text-sm dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10">
      <option value="" disabled selected>Select your name…</option>
      @foreach($participants as $pr)
        <option value="{{ $pr->id }}">{{ $pr->last_name }}, {{ $pr->first_name }} {{ $pr->email ? "({$pr->email})" : '' }}</option>
      @endforeach
    </select>
    @error('participant_id') <p class="mt-2 text-sm text-rose-500">{{ $message }}</p> @enderror

    <div class="mt-6">
      <button
        class="inline-flex items-center gap-x-1 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs
               hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600
               dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
        Continue
      </button>
    </div>
  </form>
</div>
@endsection
