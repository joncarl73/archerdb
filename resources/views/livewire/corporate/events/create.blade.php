<?php

use App\Enums\EventKind;
use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    // Form fields
    public string $title = '';

    public string $kind = 'tournament';       // default; will still be validated against EventKind::cases()

    public string $scoring_mode = 'personal'; // personal | kiosk | tablet

    public bool $is_published = false;

    public ?string $starts_on = null;         // YYYY-MM-DD

    public ?string $ends_on = null;           // YYYY-MM-DD

    public function mount(): void
    {
        Gate::authorize('create', Event::class);

        // If default isn't in enum for some reason, fall back to the first enum case
        $enumValues = array_map(fn ($c) => $c->value, EventKind::cases());
        if (! in_array($this->kind, $enumValues, true)) {
            $this->kind = $enumValues[0] ?? 'tournament';
        }
    }

    public function save(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in(array_map(fn ($c) => $c->value, EventKind::cases()))],
            'scoring_mode' => ['required', Rule::in(['personal', 'kiosk', 'tablet'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_published' => ['boolean'],
        ]);

        $event = new Event;
        $event->owner_id = auth()->id();
        $event->public_uuid = (string) \Illuminate\Support\Str::uuid();

        $event->fill([
            'title' => $this->title,
            'kind' => $this->kind, // store the enum VALUE (string) in DB
            'scoring_mode' => $this->scoring_mode,
            'is_published' => $this->is_published,
            'starts_on' => $this->starts_on ? Carbon::createFromFormat('Y-m-d', $this->starts_on) : null,
            'ends_on' => $this->ends_on ? Carbon::createFromFormat('Y-m-d', $this->ends_on) : null,
        ])->save();

        $this->dispatch('toast', type: 'success', message: 'Event created.');

        // After create, send them to Divisions
        redirect()->route('corporate.events.divisions', $event);
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6">
  <div>
    <h1 class="text-base font-semibold text-gray-900 dark:text-white">
      Create a new event
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
      Title, kind, scoring mode, dates & publish.
    </p>
  </div>

  <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-neutral-900">
    <div class="grid gap-4">
      <flux:input wire:model.defer="title" label="Title" />

      <div class="grid gap-4 sm:grid-cols-2">
        {{-- Kind from Enum --}}
        <flux:select wire:model.defer="kind" label="Kind">
          @foreach (\App\Enums\EventKind::cases() as $case)
            <option value="{{ $case->value }}">{{ Str::headline($case->value) }}</option>
          @endforeach
        </flux:select>

        {{-- Scoring mode (string-backed for now) --}}
        <flux:select wire:model.defer="scoring_mode" label="Scoring mode">
          <option value="personal">Personal device</option>
          <option value="kiosk">Kiosk</option>
          <option value="tablet">Tablet</option>
        </flux:select>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <flux:input type="date" wire:model.defer="starts_on" label="Starts on" />
        <flux:input type="date" wire:model.defer="ends_on" label="Ends on" />
      </div>

      <div class="flex items-center gap-2">
        <flux:checkbox wire:model="is_published" />
        <flux:label>Published</flux:label>
      </div>

      <div class="flex items-center justify-end">
        <flux:button variant="primary" wire:click="save">Create event</flux:button>
      </div>
    </div>
  </div>
</div>
