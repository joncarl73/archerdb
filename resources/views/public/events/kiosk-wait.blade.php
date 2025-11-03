{{-- resources/views/public/events/scoring/kiosk-wait.blade.php --}}
<x-public.layout :title="$event->title . ' — Kiosk Scoring'">
  <div class="mx-auto max-w-lg px-4 py-12 text-center">
    <h1 class="text-2xl font-semibold">You’re checked in, {{ $participant->name }}!</h1>
    <p class="mt-2 text-sm text-gray-600 dark:text-zinc-400">
      This archer will be scored at the kiosk. Please proceed to the kiosk tablet when directed by staff.
    </p>
    <p class="mt-8 text-xs text-gray-500 dark:text-zinc-500">Powered by ArcherDB</p>
  </div>
</x-public.layout>
