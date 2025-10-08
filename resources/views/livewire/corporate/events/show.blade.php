<?php
use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    public Event $event;

    // edit sheet state
    public bool $showEditSheet = false;

    // form fields
    public string $title = '';

    public ?string $location = null;

    public string $create_kind = 'single_day'; // single_day|multi_day

    public ?string $starts_on = null;          // Y-m-d

    public ?string $ends_on = null;            // Y-m-d

    public bool $is_published = false;

    // derived/links
    public string $publicInfoUrl = '';

    public function mount(Event $event): void
    {
        Gate::authorize('view', $event);
        $this->event = $event->load(['divisions', 'lineTimes', 'laneMaps', 'info']);

        // hydrate form (keeps edit sheet consistent with index)
        $this->fillFromModel();

        // guess your public page route name; adjust if different
        $this->publicInfoUrl = route('public.event.info', ['uuid' => $this->event->public_uuid]);
    }

    private function fillFromModel(): void
    {
        $e = $this->event;
        $this->title = (string) $e->title;
        $this->location = $e->location;
        $this->create_kind = (string) ($e->kind->value ?? $e->kind);
        $this->starts_on = optional($e->starts_on)->toDateString();
        $this->ends_on = optional($e->ends_on)->toDateString();
        $this->is_published = (bool) $e->is_published;

        if ($this->create_kind === 'single_day') {
            $this->ends_on = $this->starts_on;
        }
    }

    // open/close edit
    public function openEdit(): void
    {
        Gate::authorize('update', $this->event);
        $this->fillFromModel();
        $this->showEditSheet = true;
    }

    public function save(): void
    {
        Gate::authorize('update', $this->event);

        // conditional validation (single_day => same date)
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

        if ($this->create_kind === 'single_day') {
            $this->ends_on = $this->starts_on;
        }

        $this->event->update([
            'title' => $this->title,
            'location' => $this->location,
            'kind' => $this->create_kind,
            'starts_on' => $this->starts_on,
            'ends_on' => $this->ends_on,
            'is_published' => $this->is_published,
        ]);

        // keep the page model fresh
        $this->event->refresh();
        $this->showEditSheet = false;
        $this->dispatch('toast', type: 'success', message: 'Event updated');
    }

    // keep dates in sync when editing
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

    public function updatedEndsOn(?string $date): void
    {
        if ($this->create_kind === 'single_day') {
            $this->ends_on = $this->starts_on;
        }
    }

    public function togglePublish(): void
    {
        Gate::authorize('update', $this->event);
        $this->event->update(['is_published' => ! $this->event->is_published]);
        $this->event->refresh();
        $this->is_published = (bool) $this->event->is_published;
        $this->dispatch('toast', type: 'success', message: $this->event->is_published ? 'Published' : 'Unpublished');
    }
};
?>

<section class="w-full">
    @php
        $kindLabel = $event->kind_label;
        $startStr  = $event->starts_on ? \Illuminate\Support\Carbon::parse($event->starts_on)->format('Y-m-d') : '—';
        $endStr    = $event->ends_on ? \Illuminate\Support\Carbon::parse($event->ends_on)->format('Y-m-d') : '—';
    @endphp

    {{-- Header --}}
    <div class="mx-auto max-w-7xl">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">{{ $event->title }}</h1>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    {{ $event->location ?: '—' }} • {{ $kindLabel }} • {{ $startStr }} → {{ $endStr }}
                </p>
            </div>

            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <div class="flex items-center gap-2">
                    <flux:button variant="primary" icon="pencil-square" wire:click="openEdit">
                        Edit event
                    </flux:button>

                    <flux:dropdown>
                        <flux:button icon:trailing="chevron-down">Actions</flux:button>
                        <flux:menu class="min-w-64">
                            <flux:menu.item href="{{ route('corporate.events.info.edit', $event) }}" icon="pencil-square">
                                Create/Update event info
                            </flux:menu.item>
                            <flux:menu.item href="{{ $publicInfoUrl }}" target="_blank" icon="arrow-top-right-on-square">
                                View public page
                            </flux:menu.item>
                            <flux:menu.item wire:click="togglePublish" icon="{{ $event->is_published ? 'eye-slash' : 'eye' }}">
                                {{ $event->is_published ? 'Unpublish' : 'Publish' }}
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </div>
        </div>
    </div>

    {{-- Setup cards (Divisions / Line Times / Lane Map) --}}
    <div class="mx-auto max-w-7xl mt-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
            <div class="text-sm font-medium text-gray-900 dark:text-white">Divisions</div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                {{ $event->divisions->count() }} configured
            </p>
            <div class="mt-3">
                <flux:button as="a" href="{{ route('corporate.events.divisions', $event) }}" icon="user-group">
                    Manage divisions
                </flux:button>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
            <div class="text-sm font-medium text-gray-900 dark:text-white">Line times</div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                {{ $event->lineTimes->count() }} time slots
            </p>
            <div class="mt-3">
                <flux:button as="a" href="{{ route('corporate.events.line_times', $event) }}" icon="clock">
                    Manage line times
                </flux:button>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
            <div class="text-sm font-medium text-gray-900 dark:text-white">Lane map</div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                {{ $event->laneMaps->count() }} map{{ $event->laneMaps->count() === 1 ? '' : 's' }}
            </p>
            <div class="mt-3">
                <flux:button as="a" href="{{ route('corporate.events.lane_map', $event) }}" icon="map">
                    Edit lane map
                </flux:button>
            </div>
        </div>
    </div>

    {{-- (Optional) Info quick link --}}
    <div class="mx-auto max-w-7xl mt-6">
        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
            <div class="text-sm font-medium text-gray-900 dark:text-white">Public info page</div>
            <div class="mt-2 flex items-center gap-2">
                <input type="text"
                       readonly
                       value="{{ $publicInfoUrl }}"
                       class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-xs
                              focus:border-indigo-500 focus:ring-2 focus:ring-indigo-600 dark:border-white/10 dark:bg-white/5
                              dark:text-gray-200 dark:focus:border-indigo-400 dark:focus:ring-indigo-400" />
                <a href="{{ $publicInfoUrl }}" target="_blank"
                   class="rounded-md bg-white px-3 py-2 text-sm font-medium inset-ring inset-ring-gray-300 hover:bg-gray-50
                          dark:bg-white/5 dark:text-gray-200 dark:inset-ring-white/10 dark:hover:bg-white/10">
                    Open
                </a>
            </div>
            <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                Use the “Create/Update event info” action to edit details shown publicly.
            </p>
        </div>
    </div>

    {{-- Right "sheet" for edit basics --}}
    @if($showEditSheet)
        <div class="fixed inset-0 z-40">
            <div class="absolute inset-0 bg-black/40" wire:click="$set('showEditSheet', false)"></div>

            <div class="absolute inset-y-0 right-0 w-full max-w-2xl h-full overflow-y-auto bg-white p-6 shadow-xl dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Edit event</h2>
                    <button class="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10"
                            wire:click="$set('showEditSheet', false)">✕</button>
                </div>

                <form wire:submit.prevent="save" class="mt-6 space-y-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <flux:label for="title">Event title</flux:label>
                            <flux:input id="title" type="text" wire:model="title" />
                            @error('title') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                        </div>
                        <div>
                            <flux:label for="location">Location</flux:label>
                            <flux:input id="location" type="text" wire:model="location" />
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
                        <flux:button type="button" variant="ghost" wire:click="$set('showEditSheet', false)">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">Save changes</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</section>
