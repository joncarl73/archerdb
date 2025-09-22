@props(['league' => null])
@extends('layouts.public', ['league' => $league])

@section('content')
  {{ $slot }}
@endsection
