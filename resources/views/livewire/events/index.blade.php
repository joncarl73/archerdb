<?php
use App\Enums\EventKind;
use App\Models\Event;
use App\Models\EventLineTime;
use App\Models\Ruleset;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    use \Livewire\WithPagination;

    // table
    protected string $pageName = 'eventsPage';

    public string $search = '';

    public string $sort = 'starts_on';

    public string $direction = 'desc';

    // create drawer state + form
    public bool $showCreate = false;

    public string $c_title = '';

    public ?string $c_location = null;

    public string $c_kind = EventKind::SingleDay->value;

    public ?string $c_starts_on = null;

    public ?string $c_ends_on = null;

    public bool $c_is_published = false;

    // parity fields retained on Event
    public string $c_type = 'open';                    // open|closed

    public string $c_scoring_mode = 'personal_device'; // personal_device|tablet

    public int $c_lanes_count = 10;                    // KEEP on event

    // edit drawer state + form
    public bool $showEdit = false;

    public ?int $editingEventId = null;

    public string $e_title = '';

    public ?string $e_location = null;

    public string $e_kind = EventKind::SingleDay->value;

    public ?string $e_starts_on = null;

    public ?string $e_ends_on = null;

    public bool $e_is_published = false;

    public string $e_type = 'open';

    public string $e_scoring_mode = 'personal_device';

    public int $e_lanes_count = 10;

    // -----------------------
    // Line Times drawer
    // -----------------------
    public bool $showLineTimes = false;

    public ?int $lt_event_id = null;

    public string $lt_event_title = '';

    public ?string $lt_date = null;        // YYYY-MM-DD

    public ?string $lt_start_time = null;  // HH:MM

    public ?string $lt_end_time = null;    // HH:MM

    public ?int $lt_capacity = 48;

    public ?string $lt_notes = null;

    /** @var array<string,string> */
    public array $lt_dates_options = [];

    // -----------------------
    // Ruleset drawer (select + edit ruleset scoring fields)
    // -----------------------
    public bool $showRuleset = false;

    public ?int $rs_event_id = null;

    public string $rs_event_title = '';

    public ?int $rs_selected_id = null;

    /** @var array<int,string> */
    public array $rs_options = [];

    public ?string $rs_selected_description = null;

    // NEW: ruleset scoring fields (editable here)
    public ?int $rs_ends_per_session = null;

    public ?int $rs_arrows_per_end = null;

    public string $rs_lane_breakdown = 'single'; // single|AB|ABCD|ABCDEF

    // ------------ helpers ------------
    protected function slotsPerLaneFrom(string $breakdown): int
    {
        return $breakdown === 'single' ? 1 : mb_strlen($breakdown);
    }

    public function with(): array
    {
        $q = Event::query()
            ->where('company_id', auth()->user()->company_id)
            ->when($this->search !== '', fn ($q) => $q->where(function ($w) {
                $w->where('title', 'like', "%{$this->search}%")
                    ->orWhere('location', 'like', "%{$this->search}%");
            }))
            ->orderBy($this->sort, $this->direction);

        return ['events' => $q->paginate(10)];
    }

    public function sortBy(string $col): void
    {
        if (! in_array($col, ['title', 'starts_on', 'ends_on'], true)) {
            return;
        }

        if ($this->sort === $col) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $col;
            $this->direction = 'asc';
        }
        $this->resetPage($this->pageName);
    }

    // -----------------------
    // Create Drawer
    // -----------------------
    public function openCreate(): void
    {
        Gate::authorize('create', Event::class);
        $this->resetCreateForm();
        $this->showCreate = true;
    }

    public function closeCreate(): void
    {
        $this->showCreate = false;
    }

    protected function resetCreateForm(): void
    {
        $this->resetErrorBag();
        $this->c_title = '';
        $this->c_location = null;
        $this->c_kind = EventKind::SingleDay->value;
        $this->c_starts_on = null;
        $this->c_ends_on = null;
        $this->c_is_published = false;

        $this->c_type = 'open';
        $this->c_scoring_mode = 'personal_device';
        $this->c_lanes_count = 10;
    }

    public function updatedCKind(string $value): void
    {
        $this->resetErrorBag(['c_ends_on']);
        if ($value === EventKind::SingleDay->value) {
            $this->c_ends_on = null;
        } else {
            if ($this->c_starts_on && (! $this->c_ends_on || $this->c_ends_on < $this->c_starts_on)) {
                $this->c_ends_on = $this->c_starts_on;
            }
        }
    }

    public function create(): void
    {
        $this->validate([
            'c_title' => ['required', 'string', 'max:255'],
            'c_kind' => ['required'],
            'c_starts_on' => ['required', 'date'],
            'c_ends_on' => ['nullable', 'date'],

            'c_type' => ['required', 'in:open,closed'],
            'c_scoring_mode' => ['required', 'in:personal_device,tablet'],
            'c_lanes_count' => ['required', 'integer', 'min:1', 'max:1000'],
        ], [], [
            'c_title' => 'title',
            'c_kind' => 'kind',
            'c_starts_on' => 'starts on',
            'c_ends_on' => 'ends on',
            'c_type' => 'registration type',
            'c_scoring_mode' => 'scoring mode',
            'c_lanes_count' => 'lanes count',
        ]);

        if ($this->c_kind === EventKind::SingleDay->value) {
            $this->c_ends_on = $this->c_starts_on;
        } else {
            if (! $this->c_ends_on) {
                $this->addError('c_ends_on', 'End date is required for multi-day events.');

                return;
            }
            if ($this->c_ends_on < $this->c_starts_on) {
                $this->addError('c_ends_on', 'End date must be the same day or after the start date.');

                return;
            }
        }

        $event = Event::create([
            'company_id' => auth()->user()->company_id,
            'title' => $this->c_title,
            'location' => $this->c_location,
            'kind' => $this->c_kind,
            'starts_on' => $this->c_starts_on,
            'ends_on' => $this->c_ends_on,
            'is_published' => $this->c_is_published,

            'type' => $this->c_type,
            'scoring_mode' => $this->c_scoring_mode,
            'lanes_count' => $this->c_lanes_count,
        ]);

        if (method_exists($event, 'collaborators')) {
            $event->collaborators()->syncWithoutDetaching([auth()->id() => ['role' => 'owner']]);
        }

        $this->closeCreate();
        session()->flash('ok', 'Event created.');
    }

    // -----------------------
    // Edit Drawer
    // -----------------------
    public function openEdit(int $eventId): void
    {
        $this->resetErrorBag();

        $event = Event::query()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($eventId);

        Gate::authorize('update', $event);

        $this->editingEventId = $event->id;
        $this->e_title = $event->title;
        $this->e_location = $event->location;
        $this->e_kind = is_string($event->kind) ? $event->kind : $event->kind->value;
        $this->e_starts_on = optional($event->starts_on)->toDateString();
        $this->e_ends_on = optional($event->ends_on)->toDateString();
        $this->e_is_published = (bool) $event->is_published;

        $this->e_type = (string) ($event->type ?? 'open');
        $this->e_scoring_mode = (string) ($event->scoring_mode ?? 'personal_device');
        $this->e_lanes_count = (int) ($event->lanes_count ?? 10);

        $this->showEdit = true;
    }

    public function closeEdit(): void
    {
        $this->showEdit = false;
        $this->editingEventId = null;
    }

    public function updatedEKind(string $value): void
    {
        $this->resetErrorBag(['e_ends_on']);
        if ($value === EventKind::SingleDay->value) {
            $this->e_ends_on = null;
        } else {
            if ($this->e_starts_on && (! $this->e_ends_on || $this->e_ends_on < $this->e_starts_on)) {
                $this->e_ends_on = $this->e_starts_on;
            }
        }
    }

    public function saveEdit(): void
    {
        if (! $this->editingEventId) {
            return;
        }

        $event = Event::query()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($this->editingEventId);

        Gate::authorize('update', $event);

        $this->validate([
            'e_title' => ['required', 'string', 'max:255'],
            'e_kind' => ['required'],
            'e_starts_on' => ['required', 'date'],
            'e_ends_on' => ['nullable', 'date'],

            'e_type' => ['required', 'in:open,closed'],
            'e_scoring_mode' => ['required', 'in:personal_device,tablet'],
            'e_lanes_count' => ['required', 'integer', 'min:1', 'max:1000'],
        ], [], [
            'e_title' => 'title',
            'e_kind' => 'kind',
            'e_starts_on' => 'starts on',
            'e_ends_on' => 'ends on',
            'e_type' => 'registration type',
            'e_scoring_mode' => 'scoring mode',
            'e_lanes_count' => 'lanes count',
        ]);

        if ($this->e_kind === EventKind::SingleDay->value) {
            $this->e_ends_on = $this->e_starts_on;
        } else {
            if (! $this->e_ends_on) {
                $this->addError('e_ends_on', 'End date is required for multi-day events.');

                return;
            }
            if ($this->e_ends_on < $this->e_starts_on) {
                $this->addError('e_ends_on', 'End date must be the same day or after the start date.');

                return;
            }
        }

        $event->update([
            'title' => $this->e_title,
            'location' => $this->e_location,
            'kind' => $this->e_kind,
            'starts_on' => $this->e_starts_on,
            'ends_on' => $this->e_ends_on,
            'is_published' => $this->e_is_published,

            'type' => $this->e_type,
            'scoring_mode' => $this->e_scoring_mode,
            'lanes_count' => $this->e_lanes_count,
        ]);

        $this->closeEdit();
        session()->flash('ok', 'Event updated.');
    }

    public function deleteEvent(int $eventId): void
    {
        $event = Event::query()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($eventId);

        Gate::authorize('delete', $event);

        $event->delete();
        session()->flash('ok', 'Event deleted.');
    }

    // -----------------------
    // Line Times
    // -----------------------
    public function openLineTimes(int $eventId): void
    {
        $this->resetErrorBag();

        $event = Event::query()
            ->where('company_id', auth()->user()->company_id)
            ->with('ruleset')
            ->findOrFail($eventId);

        Gate::authorize('update', $event);

        $this->lt_event_id = $event->id;
        $this->lt_event_title = $event->title ?? ('Event #'.$event->id);

        $start = Carbon::parse($event->starts_on);
        $end = Carbon::parse($event->ends_on ?? $event->starts_on);
        $period = CarbonPeriod::create($start, $end);

        $this->lt_dates_options = [];
        foreach ($period as $d) {
            $this->lt_dates_options[$d->format('Y-m-d')] = $d->format('D M j, Y');
        }

        $this->lt_date = array_key_first($this->lt_dates_options);
        $this->lt_start_time = null;
        $this->lt_end_time = null;

        // Default capacity now comes from ruleset lane_breakdown
        $breakdown = $event->ruleset?->lane_breakdown ?: 'single';
        $slotsPerLane = $this->slotsPerLaneFrom($breakdown);
        $this->lt_capacity = (int) ($event->lanes_count ?? 1) * $slotsPerLane;

        $this->lt_notes = null;
        $this->showLineTimes = true;
    }

    public function saveLineTime(): void
    {
        $this->validate([
            'lt_event_id' => ['required', 'integer', 'exists:events,id'],
            'lt_date' => ['required', 'date'],
            'lt_start_time' => ['required', 'date_format:H:i'],
            'lt_end_time' => ['required', 'date_format:H:i', 'after:lt_start_time'],
            'lt_capacity' => ['required', 'integer', 'min:1', 'max:10000'],
            'lt_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $event = Event::query()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($this->lt_event_id);

        Gate::authorize('update', $event);

        // ensure date inside range
        $start = Carbon::parse($event->starts_on);
        $end = Carbon::parse($event->ends_on ?? $event->starts_on);
        $chosen = Carbon::parse($this->lt_date);
        if ($chosen->lt($start) || $chosen->gt($end)) {
            $this->addError('lt_date', 'Selected date is outside the event date range.');

            return;
        }

        EventLineTime::create([
            'event_id' => $event->id,
            'line_date' => $this->lt_date,
            'start_time' => $this->lt_start_time.':00',
            'end_time' => $this->lt_end_time.':00',
            'capacity' => $this->lt_capacity,
            'notes' => $this->lt_notes,
        ]);

        $this->lt_start_time = null;
        $this->lt_end_time = null;
        $this->lt_notes = null;

        session()->flash('ok', 'Line time added.');
    }

    public function deleteLineTime(int $lineTimeId): void
    {
        $lt = EventLineTime::query()->findOrFail($lineTimeId);
        $event = Event::query()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($lt->event_id);

        Gate::authorize('update', $event);

        $lt->delete();
        session()->flash('ok', 'Line time removed.');
    }

    // -----------------------
    // Ruleset Drawer (select + edit fields)
    // -----------------------
    public function openRuleset(int $eventId): void
    {
        $this->resetErrorBag();

        $event = Event::query()
            ->where('company_id', auth()->user()->company_id)
            ->with('ruleset')
            ->findOrFail($eventId);

        Gate::authorize('update', $event);

        $this->rs_event_id = $event->id;
        $this->rs_event_title = $event->title ?? ('Event #'.$event->id);

        // company-scoped rulesets only
        $rulesets = Ruleset::query()
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'ends_per_session', 'arrows_per_end', 'lane_breakdown']);

        $this->rs_options = $rulesets->pluck('name', 'id')->toArray();
        $this->rs_selected_id = $event->ruleset_id ?? null;

        $sel = $this->rs_selected_id ? $rulesets->firstWhere('id', $this->rs_selected_id) : null;
        $this->rs_selected_description = $sel?->description;

        // load ruleset scoring fields (or sensible defaults)
        $this->rs_ends_per_session = $sel?->ends_per_session ?? 10;
        $this->rs_arrows_per_end = $sel?->arrows_per_end ?? 3;
        $this->rs_lane_breakdown = $sel?->lane_breakdown ?? 'single';

        $this->showRuleset = true;
    }

    public function updatedRsSelectedId($value): void
    {
        $rs = Ruleset::query()
            ->where('company_id', auth()->user()->company_id)
            ->whereKey($value)
            ->first(['description', 'ends_per_session', 'arrows_per_end', 'lane_breakdown']);

        $this->rs_selected_description = $rs?->description;
        $this->rs_ends_per_session = $rs?->ends_per_session ?? 10;
        $this->rs_arrows_per_end = $rs?->arrows_per_end ?? 3;
        $this->rs_lane_breakdown = $rs?->lane_breakdown ?? 'single';
    }

    public function saveRuleset(): void
    {
        if (! $this->rs_event_id) {
            return;
        }

        $event = Event::query()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($this->rs_event_id);

        Gate::authorize('update', $event);

        if ($this->rs_selected_id && ! array_key_exists($this->rs_selected_id, $this->rs_options)) {
            $this->addError('rs_selected_id', 'Invalid ruleset selection.');

            return;
        }

        // Persist selection on the event
        $event->ruleset_id = $this->rs_selected_id;
        $event->save();

        // Also persist the scoring fields onto the selected ruleset (company owns it)
        if ($this->rs_selected_id) {
            $this->validate([
                'rs_ends_per_session' => ['required', 'integer', 'min:1', 'max:1000'],
                'rs_arrows_per_end' => ['required', 'integer', 'min:1', 'max:12'],
                'rs_lane_breakdown' => ['required', 'in:single,AB,ABCD,ABCDEF'],
            ], [], [
                'rs_ends_per_session' => 'ends per day',
                'rs_arrows_per_end' => 'arrows per end',
                'rs_lane_breakdown' => 'lane breakdown',
            ]);

            $ruleset = Ruleset::query()
                ->where('company_id', auth()->user()->company_id)
                ->findOrFail($this->rs_selected_id);

            $ruleset->update([
                'ends_per_session' => $this->rs_ends_per_session,
                'arrows_per_end' => $this->rs_arrows_per_end,
                'lane_breakdown' => $this->rs_lane_breakdown,
            ]);
        }

        session()->flash('ok', 'Ruleset updated.');
        $this->showRuleset = false;
    }

    public function clearRuleset(): void
    {
        if (! $this->rs_event_id) {
            return;
        }

        $event = Event::query()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($this->rs_event_id);

        Gate::authorize('update', $event);

        $event->ruleset_id = null;
        $event->save();

        $this->rs_selected_id = null;
        $this->rs_selected_description = null;
        $this->rs_ends_per_session = 10;
        $this->rs_arrows_per_end = 3;
        $this->rs_lane_breakdown = 'single';

        session()->flash('ok', 'Ruleset cleared.');
        $this->showRuleset = false;
    }
};
?>
<!-- The Blade/HTML below is identical to your structure, with the
     three event-level fields removed from Create/Edit and added into the Ruleset drawer. -->

