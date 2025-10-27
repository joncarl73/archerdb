<?php
use App\Enums\EventKind;
use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    public Event $event;

    public string $title = '';

    public ?string $location = null;

    public string $kind = 'single_day';

    public ?string $starts_on = null;

    public ?string $ends_on = null;

    public bool $is_published = false;

    public function mount(Event $event)
    {
        Gate::authorize('update', $event);
        $this->event = $event;
        $this->title = $event->title;
        $this->location = $event->location;
        $this->kind = $event->kind->value;
        $this->starts_on = $event->starts_on?->toDateString();
        $this->ends_on = $event->ends_on?->toDateString();
        $this->is_published = $event->is_published;
    }

    public function save()
    {
        // 🔒 All keys QUOTED
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['required'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date'],
        ]);

        if ($this->kind === EventKind::SingleDay->value) {
            $this->ends_on = $this->starts_on;
        } elseif (! $this->ends_on) {
            $this->addError('ends_on', 'End date is required for multi-day events.');

            return;
        }

        // 🔒 All keys QUOTED
        $this->event->update([
            'title' => $this->title,
            'location' => $this->location,
            'kind' => $this->kind,
            'starts_on' => $this->starts_on,
            'ends_on' => $this->ends_on,
            'is_published' => $this->is_published,
        ]);

        session()->flash('success', 'Event updated');
    }
}; ?>

<div class="mx-auto max-w-3xl">
  <div class="flex items-center justify-between">
    <flux:text as="h1" class="text-2xl font-semibold">{{ $title ?: 'Event basics' }}</flux:text>
    <a href="{{ route('corporate.events.index') }}"><flux:button appearance="secondary" icon="arrow-left">Back</flux:button></a>
  </div>

  <div class="mt-6 space-y-5">
    <div class="grid gap-5 md:grid-cols-2">
      <div>
        <flux:label>Title</flux:label>
        <flux:input wire:model.defer="title" />
      </div>

      <div>
        <flux:label>Location</flux:label>
        <flux:input wire:model.defer="location" />
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
      </div>

      <div>
        <flux:label>Ends on</flux:label>
        {{-- ✅ Use @disabled to avoid PHP touching Alpine/Livewire syntax --}}
        <flux:input type="date" wire:model="ends_on" @disabled($kind === 'single_day') />
      </div>

      <div class="flex items-center gap-3">
        <flux:checkbox wire:model="is_published" />
        <flux:label class="!mb-0">Published</flux:label>
      </div>
    </div>

    <flux:button icon="check" wire:click="save">Save changes</flux:button>
  </div>
</div>
