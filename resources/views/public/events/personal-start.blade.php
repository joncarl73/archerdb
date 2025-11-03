{{-- Personal device: hand off to scoring --}}
<x-layouts.public title="Start Scoring">
  <div class="mx-auto max-w-2xl py-10 text-center">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">You’re checked in!</h1>
    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
      {{ $event->title }} — {{ $participant->name ?? ($participant->first_name.' '.$participant->last_name) }}
    </p>

    {{-- If your app needs to mint/load a Score row first, do that in the controller
         and inject a $scoreId to route to. Otherwise link to your existing start route. --}}
    <div class="mt-8">
      <a href="{{ route('public.event.scoring.start', ['checkin' => $checkinId ?? request('checkin')]) }}"
         class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500
                focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600
                dark:bg-emerald-500 dark:hover:bg-emerald-400">
        Begin Scoring
      </a>
    </div>

    <p class="mt-6 text-xs text-gray-500 dark:text-gray-400">
      If this isn’t you, go back to the check-in page and choose the correct participant.
    </p>
  </div>
</x-layouts.public>
