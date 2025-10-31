<?php
use App\Enums\EventKind;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    public string $title = '';

    public ?string $location = null;

    public string $kind = EventKind::SingleDay->value;  // 'single_day'

    public ?string $starts_on = null;

    public ?string $ends_on = null;

    public bool $is_published = false;

    public function mount(): void
    {
        Gate::authorize('create', Event::class);
    }

    public function save(): RedirectResponse
    {
        // Validate inputs
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['required'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date'],
        ]);

        // Normalize dates based on kind
        if ($this->kind === EventKind::SingleDay->value) {
            $this->ends_on = $this->starts_on;
        } elseif (! $this->ends_on) {
            $this->addError('ends_on', 'End date is required for multi-day events.');

            // Return to the same page with validation error; no redirect
            return back();
        }

        $event = Event::create([
            'company_id' => auth()->user()->company_id,
            'title' => trim($this->title),
            'location' => $this->location,
            'kind' => $this->kind,
            'starts_on' => $this->starts_on,
            'ends_on' => $this->ends_on,
            'is_published' => $this->is_published,
        ]);

        $event->collaborators()->syncWithoutDetaching([
            auth()->id() => ['role' => 'owner'],
        ]);

        // ✅ Return a real RedirectResponse (not Livewire Redirector)
        // Change the route name below if your “show” route is different.
        return to_route('corporate.events.show', ['event' => $event->id])
            ->with('ok', 'Event created.');
    }
}; ?>

<div class="mx-auto max-w-3xl">
  <flux:text as="h1" class="text-2xl font-semibold">New event</flux:text>

  <div class="mt-6 space-y-5">
    <div class="grid gap-5 md:grid-cols-2">
      <div>
        <flux:label>Title</flux:label>
        <flux:input wire:model.defer="title" placeholder="Indoor 600 — January" />
        @error('title') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
      </div>

      <div>
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

      <div>
        <flux:label>Starts on</flux:label>
        <flux:input type="date" wire:model="starts_on" />
        @error('starts_on') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
      </div>

      <div>
        <flux:label>Ends on</flux:label>
        {{-- Disable when single-day --}}
        <flux:input type="date" wire:model="ends_on" @disabled($kind === 'single_day') />
        @error('ends_on') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
      </div>

      <div class="flex items-center gap-3">
        <flux:checkbox wire:model="is_published" />
        <flux:label class="!mb-0">Published</flux:label>
      </div>
    </div>

    <div class="flex gap-2">
      <flux:button icon="plus" wire:click="save">Create event</flux:button>
      <a href="{{ route('corporate.events.index') }}"><flux:button appearance="secondary">Cancel</flux:button></a>
    </div>
  </div>
</div>
