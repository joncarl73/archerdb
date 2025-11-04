@php
    use Illuminate\Support\Facades\Route as RouteFacade;

    // Prefer named route; fall back to path if someone renames it later
    $participantsUrl = RouteFacade::has('corporate.events.participants.index')
        ? route('corporate.events.participants.index', $event)
        : url('/corporate/events/'.$event->id.'/participants');

    // Stripe session id can be injected by controller OR come from the query string (?session_id=...)
    $sessionId = $sessionId ?? request('session_id');
@endphp

<x-layouts.app title="Event CSV Import">
<div class="mx-auto max-w-3xl px-4 py-10">
    <div class="mb-6">
        <a href="{{ route('corporate.events.show', $event) }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
            <svg class="mr-1 size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M11.78 15.78a.75.75 0 0 1-1.06 0L5.47 10.53a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 1 1 1.06 1.06L7.81 9.25H16a.75.75 0 0 1 0 1.5H7.81l3.97 3.97a.75.75 0 0 1 0 1.06Z" clip-rule="evenodd" />
            </svg>
            Back to event
        </a>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-zinc-900">
        <div class="flex items-start gap-4">
            <div class="mt-1">
                <svg class="size-6 text-indigo-600 dark:text-indigo-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M12 2.25a.75.75 0 0 1 .75.75V5.5h2.5a.75.75 0 0 1 0 1.5h-2.5v7a2 2 0 1 0 4 0 .75.75 0 0 1 1.5 0 3.5 3.5 0 0 1-7 0v-7H7.5a.75.75 0 0 1 0-1.5H10.5V3A.75.75 0 0 1 12 2.25Z" clip-rule="evenodd"/>
                </svg>
            </div>

            <div class="flex-1">
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                    CSV Import Payment — {{ $event->title }}
                </h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                    Thanks! If your payment succeeded, we’ll import your participants automatically
                    in the background. This may take a moment while we confirm the payment and process the file.
                </p>

                @if(!empty($sessionId))
                    <div class="mt-4 rounded-xl bg-gray-50 p-3 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">
                        <div class="font-medium">Stripe session</div>
                        <code class="break-all">{{ $sessionId }}</code>
                    </div>
                @endif

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">What happens next?</h2>
                        <ul class="mt-2 list-disc pl-5 text-sm text-gray-600 dark:text-gray-300 space-y-1">
                            <li>We confirm your payment via Stripe webhooks.</li>
                            <li>Once confirmed, your CSV is parsed and participants are added.</li>
                            <li>You’ll see new participants on the Participants tab.</li>
                        </ul>
                    </div>

                    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Troubleshooting</h2>
                        <ul class="mt-2 list-disc pl-5 text-sm text-gray-600 dark:text-gray-300 space-y-1">
                            <li>If you canceled payment, simply re-upload and try again.</li>
                            <li>If payment failed, you can retry from the Confirm &amp; Pay screen.</li>
                            <li>If nothing appears after a few minutes, recheck your CSV and try again.</li>
                        </ul>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <flux:button as="a" href="{{ $participantsUrl }}" variant="primary">
                        Go to Participants
                    </flux:button>

                    <flux:button as="a" href="{{ route('corporate.events.show', $event) }}" variant="ghost">
                        Back to Event
                    </flux:button>
                </div>

                <div class="mt-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        This page is safe to close. You can continue managing your event while we finish up.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
</x-layouts.app>
