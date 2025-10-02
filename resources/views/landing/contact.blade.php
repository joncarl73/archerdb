@extends('landing.layouts.page')

@section('title', 'Contact Us')

@section('page')
  {{-- Optional intro --}}
  <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
    We usually reply within 1–2 business days.
  </p>

  <livewire:landing.contact-form />
@endsection
