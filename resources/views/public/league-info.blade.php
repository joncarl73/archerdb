<x-layouts.app :title="$league->info->title ?? $league->title">
    <div class="mx-auto w-full max-w-5xl space-y-6">

        {{-- Banner --}}
        @if($bannerUrl)
            <div class="overflow-hidden rounded-xl ring-1 ring-black/5">
                <img src="{{ $bannerUrl }}" alt="{{ $league->title }} banner" class="w-full object-cover">
            </div>
        @endif

        {{-- Heading & meta --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-white/10 dark:bg-neutral-900">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ $league->info->title ?? $league->title }}
                    </h1>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ $league->location ?: '—' }} •
                        {{ ucfirst($league->type->value ?? $league->type) }} League •
                        Starts {{ optional($league->start_date)->format('Y-m-d') ?: '—' }} •
                        {{ $league->length_weeks }} weeks
                    </p>
                </div>

                {{-- Registration status / CTA --}}
                <div class="mt-2 sm:mt-0">
                    @php
                        $fmt = fn($d) => $d ? $d->format('Y-m-d') : null;
                    @endphp

                    @if($window === 'during')
                        @if($registrationUrl)
                            <a href="{{ $registrationUrl }}" target="_blank" rel="noopener"
                               class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                                Register now
                                <svg class="ml-2 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" clip-rule="evenodd"
                                          d="M12.293 2.293a1 1 0 011.414 0l4 4a.997.997 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a.997.997 0 010-1.414l8-8zM5 13l2 2 8-8-2-2-8 8z"/>
                                </svg>
                            </a>
                            @if($start || $end)
                                <div class="mt-1 text-right text-xs text-neutral-500 dark:text-neutral-400">
                                    @if($start) Opens {{ $fmt($start) }}. @endif
                                    @if($end) Closes {{ $fmt($end) }}. @endif
                                </div>
                            @endif
                        @else
                            <div class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-200 dark:ring-emerald-900/40">
                                Registration is open.
                                @if($end) Closes {{ $fmt($end) }}. @endif
                            </div>
                        @endif
                    @elseif($window === 'before')
                        <div class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-inset ring-amber-200 dark:bg-amber-900/20 dark:text-amber-200 dark:ring-amber-900/40">
                            Registration opens {{ $fmt($start) }}.
                        </div>
                    @elseif($window === 'after')
                        <div class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-200 dark:bg-rose-900/20 dark:text-rose-200 dark:ring-rose-900/40">
                            Registration closed{{ $end ? ' on '.$fmt($end) : '' }}.
                        </div>
                    @else
                        {{-- No dates configured --}}
                        @if($registrationUrl)
                            <a href="{{ $registrationUrl }}" target="_blank" rel="noopener"
                               class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                                Register
                            </a>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- Content --}}
        <div class="prose prose-neutral max-w-none rounded-xl border border-neutral-200 bg-white p-5 dark:prose-invert dark:border-white/10 dark:bg-neutral-900">
            {!! $contentHtml !!}
        </div>

        {{-- Optional: small facts footer --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-4 text-sm text-neutral-600 dark:border-white/10 dark:bg-neutral-900 dark:text-neutral-300">
            <div class="flex flex-wrap gap-x-6 gap-y-2">
                <div><span class="font-medium text-neutral-900 dark:text-neutral-100">Ends/day:</span> {{ $league->ends_per_day }}</div>
                <div><span class="font-medium text-neutral-900 dark:text-neutral-100">Arrows/end:</span> {{ $league->arrows_per_end }}</div>
                <div><span class="font-medium text-neutral-900 dark:text-neutral-100">X-ring value:</span> {{ $league->x_ring_value }}</div>
                <div><span class="font-medium text-neutral-900 dark:text-neutral-100">Lanes:</span> {{ $league->lanes_count }} ({{ $league->lane_breakdown }})</div>
            </div>
        </div>
    </div>
</x-layouts.app>
