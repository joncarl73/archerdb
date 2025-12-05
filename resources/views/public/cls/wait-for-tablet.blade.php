{{-- resources/views/public/cls/wait-for-tablet.blade.php --}}
<x-layouts.public :league="null">
  @php
    /** @var \App\Models\Event|\App\Models\League $owner */
    $isEvent = ($kind === 'event');
    $title   = $isEvent
      ? ($owner->title ?? 'Event')
      : ($owner->name ?? 'League');
  @endphp

  <section class="w-full">
    <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8 py-10">
      <div class="rounded-2xl border border-zinc-200 bg-white p-6 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <h1 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
          Scoring will be done on a tablet
        </h1>

        <p class="mt-3 text-sm text-zinc-700 dark:text-zinc-300">
          {{ $participantName }},
          scoring for
          <span class="font-medium">
            {{ $title }}
          </span>
          is handled using organizer-provided tablet devices.
        </p>

        <p class="mt-3 text-sm text-zinc-700 dark:text-zinc-300">
          You&apos;re checked in and assigned a lane. Please wait for an official
          to provide you with a scoring tablet. You won&apos;t need to enter scores on
          your own phone.
        </p>

        <div class="mt-6">
          <a
            href="{{ url()->previous() }}"
            class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
          >
            &larr; Back
          </a>
        </div>
      </div>
    </div>
  </section>
</x-layouts.public>
