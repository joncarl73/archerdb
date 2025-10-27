@extends('landing.layouts.layout')

@section('title', $event->title)

@section('content')
<section class="container mx-auto max-w-4xl py-10 space-y-6">
  <h1 class="text-3xl font-semibold">{{ $event->title }}</h1>
  <p class="opacity-80">{{ $event->location }}</p>
  <p class="opacity-70">
    {{ $event->starts_on->toFormattedDateString() }}
    @if($event->starts_on->ne($event->ends_on)) – {{ $event->ends_on->toFormattedDateString() }} @endif
  </p>
  <a class="btn btn-primary" href="{{ route('public.event.landing', ['uuid' => $event->public_uuid]) }}">View Details</a>
</section>
@endsection
