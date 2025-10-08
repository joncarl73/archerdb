<?php
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // pagination (mirror leagues)
    protected string $pageName = 'eventsPage';

    public int $perPage = 5;

    // sorting (mirror leagues)
    public string $sort = 'updated_at';

    public string $direction = 'desc';

    // sheet state (create + edit)
    public bool $showSheet = false;

    public ?int $editingId = null;

    // filters/search
    public string $search = '';

    public ?string $kind = ''; // '', 'single_day', 'multi_day'

    // form fields (basics)
    public string $title = '';

    public ?string $location = null;

    public string $create_kind = 'single_day';     // single_day | multi_day

    public ?string $starts_on = null;              // Y-m-d

    public ?string $ends_on = null;                // Y-m-d

    public bool $is_published = false;

    // lifecycle: reset page when controls change
    public function updatingSort()
    {
        $this->resetPage($this->pageName);
    }

    public function updatingDirection()
    {
        $this->resetPage($this->pageName);
    }

    public function updatingSearch()
    {
        $this->resetPage($this->pageName);
    }

    public function updatingKind()
    {
        $this->resetPage($this->pageName);
    }

    // pagination helpers (mirror leagues)
    public function goto(int $page): void
    {
        $this->gotoPage($page, $this->pageName);
    }

    public function prevPage(): void
    {
        $this->previousPage($this->pageName);
    }

    public function nextPage(): void
    {
        $this->nextPage($this->pageName);
    }

    // Keep dates in sync for single-day
    public function updatedCreateKind(string $value): void
    {
        if ($value === 'single_day') {
            $this->ends_on = $this->starts_on;
        }
    }

    public function updatedStartsOn(?string $date): void
    {
        if ($this->create_kind === 'single_day') {
            $this->ends_on = $date;
        }
    }

    // Optional: if user edits ends_on while single_day, snap it back
    public function updatedEndsOn(?string $date): void
    {
        if ($this->create_kind === 'single_day') {
            $this->ends_on = $this->starts_on;
        }
    }

    // computed: paginated events the user can manage
    public function getEventsProperty()
    {
        Gate::authorize('viewAny', Event::class);

        $base = Event::query()
            // ->where('owner_id', Auth::id()) // uncomment if you scope by owner like leagues
            ->whereIn('kind', ['single_day', 'multi_day']) // only manage single/multi day here
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$this->search}%")
                ->orWhere('location', 'like', "%{$this->search}%")
            ))
            ->when($this->kind, fn ($q) => $q->where('kind', $this->kind))
            ->orderBy($this->sort, $this->direction);

        $total = (clone $base)->count();
        $lastPage = max(1, (int) ceil($total / $this->perPage));

        $requested = (int) ($this->paginators[$this->pageName] ?? 1);
        $page = min(max(1, $requested), $lastPage);
        if ($requested !== $page) {
            $this->setPage($page, $this->pageName);
        }

        return $base->paginate($this->perPage, ['*'], $this->pageName, $page);
    }

    // paging window (mirror leagues)
    public function getPageWindowProperty(): array
    {
        $p = $this->events;
        $window = 2;
        $current = max(1, (int) $p->currentPage());
        $last = max(1, (int) $p->lastPage());
        $start = max(1, $current - $window);
        $end = min($last, $current + $window);

        return compact('current', 'last', 'start', 'end');
    }

    // sheet openers
    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showSheet = true;
    }

    public function openEdit(int $id): void
    {
        $event = Event::findOrFail($id);
        Gate::authorize('update', $event);

        $this->editingId = $event->id;
        $this->title = (string) $event->title;
        $this->location = $event->location;
        $this->create_kind = (string) ($event->kind->value ?? $event->kind); // enum-safe
        $this->starts_on = optional($event->starts_on)->toDateString();
        $this->ends_on = optional($event->ends_on)->toDateString();
        $this->is_published = (bool) $event->is_published;

        // Ensure single-day stays normalized in UI
        if ($this->create_kind === 'single_day') {
            $this->ends_on = $this->starts_on;
        }

        $this->showSheet = true;
    }

    // create or update (basics)
    public function save(): void
    {
        if ($this->editingId) {
            $event = Event::findOrFail($this->editingId);
            Gate::authorize('update', $event);
        } else {
            Gate::authorize('create', Event::class);
        }

        // Conditional validation rules
        $rules = [
            'title' => ['required', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:160'],
            'create_kind' => ['required', 'in:single_day,multi_day'],
            'starts_on' => ['required', 'date'],
            'is_published' => ['boolean'],
        ];
        if ($this->create_kind === 'single_day') {
            $rules['ends_on'] = ['required', 'date', 'same:starts_on'];
        } else {
            $rules['ends_on'] = ['required', 'date', 'after_or_equal:starts_on'];
        }

        $this->validate($rules, [
            'ends_on.same' => 'For single-day events, “Ends on” must match “Starts on”.',
        ]);

        // Normalize just in case
        if ($this->create_kind === 'single_day') {
            $this->ends_on = $this->starts_on;
        }

        if ($this->editingId) {
            // UPDATE
            $event->update([
                'title' => $this->title,
                'location' => $this->location,
                'kind' => $this->create_kind,
                'starts_on' => $this->starts_on,
                'ends_on' => $this->ends_on,
                'is_published' => $this->is_published,
            ]);

            $this->dispatch('toast', type: 'success', message: 'Event updated');
        } else {
            // CREATE
            Event::create([
                'title' => $this->title,
                'location' => $this->location,
                'kind' => $this->create_kind,
                'starts_on' => $this->starts_on,
                'ends_on' => $this->ends_on,
                'is_published' => $this->is_published,
                'public_uuid' => (string) Str::uuid(),
                'owner_id' => Auth::id(), // remove if your schema doesn’t have owner_id
            ]);

            $this->dispatch('toast', type: 'success', message: 'Event created');
        }

        $this->showSheet = false;
        $this->resetForm();
    }

    // inline actions (parity with leagues)
    public function togglePublish(int $id): void
    {
        $event = Event::findOrFail($id);
        Gate::authorize('update', $event);

        $event->update(['is_published' => ! $event->is_published]);
        $this->dispatch('toast', type: 'success', message: $event->is_published ? 'Published' : 'Unpublished');
    }

    public function delete(int $id): void
    {
        $event = Event::findOrFail($id);
        Gate::authorize('delete', $event);

        $event->delete();
        $this->dispatch('toast', type: 'success', message: 'Event deleted');
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->location = null;
        $this->create_kind = 'single_day';
        $this->starts_on = null;
        $this->ends_on = null;
        $this->is_published = false;
    }
};
?>

