{{-- resources/views/public/checkin/ok.blade.php --}}
@extends('layouts.public')

@section('title', 'Checked in')

@section('secondary-nav')
<header class="pt-6 pb-4 sm:pb-6">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <h1 class="text-base font-semibold text-gray-900 dark:text-white">You’re all set</h1>
  </div>
</header>
@endsection

@section('content')
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
  <div class="max-w-xl rounded-xl border border-gray-200 p-6 shadow-sm dark:border-white/10 dark:bg-white/5">
    <p class="text-sm text-gray-700 dark:text-gray-300">
      {{ $name ? "$name, y" : 'Y' }}ou’ve been checked in. Good shooting!
    </p>
  </div>
</div>
@endsection
