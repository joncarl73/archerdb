{{-- Kiosk flow: indicate that scoring will happen on a kiosk --}}
<x-layouts.public title="Kiosk Scoring">
  <div class="mx-auto max-w-2xl py-10 text-center">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">You’re checked in!</h1>
    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
      {{ $event->title }} — {{ $participant->name ?? ($participant->first_name.' '.$participant->last_name) }}
    </p>

    <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4 text-left dark:border-white/10 dark:bg-white/5">
      <p class="text-sm text-gray-800 dark:text-gray-200">
        This event uses <span class="font-medium">kiosk tablets</span> for scoring. Please proceed to your assigned kiosk.
      </p>
      <ul class="mt-3 list-disc pl-5 text-sm text-gray-600 dark:text-gray-300">
        <li>Event staff will open the scoring screen on the kiosk for you.</li>
        <li>If you need assistance, ask a volunteer or official.</li>
      </ul>
    </div>

    <div class="mt-8">
      <a href="{{ route('public.event.landing', ['uuid' => $event->public_uuid]) }}"
         class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50
                dark:bg-white/5 dark:text-gray-200 dark:inset-ring-white/10 dark:hover:bg-white/10">
        Back to event page
      </a>
    </div>
  </div>
</x-layouts.public>