<section class="w-full">
    {{-- Header --}}
    <div class="mx-auto max-w-7xl">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">Events</h1>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    Manage single-day and multi-day events. (Leagues & BYC are managed in the Leagues area.)
                </p>
            </div>
            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <button wire:click="openCreate"
                        class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-xs
                               hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600
                               dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                    New event
                </button>
            </div>
        </div>

        <div class="mt-4 grid max-w-2xl grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <flux:input icon="magnifying-glass" placeholder="Search by title or location…" wire:model.live.debounce.300ms="search" />
            </div>
            <div>
                <flux:select wire:model.live="kind" class="w-full">
                    <option value="">All (single + multi)</option>
                    <option value="single_day">Single-day</option>
                    <option value="multi_day">Multi-day</option>
                </flux:select>
            </div>
        </div>
    </div>

    {{-- Table wrapper (mirrors leagues) --}}
    <div class="mt-6">
        <div class="mx-auto max-w-7xl">
            <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
                <table class="w-full text-left">
                    <thead class="bg-white dark:bg-gray-900">
                        <tr>
                            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Title</th>
                            <th class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 sm:table-cell dark:text-white">Kind</th>
                            <th class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 md:table-cell dark:text-white">Window</th>
                            <th class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 md:table-cell dark:text-white">Status</th>
                            <th class="py-3.5 pl-3 pr-4"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse($this->events as $e)
                            @php
                                $startStr = $e->starts_on ? \Illuminate\Support\Carbon::parse($e->starts_on)->format('Y-m-d') : '—';
                                $endStr   = $e->ends_on ? \Illuminate\Support\Carbon::parse($e->ends_on)->format('Y-m-d') : '—';
                            @endphp
                            <tr>
                                <td class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white">
                                    <a href="{{ route('corporate.events.show', $e) }}"
                                       class="hover:underline hover:text-indigo-600 dark:hover:text-indigo-400">
                                        {{ $e->title }}
                                    </a>
                                    <div class="text-xs opacity-60">{{ $e->location ?? '—' }}</div>
                                </td>
                                <td class="hidden px-3 py-4 text-sm text-gray-500 sm:table-cell dark:text-gray-400">
                                    {{ $e->kind_label }}
                                </td>
                                <td class="hidden px-3 py-4 text-sm text-gray-500 md:table-cell dark:text-gray-400">
                                    {{ $startStr }} → {{ $endStr }}
                                </td>
                                <td class="hidden px-3 py-4 text-sm text-gray-500 md:table-cell dark:text-gray-400">
                                    @if($e->is_published)
                                        <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-700/10 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-400/30">
                                            Published
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-700/10 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30">
                                            Draft
                                        </span>
                                    @endif
                                </td>
                                <td class="py-4 pl-3 pr-4 text-right text-sm font-medium">
                                    <div class="inline-flex items-center gap-1.5">
                                        {{-- Edit (sheet) --}}
                                        <flux:button
                                            variant="ghost"
                                            size="xs"
                                            icon="pencil-square"
                                            title="Edit"
                                            wire:click="openEdit({{ $e->id }})"
                                        >
                                            <span class="sr-only">Edit {{ $e->title }}</span>
                                        </flux:button>

                                        {{-- Keep your existing flows --}}
                                        <flux:button as="a" size="xs" href="{{ route('corporate.events.divisions', $e) }}" icon="user-group">
                                            Divisions
                                        </flux:button>
                                        <flux:button as="a" size="xs" href="{{ route('corporate.events.line_times', $e) }}" icon="clock">
                                            Line times
                                        </flux:button>
                                        <flux:button as="a" size="xs" href="{{ route('corporate.events.lane_map', $e) }}" icon="map">
                                            Lane map
                                        </flux:button>
                                        <flux:button as="a" size="xs" variant="ghost" href="{{ route('corporate.events.info.edit', $e) }}" icon="document-text">
                                            Info page
                                        </flux:button>

                                        {{-- Publish toggle --}}
                                        <flux:button
                                            variant="ghost"
                                            size="xs"
                                            icon="{{ $e->is_published ? 'eye-slash' : 'eye' }}"
                                            title="{{ $e->is_published ? 'Unpublish' : 'Publish' }}"
                                            wire:click="togglePublish({{ $e->id }})"
                                        >
                                            <span class="sr-only">{{ $e->is_published ? 'Unpublish' : 'Publish' }} {{ $e->title }}</span>
                                        </flux:button>

                                        {{-- Delete --}}
                                        <flux:button
                                            variant="ghost"
                                            size="xs"
                                            icon="trash"
                                            title="Delete"
                                            wire:click="delete({{ $e->id }})"
                                            class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
                                        >
                                            <span class="sr-only">Delete {{ $e->title }}</span>
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 px-4 text-sm text-gray-500 dark:text-gray-400">
                                    No events yet. Click “New event” to create your first one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- Pagination footer (mirror leagues) --}}
                @php($p = $this->events)
                @php($w = $this->pageWindow)
                <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 dark:border-white/10 dark:bg-transparent">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <button wire:click="prevPage" @disabled($p->onFirstPage()) class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">Previous</button>
                        <button wire:click="nextPage" @disabled(!$p->hasMorePages()) class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">Next</button>
                    </div>

                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                Showing <span class="font-medium">{{ $p->firstItem() ?? 0 }}</span>
                                to <span class="font-medium">{{ $p->lastItem() ?? 0 }}</span>
                                of <span class="font-medium">{{ $p->total() }}</span> results
                            </p>
                        </div>
                        <div>
                            <nav aria-label="Pagination" class="isolate inline-flex -space-x-px rounded-md shadow-xs dark:shadow-none">
                                <button wire:click="prevPage" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:inset-ring-gray-700 dark:hover:bg-white/5" @disabled($p->onFirstPage())>
                                    <span class="sr-only">Previous</span>
                                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5"><path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" fill-rule="evenodd" /></svg>
                                </button>

                                @for ($i = $w['start']; $i <= $w['end']; $i++)
                                    @if ($i === $w['current'])
                                        <span aria-current="page" class="relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:focus-visible:outline-indigo-500">{{ $i }}</span>
                                    @else
                                        <button wire:click="goto({{ $i }})" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:text-gray-200 dark:inset-ring-gray-700 dark:hover:bg:white/5">{{ $i }}</button>
                                    @endif
                                @endfor

                                <button wire:click="nextPage" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:inset-ring-gray-700 dark:hover:bg-white/5" @disabled(!$p->hasMorePages())>
                                    <span class="sr-only">Next</span>
                                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5"><path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" /></svg>
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div> {{-- /bordered wrapper --}}
        </div>
    </div>

    {{-- Right "sheet" for create/edit (mirror leagues UX) --}}
    @if($showSheet)
        <div class="fixed inset-0 z-40">
            <div class="absolute inset-0 bg-black/40" wire:click="$set('showSheet', false)"></div>

            <div class="absolute inset-y-0 right-0 w-full max-w-2xl h-full overflow-y-auto bg-white p-6 shadow-xl dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $editingId ? 'Edit event' : 'Create event' }}
                    </h2>
                    <button class="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10"
                            wire:click="$set('showSheet', false)">✕</button>
                </div>

                <form wire:submit.prevent="save" class="mt-6 space-y-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <flux:label for="title">Event title</flux:label>
                            <flux:input id="title" type="text" wire:model="title" placeholder="2026 Indoor Classic" />
                            @error('title') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                        </div>
                        <div>
                            <flux:label for="location">Location</flux:label>
                            <flux:input id="location" type="text" wire:model="location" placeholder="Club/Range name" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3" wire:key="dates-row-{{ $create_kind }}">
                        <div>
                            <flux:label for="create_kind">Kind</flux:label>
                            <flux:select id="create_kind" wire:model="create_kind" class="w-full">
                                <option value="single_day">Single-day</option>
                                <option value="multi_day">Multi-day</option>
                            </flux:select>
                            @error('create_kind') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                        </div>
                        <div>
                            <flux:label for="starts_on">Starts on</flux:label>
                            <flux:input id="starts_on" type="date" wire:model="starts_on" />
                            @error('starts_on') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                        </div>
                        <div>
                            <flux:label for="ends_on">Ends on</flux:label>
                            <flux:input id="ends_on" type="date" wire:model="ends_on" />
                            @error('ends_on') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                            <p class="mt-1 text-xs opacity-70">Single-day events automatically set “Ends on” to match “Starts on”.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <flux:switch id="is_published" wire:model="is_published" />
                        <flux:label for="is_published">Published</flux:label>
                    </div>

                    <div class="flex justify-end gap-3">
                        <flux:button type="button" variant="ghost" wire:click="$set('showSheet', false)">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create' }}</flux:button>
                    </div>

                    <p class="text-xs opacity-60 pt-2">
                        After saving, use the buttons on the list (Basics, Divisions, Line times, Lane map, Info page) to finish setup.
                    </p>
                </form>
            </div>
        </div>
    @endif
</section>
