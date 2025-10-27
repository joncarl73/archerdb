<?php
use App\Enums\EventKind;
use App\Models\Event;
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

    public string $title = '';

    public ?string $location = null;

    public string $kind = EventKind::SingleDay->value;   // 'single_day'

    public ?string $starts_on = null;

    public ?string $ends_on = null;

    public bool $is_published = false;

    public function with(): array
    {
        $q = Event::query()
            ->when(auth()->user()->isCorporate(), fn ($q) => $q->where('company_id', auth()->user()->company_id))
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

    public function openCreate(): void
    {
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
        $this->title = '';
        $this->location = null;
        $this->kind = EventKind::SingleDay->value;
        $this->starts_on = null;
        $this->ends_on = null;
        $this->is_published = false;
    }

    // Normalize dates when switching kind; UI toggling handled client-side via Alpine
    public function updatedKind(string $value): void
    {
        $this->resetErrorBag(['ends_on']);
        if ($value === EventKind::SingleDay->value) {
            // hide "ends_on" in UI; set on save
            $this->ends_on = null;
        } else {
            // prefill ends_on if needed
            if ($this->starts_on && (! $this->ends_on || $this->ends_on < $this->starts_on)) {
                $this->ends_on = $this->starts_on;
            }
        }
    }

    public function create(): \Illuminate\Http\RedirectResponse
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['required'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date'],
        ]);

        if ($this->kind === EventKind::SingleDay->value) {
            // Single day: force ends_on to starts_on
            $this->ends_on = $this->starts_on;
        } else {
            // Multi-day checks
            if (! $this->ends_on) {
                $this->addError('ends_on', 'End date is required for multi-day events.');

                return back();
            }
            if ($this->ends_on < $this->starts_on) {
                $this->addError('ends_on', 'End date must be the same day or after the start date.');

                return back();
            }
        }

        $event = Event::create([
            'company_id' => auth()->user()->company_id,
            'title' => $this->title,
            'location' => $this->location,
            'kind' => $this->kind,
            'starts_on' => $this->starts_on,
            'ends_on' => $this->ends_on,
            'is_published' => $this->is_published,
        ]);

        $this->closeCreate();

        return redirect()->route('corporate.events.basics', $event);
    }
}; ?>

