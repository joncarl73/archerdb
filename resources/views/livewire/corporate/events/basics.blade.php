<?php

use App\Enums\EventKind;
use App\Enums\EventScoringMode;
use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum as EnumRule;
use Livewire\Volt\Component;

new class extends Component
{
    public ?Event $event = null;

    // Bind simple strings in the UI (not enum objects)
    public string $title = '';

    public string $kind = 'tournament';        // EventKind backed value

    public string $scoring_mode = 'personal';  // EventScoringMode backed value

    public bool $is_published = false;

    public ?string $starts_on = null;          // YYYY-MM-DD

    public ?string $ends_on = null;            // YYYY-MM-DD

    public function mount(?Event $event = null): void
    {
        if ($event) {
            Gate::authorize('update', $event);

            $this->event = $event;
            $this->title = (string) $event->title;
            $this->kind = $event->kind instanceof \BackedEnum ? $event->kind->value : (string) $event->kind;
            $this->scoring_mode = $event->scoring_mode instanceof \BackedEnum ? $event->scoring_mode->value : (string) $event->scoring_mode;
            $this->is_published = (bool) $event->is_published;
            $this->starts_on = $event->starts_on?->format('Y-m-d');
            $this->ends_on = $event->ends_on?->format('Y-m-d');
        } else {
            Gate::authorize('create', Event::class);
        }
    }

    public function save(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['required', new EnumRule(EventKind::class)],
            'scoring_mode' => ['required', new EnumRule(EventScoringMode::class)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_published' => ['boolean'],
        ]);

        $e = $this->event ?? new Event([
            'owner_id' => auth()->id(),
            'public_uuid' => (string) Str::uuid(),
        ]);

        $e->title = $this->title;
        $e->kind = EventKind::from($this->kind);                 // map back to enum
        $e->scoring_mode = EventScoringMode::from($this->scoring_mode);  // map back to enum
        $e->is_published = $this->is_published;
        $e->starts_on = $this->starts_on ? Carbon::createFromFormat('Y-m-d', $this->starts_on) : null;
        $e->ends_on = $this->ends_on ? Carbon::createFromFormat('Y-m-d', $this->ends_on) : null;

        $e->save();

        $this->event = $e;

        $this->dispatch('toast', type: 'success', message: 'Basics saved.');

        // If this was the "new" page, move to Divisions next
        if (request()->routeIs('corporate.events.create')) {
            redirect()->route('corporate.events.divisions', $e);
        }
    }
}; ?>

@php
  $kinds = \App\Enums\EventKind::cases();
  $modes = \App\Enums\EventScoringMode::cases();
@endphp

<div class="mx-auto max-w-3xl space-y-6">
  <div>
    <h1 class="text-base font-semibold text-gray-900 dark:text-white">
      {{ $event ? 'Edit event basics' : 'Create a new event' }}
    </h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
      Title, kind, scoring mode, dates & publish.
    </p>
  </div>

  <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-neutral-900">
    <div class="grid gap-4">
      <flux:input wire:model.defer="title" label="Title" />

      <div class="grid gap-4 sm:grid-cols-2">
        <flux:select wire:model.defer="kind" label="Kind">
          @foreach ($kinds as $case)
            <option value="{{ $case->value }}">
              {{ method_exists($case, 'label') ? $case->label() : \Illuminate\Support\Str::headline($case->value) }}
            </option>
          @endforeach
        </flux:select>

        <flux:select wire:model.defer="scoring_mode" label="Scoring mode">
          @foreach ($modes as $case)
            <option value="{{ $case->value }}">
              {{ method_exists($case, 'label') ? $case->label() : \Illuminate\Support\Str::headline($case->value) }}
            </option>
          @endforeach
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

      <div class="flex items-center justify-between">
        <div class="flex gap-2">
          @if ($event)
            <flux:button as="a" variant="ghost" href="{{ route('corporate.events.info.edit', $event) }}">
              Info page
            </flux:button>
          @endif
        </div>

        <div class="flex gap-2">
          <flux:button variant="primary" wire:click="save">Save</flux:button>

          @if ($event)
            <flux:button as="a" href="{{ route('corporate.events.divisions', $event) }}">
              Next: Divisions
            </flux:button>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
