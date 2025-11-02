<?php
use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    public Event $event;

    public string $checkinUrl = '';

    public function mount(Event $event): void
    {
        Gate::authorize('view', $event);

        // Load line times oldest → newest (date, then start time)
        $this->event = $event->load(['lineTimes' => fn ($q) => $q->orderBy('line_date')->orderBy('start_time')]);

        // Public check-in URL for events
        $this->checkinUrl = route('public.event.checkin.participants', ['uuid' => $this->event->public_uuid]);
    }

    public function getIsTabletModeProperty(): bool
    {
        $mode = is_string($this->event->scoring_mode)
            ? $this->event->scoring_mode
            : ($this->event->scoring_mode->value ?? 'personal_device');

        return $mode === 'tablet';
    }

    public function getCanManageKiosksProperty(): bool
    {
        return Gate::check('manageKiosks', $this->event);
    }
};
?>

<section class="w-full">
  @php
    $mode = is_string($event->scoring_mode) ? $event->scoring_mode : ($event->scoring_mode->value ?? $event->scoring_mode);
    $isTabletMode = ($mode === 'tablet');
    $canManageKiosks = Gate::check('manageKiosks', $event);
    $canUpdateEvent = Gate::check('update', $event);
  @endphp

  <div class="mx-auto max-w-7xl">
    {{-- Header --}}
    <div class="sm:flex sm:items-center">
      <div class="sm:flex-auto">
        <h1 class="text-base font-semibold text-gray-900 dark:text-white">
          {{ $event->title }}
        </h1>
        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
          {{ $event->location ?: '—' }} •
          {{ ucfirst(is_string($event->kind) ? $event->kind : $event->kind->value) }} •
          {{ optional($event->starts_on)->format('Y-m-d') ?: '—' }}
          @if($event->ends_on && $event->starts_on && !$event->ends_on->isSameDay($event->starts_on))
            → {{ optional($event->ends_on)->format('Y-m-d') }}
          @endif
        </p>
      </div>

      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <div class="flex items-center gap-2">
          {{-- Kiosk sessions (parity with leagues) --}}
          @if($isTabletMode && $canManageKiosks)
            <flux:button as="a"
              href="{{ route('corporate.events.kiosks.index', $event) }}"
              variant="primary" color="emerald" icon="computer-desktop">
              Kiosk sessions
            </flux:button>
          @endif

          {{-- Actions dropdown --}}
          <flux:dropdown>
            <flux:button icon:trailing="chevron-down">Actions</flux:button>
            <flux:menu class="min-w-64">
              {{-- If you have an event info editor, wire it here (mirroring leagues.info.edit) --}}
              {{-- @if($canUpdateEvent)
                <flux:menu.item href="{{ route('corporate.events.info.edit', $event) }}" icon="pencil-square">
                  Create/Update event info
                </flux:menu.item>
              @endif --}}

              {{-- Public event landing (already in your routes) --}}
              <flux:menu.item href="{{ route('public.event.landing', ['uuid' => $event->public_uuid]) }}"
                               target="_blank" icon="arrow-top-right-on-square">
                View public page
              </flux:menu.item>

              {{-- QR for public check-in (events) --}}
              <flux:menu.item href="{{ route('corporate.events.qr.pdf', $event) }}" icon="qr-code">
                Download check-in QR (PDF)
              </flux:menu.item>

              {{-- If you add exports/scoring sheets for events, mirror the league items here --}}
            </flux:menu>
          </flux:dropdown>
        </div>
      </div>
    </div>

    {{-- Flash OK --}}
    @if (session('ok'))
      <div class="mt-6 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">
        {{ session('ok') }}
      </div>
    @endif

    {{-- Public check-in URL + QR (parity with leagues) --}}
    <div class="mt-6 grid gap-4 md:grid-cols-[1fr_auto]">
      <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
        <div class="text-sm font-medium text-gray-900 dark:text-white">Public check-in link</div>
        <div class="mt-2 flex items-center gap-2">
          <input type="text"
                 readonly
                 value="{{ $checkinUrl }}"
                 class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-xs
                        focus:border-indigo-500 focus:ring-2 focus:ring-indigo-600 dark:border-white/10 dark:bg-white/5
                        dark:text-gray-200 dark:focus:border-indigo-400 dark:focus:ring-indigo-400" />
          <a href="{{ $checkinUrl }}" target="_blank"
             class="rounded-md bg-white px-3 py-2 text-sm font-medium inset-ring inset-ring-gray-300 hover:bg-gray-50
                    dark:bg-white/5 dark:text-gray-200 dark:inset-ring-white/10 dark:hover:bg-white/10">
            Open
          </a>
        </div>
        <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
          Share this link or the QR code with archers to check in on their phone.
        </p>
      </div>

      <div class="flex items-center justify-center rounded-lg border border-gray-200 p-3 dark:border-white/10">
        <a href="{{ route('corporate.events.qr.pdf', $event) }}"
           title="Download printable QR (PDF)"
           class="block transition hover:opacity-90 focus:opacity-90">
          <div class="h-36 w-36">
            <div class="h-full w-full [&>svg]:h-full [&>svg]:w-full">
              {!! QrCode::format('svg')->size(300)->margin(1)->errorCorrection('M')->generate($checkinUrl) !!}
            </div>
          </div>
        </a>
      </div>
    </div>
  </div>

  {{-- Schedule (Line Times) – read-only --}}
  <div class="mt-6">
    <div class="mx-auto max-w-7xl">
      <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
        <table class="w-full text-left">
          <thead class="bg-white dark:bg-gray-900">
            <tr>
              <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Date</th>
              <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Day</th>
              <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Start</th>
              <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">End</th>
              <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Capacity</th>
              <th class="py-3.5 pl-3 pr-4"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/10">
            @forelse($event->lineTimes as $lt)
              @php
                $date = \Carbon\Carbon::parse($lt->line_date);
                $start = \Carbon\Carbon::createFromFormat('H:i:s', $lt->start_time)->format('g:ia');
                $end   = \Carbon\Carbon::createFromFormat('H:i:s', $lt->end_time)->format('g:ia');
              @endphp
              <tr>
                <td class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white">{{ $date->format('Y-m-d') }}</td>
                <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $date->format('l') }}</td>
                <td class="px-3 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $start }}</td>
                <td class="px-3 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $end }}</td>
                <td class="px-3 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $lt->capacity }}</td>
                <td class="py-4 pl-3 pr-4 text-right text-sm font-medium">
                  <div class="inline-flex items-center gap-2">
                    @if($isTabletMode && $canManageKiosks)
                      <flux:button
                        as="a"
                        href="{{ route('corporate.events.lines.live', [$event, $lt]) }}?kiosk=1"
                        target="_blank" size="sm" variant="primary" color="blue"
                        icon="presentation-chart-bar">
                        Live scoring (Kiosk)
                      </flux:button>
                    @endif
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="py-8 px-4 text-sm text-gray-500 dark:text-gray-400">
                  No line times scheduled. Use the Events index → “Line Times” drawer to add them.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>