<div class="mx-auto max-w-7xl relative">
  <!-- Header -->
  <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <flux:text as="h1" class="text-2xl font-semibold">Events</flux:text>
    <flux:button variant="primary" color="indigo" wire:click="openCreate">New event</flux:button>
  </div>

  <!-- Search -->
  <div class="mt-4 max-w-md">
    <flux:input icon="magnifying-glass" placeholder="Search by title or location…"
                wire:model.live.debounce.300ms="search" />
  </div>

  <!-- Table -->
  <div class="mt-6">
    <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
      <table class="w-full text-left">
        <thead class="bg-white dark:bg-gray-900">
          <tr>
            <th class="px-3 py-3.5 text-left text-sm font-semibold dark:text-white">
              <button wire:click="sortBy('title')" class="flex items-center gap-1">Title</button>
            </th>
            <th class="px-3 py-3.5 text-left text-sm font-semibold dark:text-white">Kind</th>
            <th class="px-3 py-3.5 text-left text-sm font-semibold dark:text-white">
              <button wire:click="sortBy('starts_on')" class="flex items-center gap-1">Starts</button>
            </th>
            <th class="px-3 py-3.5 text-left text-sm font-semibold dark:text-white">
              <button wire:click="sortBy('ends_on')" class="flex items-center gap-1">Ends</button>
            </th>
            <th class="px-3 py-3.5 text-left text-sm font-semibold dark:text-white hidden md:table-cell">Location</th>
            <th class="px-3 py-3.5 text-right text-sm font-semibold dark:text-white">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-zinc-800">
          @foreach ($events as $event)
            <tr class="bg-white hover:bg-zinc-50 dark:bg-zinc-900 dark:hover:bg-zinc-800/60">
              <td class="px-3 py-3 text-sm">
                <a class="font-medium underline-offset-2 hover:underline"
                   href="{{ route('corporate.events.basics', $event) }}">
                  {{ $event->title }}
                </a>
              </td>
              <td class="px-3 py-3 text-sm">{{ $event->kind->value }}</td>
              <td class="px-3 py-3 text-sm">{{ $event->starts_on->toFormattedDateString() }}</td>
              <td class="px-3 py-3 text-sm">
                {{ $event->ends_on->isSameDay($event->starts_on) ? '—' : $event->ends_on->toFormattedDateString() }}
              </td>
              <td class="px-3 py-3 text-sm hidden md:table-cell">{{ $event->location ?: '—' }}</td>
              <td class="px-3 py-3 text-sm text-right">
                <a href="{{ route('corporate.events.basics', $event) }}">
                  <flux:button size="sm" appearance="secondary" icon="cog">Manage</flux:button>
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="mt-4">
      {{ $events->links() }}
    </div>
  </div>

  <!-- Right-side create drawer (Flux-style panel) -->
  @if($showCreate)
    <!-- Backdrop -->
    <div class="fixed inset-0 z-40 bg-black/40" wire:click="closeCreate" aria-hidden="true"></div>

    <!-- Panel -->
    <aside class="fixed inset-y-0 right-0 z-50 w-full max-w-lg bg-white dark:bg-zinc-900 shadow-xl
                  border-l border-gray-200 dark:border-zinc-800 flex flex-col">
      <!-- Panel header -->
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-zinc-800">
        <flux:text as="h2" class="text-lg font-semibold">Create event</flux:text>
        <flux:button icon="x-mark" appearance="ghost" size="sm" wire:click="closeCreate" />
      </div>

      <!-- Panel body -->
      <div class="flex-1 overflow-auto p-5 space-y-5" x-data="{ kind: @entangle('kind') }">
        <div class="grid gap-5 md:grid-cols-2">
          <div class="md:col-span-2">
            <flux:label>Title</flux:label>
            <flux:input wire:model.defer="title" placeholder="Indoor 600 — January" />
            @error('title') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
          </div>

          <div class="md:col-span-2">
            <flux:label>Location</flux:label>
            <flux:input wire:model.defer="location" placeholder="Club range, City, ST" />
          </div>

          <div>
            <flux:label>Kind</flux:label>
            <flux:select wire:model="kind">
              @foreach(\App\Enums\EventKind::cases() as $k)
                <option value="{{ $k->value }}">{{ $k->value }}</option>
              @endforeach
            </flux:select>
          </div>

          <!-- SINGLE-DAY: one date field -->
          <div class="md:col-span-1" x-cloak x-show="kind === 'single_day'">
            <flux:label>Date</flux:label>
            <flux:input type="date" wire:model="starts_on" />
            @error('starts_on') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
          </div>

          <!-- MULTI-DAY: starts + ends -->
          <template x-cloak x-if="kind !== 'single_day'">
            <div class="contents md:contents">
              <div>
                <flux:label>Starts on</flux:label>
                <flux:input type="date"
                            wire:model="starts_on"
                            x-on:change="$wire.ends_on = (!$wire.ends_on || $wire.ends_on < $event.target.value) ? $event.target.value : $wire.ends_on" />
                @error('starts_on') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
              </div>

              <div>
                <flux:label>Ends on</flux:label>
                <flux:input type="date"
                            wire:model="ends_on"
                            x-bind:min="$wire.starts_on || null" />
                @error('ends_on') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
              </div>
            </div>
          </template>

          <div class="flex items-center gap-3 md:col-span-2">
            <flux:checkbox wire:model="is_published" />
            <flux:label class="!mb-0">Published</flux:label>
          </div>
        </div>
      </div>

      <!-- Panel footer -->
      <div class="border-t border-gray-200 dark:border-zinc-800 px-5 py-4 flex items-center justify-end gap-2">
        <flux:button appearance="secondary" wire:click="closeCreate">Cancel</flux:button>
        <flux:button icon="plus" wire:click="create">Create event</flux:button>
      </div>
    </aside>
  @endif
</div>
