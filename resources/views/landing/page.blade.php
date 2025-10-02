@extends('landing.layout')

@section('title', $title ?? 'ArcherDB')

@section('content')
    <section class="py-10 sm:py-12">
        <div class="mx-auto w-full max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Optional page header --}}
            @if (!empty($heading) || !empty($subheading))
                <div class="text-center">
                    @if (!empty($heading))
                        <h1 class="text-3xl font-bold text-neutral-900 dark:text-neutral-100">{{ $heading }}</h1>
                    @endif
                    @if (!empty($subheading))
                        <p class="mt-2 text-neutral-600 dark:text-neutral-300">{{ $subheading }}</p>
                    @endif
                </div>
            @endif

            {{-- Content card --}}

            <div
                class="rounded-2xl border border-neutral-200 bg-white p-6
            dark:border-neutral-800 dark:bg-neutral-900
            text-neutral-800 dark:text-neutral-300">
                <div
                    class="space-y-4
            [&_h2]:text-3xl [&_h2]:font-bold [&_h2]:text-neutral-900 dark:[&_h2]:text-neutral-100
            [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:mt-6
            [&_p]:leading-relaxed
            [&_ul]:list-disc [&_ul]:pl-6 [&_ul>li]:mt-1.5
            [&_ol]:list-decimal [&_ol]:pl-6 [&_ol>li]:mt-1.5
            [&_a]:underline [&_a]:underline-offset-2 hover:[&_a]:opacity-80">
                    @yield('page')
                </div>
            </div>


            {{-- Optional footer links --}}
            <div class="flex items-center justify-between">
                <a href="{{ route('home') }}"
                    class="text-sm text-neutral-600 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200">
                    ← Back to home
                </a>
            </div>
        </div>
    </section>
@endsection
