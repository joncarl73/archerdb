{{-- resources/views/public/events/scoring/personal-start.blade.php --}}
<x-public.layout :title="$event->title . ' — Start Scoring'">
  <div class="mx-auto max-w-lg px-4 py-12 text-center">
    <h1 class="text-2xl font-semibold">You’re checked in, {{ $participant->name }}!</h1>
    <p class="mt-2 text-sm text-gray-600 dark:text-zinc-400">
      Lanes are handled by staff. When told to begin, tap below to open the scoring page on your device.
    </p>

    <a href="{{ route('public.scoring.start', ['uuid' => $event->public_uuid, 'pid' => $participant->id]) }}"
       class="mt-6 inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus:ring-2 focus:ring-indigo-600">
      Open Scoring
    </a>

    <p class="mt-8 text-xs text-gray-500 dark:text-zinc-500">Powered by ArcherDB</p>
  </div>
</x-public.layout>
