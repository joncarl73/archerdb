<?php
use App\Enums\EventKind;
use App\Models\Event;
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

    // edit drawer state + form
    public bool $showEdit = false;

    public ?int $editingEventId = null;

    public string $e_title = '';

    public ?string $e_location = null;

    public string $e_kind = EventKind::SingleDay->value;

    public ?string $e_starts_on = null;

    public ?string $e_ends_on = null;

    public bool $e_is_published = false;

    public function with(): array
    {
        $q = Event::query()
            ->where('company_id', auth()->user()->company_id) // company-scoped
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
        ], [], [
            'c_title' => 'title',
            'c_kind' => 'kind',
            'c_starts_on' => 'starts on',
            'c_ends_on' => 'ends on',
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
        ]);

        // auto-assign creator as Owner
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
        ], [], [
            'e_title' => 'title',
            'e_kind' => 'kind',
            'e_starts_on' => 'starts on',
            'e_ends_on' => 'ends on',
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
};
?>

<div class="mx-auto max-w-7xl relative">
  {{-- Header (match leagues page typography/spacing) --}}
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

  {{-- Search (match leagues input sizing) --}}
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
            <tr class="">
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

                  <a href="{{ route('corporate.events.access', $event) }}">
                    <flux:button size="sm" appearance="secondary" icon="users">Collaborators</flux:button>
                  </a>

                  <a href="{{ route('corporate.events.ruleset.show', $event) }}">
                    <flux:button size="sm" appearance="secondary" icon="adjustments-horizontal">Ruleset</flux:button>
                  </a>

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

    {{-- Pagination (keep same control used across app) --}}
    <div class="mt-4">
      {{ $events->links() }}
    </div>
  </div>

  {{-- CREATE: Right-side drawer --}}
  @if($showCreate)
    <div class="fixed inset-0 z-40 bg-black/40" wire:click="closeCreate" aria-hidden="true"></div>
    <aside class="fixed inset-y-0 right-0 z-50 w-full max-w-lg bg-white dark:bg-zinc-900 shadow-xl border-l border-gray-200 dark:border-zinc-800 flex flex-col">
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

  {{-- EDIT: Right-side drawer --}}
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
</div>
