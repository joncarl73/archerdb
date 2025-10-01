@extends('layouts.public')

@section('title', 'Kiosk Home')

@section('content')
  <div class="mx-auto max-w-3xl py-10">
    <h1 class="text-2xl font-semibold dark:text-white">Kiosk mode</h1>
    <p class="mt-2 dark:text-gray-300">League: {{ $league->name }}</p>

    <div class="mt-6 flex gap-3">
      <a class="px-4 py-2 rounded-lg bg-blue-600 text-white"
         href="{{ route('public.scoring.index', $league->public_uuid) }}?kiosk=1">
        Open Scoring
      </a>

      <form method="POST" action="{{ route('kiosk.end') }}">
        @csrf
        <button class="px-4 py-2 rounded-lg bg-gray-700 text-white">End Kiosk</button>
      </form>
    </div>
  </div>
@endsection
