<?php
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventLineTime;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    public Event $event;

    public EventLineTime $lineTime;

    /** Simple payload for the table */
    public array $checkins = [];

    public function mount(Event $event, EventLineTime $lineTime): void
    {
        Gate::authorize('view', $event);

        // Ensure the line time belongs to this event
        if ((int) $lineTime->event_id !== (int) $event->id) {
            abort(404);
        }

        $this->event = $event;
        $this->lineTime = $lineTime;

        $this->loadCheckins();
    }

    public function loadCheckins(): void
    {
        $rows = EventCheckin::query()
            ->where('event_id', $this->event->id)
            ->where('event_line_time_id', $this->lineTime->id)
            ->orderBy('lane_number')
            ->orderBy('lane_slot')
            ->get([
                'id',
                'participant_id',
                'participant_name',
                'lane_number',
                'lane_slot',
            ]);

        $this->checkins = $rows->map(function ($r) {
            $lane = (string) ($r->lane_number ?? '');
            if ($lane !== '' && $r->lane_slot && $r->lane_slot !== 'single') {
                $lane .= $r->lane_slot;
            }

            return [
                'id' => (int) $r->id,
                'participant_id' => (int) $r->participant_id,
                'participant_name' => (string) ($r->participant_name ?: 'Unknown'),
                'lane' => $lane !== '' ? $lane : null,
            ];
        })->all();
    }
};
?>

<section class="w-full">
  <div class="mx-auto max-w-7xl">
    {{-- Header --}}
    <div class="sm:flex sm:items-center">
      <div class="sm:flex-auto">
        <h1 class="text-base font-semibold text-gray-900 dark:text-white">
          {{ $event->title }} — Live scoring ({{ \Illuminate\Support\Carbon::parse($lineTime->starts_at)->format('Y-m-d H:i') }})
        </h1>
        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
          {{ $event->location ?: '—' }} · Line {{ \Illuminate\Support\Carbon::parse($lineTime->starts_at)->format('g:i A') }}
        </p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <a href="{{ route('corporate.events.show', $event) }}"
           class="rounded-md bg-white px-3 py-2 text-sm font-medium inset-ring inset-ring-gray-300 hover:bg-gray-50
                  dark:bg-white/5 dark:text-gray-200 dark:inset-ring-white/10 dark:hover:bg-white/10">
          Back to Event
        </a>
      </div>
    </div>

    {{-- Table --}}
    <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
      <table class="w-full text-left">
        <thead class="bg-white dark:bg-gray-900">
          <tr>
            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold">Lane</th>
            <th class="px-3 py-3.5 text-left text-sm font-semibold">Archer</th>
            <th class="py-3.5 pl-3 pr-4 text-right text-sm font-semibold"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
          @forelse($checkins as $c)
            <tr>
              <td class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white">
                {{ $c['lane'] ?? '—' }}
              </td>
              <td class="px-3 py-4 text-sm text-gray-700 dark:text-gray-300">
                {{ $c['participant_name'] }}
              </td>
              <td class="py-4 pl-3 pr-4 text-right text-sm">
                {{-- When your public event scoring route is ready, link it here --}}
                {{-- Example: --}}
                {{-- <a href="{{ route('public.event.scoring.start', [$event->public_uuid, $c['id']]) }}"
                       target="_blank"
                       class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">
                     Start scoring
                   </a> --}}
                <span class="text-xs text-gray-400">Waiting…</span>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="3" class="py-8 px-4 text-sm text-gray-500 dark:text-gray-400">
                No check-ins found for this line time.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</section>