<div class="mx-auto max-w-7xl relative">
  {{-- Header --}}
  <div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
      <h1 class="text-base font-semibold text-gray-900 dark:text-white">Events</h1>
      <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
        Create and manage events for your company. Use the side drawer to add or edit events.
      </p>
    </div>
    <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
      <flux:button variant="primary" color="indigo" icon="plus" wire:click="openCreate">
        New event
      </flux:button>
    </div>
  </div>

  {{-- Flash OK --}}
  @if (session('ok'))
    <div class="mt-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">
      {{ session('ok') }}
    </div>
  @endif

  {{-- Search --}}
  <div class="mt-4 max-w-sm">
    <flux:input icon="magnifying-glass" placeholder="Search by title or location…" wire:model.live.debounce.300ms="search" />
  </div>

  {{-- Table --}}
  <div class="mt-6">
    <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
      <table class="w-full text-left">
        <thead class="bg-white dark:bg-gray-900">
          <tr>
            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white">
              <button wire:click="sortBy('title')" class="flex items-center gap-1">
                Title
                @if($sort==='title')
                  <span class="text-xs opacity-70">{{ $direction==='asc' ? '▲' : '▼' }}</span>
                @endif
              </button>
            </th>
            <th class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 sm:table-cell dark:text-white">Kind</th>
            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">
              <button wire:click="sortBy('starts_on')" class="flex items-center gap-1">
                Starts
                @if($sort==='starts_on')
                  <span class="text-xs opacity-70">{{ $direction==='asc' ? '▲' : '▼' }}</span>
                @endif
              </button>
            </th>
            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">
              <button wire:click="sortBy('ends_on')" class="flex items-center gap-1">
                Ends
                @if($sort==='ends_on')
                  <span class="text-xs opacity-70">{{ $direction==='asc' ? '▲' : '▼' }}</span>
                @endif
              </button>
            </th>
            <th class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 sm:table-cell dark:text-white">Location</th>
            <th class="py-3.5 pl-3 pr-4 text-right text-sm font-semibold text-gray-900 dark:text-white">Actions</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
          @foreach ($events as $event)
            <tr>
              <td class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white">
                <div class="flex items-center gap-2">
                  <span class="underline-offset-2">{{ $event->title }}</span>
                  @if($event->is_published)
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">Published</span>
                  @else
                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-800 dark:bg-white/10 dark:text-zinc-300">Draft</span>
                  @endif
                </div>
              </td>

              <td class="hidden px-3 py-4 text-sm text-gray-700 sm:table-cell dark:text-gray-300">
                {{ is_string($event->kind) ? $event->kind : $event->kind->value }}
              </td>

              <td class="px-3 py-4 text-sm text-gray-700 dark:text-gray-300">
                {{ optional($event->starts_on)->toFormattedDateString() }}
              </td>

              <td class="px-3 py-4 text-sm text-gray-700 dark:text-gray-300">
                @if($event->ends_on && $event->starts_on && $event->ends_on->isSameDay($event->starts_on))
                  —
                @else
                  {{ optional($event->ends_on)->toFormattedDateString() ?: '—' }}
                @endif
              </td>

              <td class="hidden px-3 py-4 text-sm text-gray-700 sm:table-cell dark:text-gray-300">
                {{ $event->location ?: '—' }}
              </td>

              <td class="py-4 pl-3 pr-4 text-right text-sm font-medium">
                <div class="inline-flex items-center gap-2">
                  <flux:button size="sm" appearance="secondary" icon="pencil-square" wire:click="openEdit({{ $event->id }})">
                    Edit
                  </flux:button>

                  <flux:button size="sm" appearance="secondary" icon="clock" wire:click="openLineTimes({{ $event->id }})">
                    Line Times
                  </flux:button>

                  <flux:button size="sm" appearance="secondary" icon="adjustments-horizontal" wire:click="openRuleset({{ $event->id }})">
                    Ruleset
                  </flux:button>

                  <flux:button
                    size="sm"
                    appearance="danger"
                    icon="trash"
                    wire:click="deleteEvent({{ $event->id }})"
                    onclick="return confirm('Delete this event? This cannot be undone.');"
                  >
                    Delete
                  </flux:button>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
      {{ $events->links() }}
    </div>
  </div>

  {{-- CREATE Drawer --}}
  @if($showCreate)
    <div class="fixed inset-0 z-40 bg-black/40" wire:click="closeCreate" aria-hidden="true"></div>
    <aside class="fixed inset-y-0 right-0 z-50 w/full max-w-lg bg-white dark:bg-zinc-900 shadow-xl border-l border-gray-200 dark:border-zinc-800 flex flex-col">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-zinc-800">
        <flux:text as="h2" class="text-lg font-semibold">Create event</flux:text>
        <flux:button icon="x-mark" appearance="ghost" size="sm" wire:click="closeCreate" />
      </div>

      <div class="flex-1 overflow-auto p-5 space-y-5" x-data="{ kind: @entangle('c_kind') }">
        <div class="grid gap-5 md:grid-cols-2">
          <div class="md:col-span-2">
            <flux:label>Title</flux:label>
            <flux:input wire:model.defer="c_title" placeholder="Indoor 600 — January" />
            @error('c_title') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
          </div>

          <div class="md:col-span-2">
            <flux:label>Location</flux:label>
            <flux:input wire:model.defer="c_location" placeholder="Club range, City, ST" />
          </div>

          <div>
            <flux:label>Kind</flux:label>
            <flux:select wire:model="c_kind">
              @foreach(\App\Enums\EventKind::cases() as $k)
                <option value="{{ $k->value }}">{{ $k->value }}</option>
              @endforeach
            </flux:select>
          </div>

          {{-- SINGLE-DAY --}}
          <div class="md:col-span-1" x-cloak x-show="kind === 'single_day'">
            <flux:label>Date</flux:label>
            <flux:input type="date" wire:model="c_starts_on" />
            @error('c_starts_on') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
          </div>

          {{-- MULTI-DAY --}}
          <template x-cloak x-if="kind !== 'single_day'">
            <div class="contents md:contents">
              <div>
                <flux:label>Starts on</flux:label>
                <flux:input
                  type="date"
                  wire:model="c_starts_on"
                  x-on:change="$wire.c_ends_on = (!$wire.c_ends_on || $wire.c_ends_on < $event.target.value) ? $event.target.value : $wire.c_ends_on"
                />
                @error('c_starts_on') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
              </div>
              <div>
                <flux:label>Ends on</flux:label>
                <flux:input type="date" wire:model="c_ends_on" x-bind:min="$wire.c_starts_on || null" />
                @error('c_ends_on') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
              </div>
            </div>
          </template>

          {{-- Parity fields kept on Event --}}
          <div>
            <flux:label>Registration type</flux:label>
            <flux:select wire:model="c_type">
              <option value="open">Open</option>
              <option value="closed">Closed</option>
            </flux:select>
            @error('c_type') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
          </div>

          <div>
            <flux:label>Scoring mode</flux:label>
            <flux:select wire:model="c_scoring_mode">
              <option value="personal_device">Personal device</option>
              <option value="tablet">Tablet / kiosk</option>
            </flux:select>
            @error('c_scoring_mode') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
          </div>

          <div>
            <flux:label>Lanes count</flux:label>
            <flux:input type="number" min="1" wire:model.defer="c_lanes_count" />
            @error('c_lanes_count') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
          </div>

          <div class="flex items-center gap-3 md:col-span-2">
            <flux:checkbox wire:model="c_is_published" />
            <flux:label class="!mb-0">Published</flux:label>
          </div>
        </div>
      </div>

      <div class="border-t border-gray-200 dark:border-zinc-800 px-5 py-4 flex items-center justify-end gap-2">
        <flux:button appearance="secondary" wire:click="closeCreate">Cancel</flux:button>
        <flux:button icon="plus" wire:click="create">Create event</flux:button>
      </div>
    </aside>
  @endif

  {{-- EDIT Drawer --}}
  @if($showEdit)
    <div class="fixed inset-0 z-40 bg-black/40" wire:click="closeEdit" aria-hidden="true"></div>
    <aside class="fixed inset-y-0 right-0 z-50 w-full max-w-lg bg-white dark:bg-zinc-900 shadow-xl border-l border-gray-200 dark:border-zinc-800 flex flex-col">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-zinc-800">
        <flux:text as="h2" class="text-lg font-semibold">Edit event</flux:text>
        <flux:button icon="x-mark" appearance="ghost" size="sm" wire:click="closeEdit" />
      </div>

      <div class="flex-1 overflow-auto p-5 space-y-5" x-data="{ kind: @entangle('e_kind') }">
        <div class="grid gap-5 md:grid-cols-2">
          <div class="md:col-span-2">
            <flux:label>Title</flux:label>
            <flux:input wire:model.defer="e_title" />
            @error('e_title') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
          </div>

          <div class="md:col-span-2">
            <flux:label>Location</flux:label>
            <flux:input wire:model.defer="e_location" />
          </div>

          <div>
            <flux:label>Kind</flux:label>
            <flux:select wire:model="e_kind">
              @foreach(\App\Enums\EventKind::cases() as $k)
                <option value="{{ $k->value }}">{{ $k->value }}</option>
              @endforeach
            </flux:select>
          </div>

          {{-- SINGLE-DAY --}}
          <div class="md:col-span-1" x-cloak x-show="kind === 'single_day'">
            <flux:label>Date</flux:label>
            <flux:input type="date" wire:model="e_starts_on" />
            @error('e_starts_on') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
          </div>

          {{-- MULTI-DAY --}}
          <template x-cloak x-if="kind !== 'single_day'">
            <div class="contents md:contents">
              <div>
                <flux:label>Starts on</flux:label>
                <flux:input
                  type="date"
                  wire:model="e_starts_on"
                  x-on:change="$wire.e_ends_on = (!$wire.e_ends_on || $wire.e_ends_on < $event.target.value) ? $event.target.value : $wire.e_ends_on"
                />
                @error('e_starts_on') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
              </div>
              <div>
                <flux:label>Ends on</flux:label>
                <flux:input type="date" wire:model="e_ends_on" x-bind:min="$wire.e_starts_on || null" />
                @error('e_ends_on') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
              </div>
            </div>
          </template>

          {{-- Parity fields kept on Event --}}
          <div>
            <flux:label>Registration type</flux:label>
            <flux:select wire:model="e_type">
              <option value="open">Open</option>
              <option value="closed">Closed</option>
            </flux:select>
            @error('e_type') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
          </div>

          <div>
            <flux:label>Scoring mode</flux:label>
            <flux:select wire:model="e_scoring_mode">
              <option value="personal_device">Personal device</option>
              <option value="tablet">Tablet / kiosk</option>
            </flux:select>
            @error('e_scoring_mode') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
          </div>

          <div>
            <flux:label>Lanes count</flux:label>
            <flux:input type="number" min="1" wire:model.defer="e_lanes_count" />
            @error('e_lanes_count') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
          </div>

          <div class="flex items-center gap-3 md:col-span-2">
            <flux:checkbox wire:model="e_is_published" />
            <flux:label class="!mb-0">Published</flux:label>
          </div>
        </div>
      </div>

      <div class="border-t border-gray-200 dark:border-zinc-800 px-5 py-4 flex items-center justify-end gap-2">
        <flux:button appearance="secondary" wire:click="closeEdit">Cancel</flux:button>
        <flux:button icon="check" wire:click="saveEdit">Save changes</flux:button>
      </div>
    </aside>
  @endif

  {{-- LINE TIMES Drawer (unchanged layout, new capacity logic already in component) --}}
  @if($showLineTimes)
    <div class="fixed inset-0 z-40 bg-black/40" x-on:click="$wire.showLineTimes=false" aria-hidden="true"></div>
    <aside class="fixed inset-y-0 right-0 z-50 w-full max-w-lg bg-white dark:bg-zinc-900 shadow-xl border-l border-gray-200 dark:border-zinc-800 flex flex-col">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-zinc-800">
        <flux:text as="h2" class="text-lg font-semibold">Line Times — {{ $lt_event_title }}</flux:text>
        <flux:button icon="x-mark" appearance="ghost" size="sm" wire:click="$set('showLineTimes', false)" />
      </div>

      <div class="flex-1 overflow-auto p-5 space-y-6">
        {{-- Existing --}}
        <div>
          <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Existing</h3>
          @php
            $existing = $lt_event_id ? \App\Models\EventLineTime::query()
              ->where('event_id', $lt_event_id)
              ->orderBy('line_date')
              ->orderBy('start_time')
              ->get() : collect();
          @endphp

          @if($existing->isEmpty())
            <div class="text-sm text-gray-500">No line times yet.</div>
          @else
            <ul class="space-y-2">
              @foreach($existing as $lt)
                @php
                  $d      = \Carbon\Carbon::parse($lt->line_date)->format('Y-m-d');
                  $startT = $lt->start_time;
                  $endT   = $lt->end_time;
                  $starts = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', "$d $startT")->format('m/d/Y g:ia');
                  $ends   = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', "$d $endT")->format('g:ia');
                @endphp
                <li class="flex items-center justify-between rounded-xl border border-gray-200 dark:border-zinc-700 px-3 py-2">
                  <div>
                    <div class="font-medium text-gray-900 dark:text-white">{{ $starts }} – {{ $ends }}</div>
                    <div class="text-xs text-gray-500">Capacity: {{ $lt->capacity }}</div>
                    @if($lt->notes)
                      <div class="text-xs text-gray-500">Notes: {{ $lt->notes }}</div>
                    @endif
                  </div>
                  <flux:button
                    size="xs"
                    appearance="danger"
                    icon="trash"
                    wire:click="deleteLineTime({{ $lt->id }})"
                    onclick="return confirm('Delete this line time?');"
                  >Delete</flux:button>
                </li>
              @endforeach
            </ul>
          @endif
        </div>

        {{-- Add new --}}
        <div class="rounded-2xl border border-gray-200 dark:border-zinc-700 p-4">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Add Line Time</h3>

          <div class="grid gap-3">
            <div>
              <flux:label>Date</flux:label>
              <flux:select wire:model="lt_date">
                @foreach($lt_dates_options as $val => $label)
                  <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
              </flux:select>
              @error('lt_date') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <flux:label>Start time</flux:label>
                <flux:input type="time" step="60" wire:model="lt_start_time" />
                @error('lt_start_time') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
              </div>
              <div>
                <flux:label>End time</flux:label>
                <flux:input type="time" step="60" wire:model="lt_end_time" />
                @error('lt_end_time') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <flux:label>Capacity</flux:label>
                <flux:input type="number" min="1" wire:model="lt_capacity" />
                @error('lt_capacity') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
              </div>
              <div>
                <flux:label>Notes (optional)</flux:label>
                <flux:input wire:model="lt_notes" placeholder="e.g., Compound/Recurve split" />
                @error('lt_notes') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
              </div>
            </div>

            <div class="pt-2">
              <flux:button icon="plus" wire:click="saveLineTime">Add line time</flux:button>
            </div>
          </div>
        </div>
      </div>

      <input type="hidden" wire:model="lt_event_id" />
    </aside>
  @endif

  {{-- RULESET Drawer --}}
  @if($showRuleset)
    <div class="fixed inset-0 z-40 bg-black/40" x-on:click="$wire.showRuleset=false" aria-hidden="true"></div>
    <aside class="fixed inset-y-0 right-0 z-50 w-full max-w-lg bg-white dark:bg-zinc-900 shadow-xl border-l border-gray-200 dark:border-zinc-800 flex flex-col">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-zinc-800">
        <flux:text as="h2" class="text-lg font-semibold">Ruleset — {{ $rs_event_title }}</flux:text>
        <flux:button icon="x-mark" appearance="ghost" size="sm" wire:click="$set('showRuleset', false)" />
      </div>

      <div class="flex-1 overflow-auto p-5 space-y-5">
        <div>
          <flux:label>Select ruleset</flux:label>
          <flux:select wire:model="rs_selected_id">
            <option value="">— None —</option>
            @foreach($rs_options as $rid => $name)
              <option value="{{ $rid }}">{{ $name }}</option>
            @endforeach
          </flux:select>
          @error('rs_selected_id') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
        </div>

        @if($rs_selected_description)
          <div class="rounded-xl border border-gray-200 dark:border-zinc-700 p-3">
            <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Description</div>
            <div class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line">{{ $rs_selected_description }}</div>
          </div>
        @endif

        {{-- NEW: Ruleset scoring fields --}}
        @if($rs_selected_id)
          <div class="grid gap-4 md:grid-cols-3">
            <div>
              <flux:label>Ends per day</flux:label>
              <flux:input type="number" min="1" wire:model.defer="rs_ends_per_session" />
              @error('rs_ends_per_session') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
            </div>
            <div>
              <flux:label>Arrows per end</flux:label>
              <flux:input type="number" min="1" max="12" wire:model.defer="rs_arrows_per_end" />
              @error('rs_arrows_per_end') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
            </div>
            <div>
              <flux:label>Lane breakdown</flux:label>
              <flux:select wire:model="rs_lane_breakdown">
                <option value="single">Single (1 per lane)</option>
                <option value="AB">A/B (2 per lane)</option>
                <option value="ABCD">A/B/C/D (4 per lane)</option>
                <option value="ABCDEF">A/B/C/D/E/F (6 per lane)</option>
              </flux:select>
              @error('rs_lane_breakdown') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
            </div>
          </div>
        @endif
      </div>

      <div class="border-t border-gray-200 dark:border-zinc-800 px-5 py-4 flex items-center gap-2">
        <flux:button size="sm" appearance="secondary" icon="no-symbol" wire:click="clearRuleset">
          Clear
        </flux:button>

        <div class="ml-auto flex items-center gap-2">
          <flux:button size="sm" appearance="secondary" wire:click="$set('showRuleset', false)">
            Cancel
          </flux:button>
          <flux:button size="sm" icon="check" wire:click="saveRuleset">
            Save
          </flux:button>
        </div>
      </div>

      <input type="hidden" wire:model="rs_event_id" />
    </aside>
  @endif
</div>
