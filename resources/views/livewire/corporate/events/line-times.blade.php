<?php

use App\Models\Event;
use App\Models\EventLineTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    public Event $event;

    public ?string $label = null;

    public ?string $starts_at = null; // local datetime-local input

    public ?string $ends_at = null;

    public ?int $capacity = null;

    public function mount(Event $event): void
    {
        Gate::authorize('update', $event);
        $this->event = $event;
    }

    public function add(): void
    {
        $this->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        EventLineTime::create([
            'event_id' => $this->event->id,
            'label' => $this->label ?: null,
            'starts_at' => Carbon::parse($this->starts_at),
            'ends_at' => Carbon::parse($this->ends_at),
            'capacity' => $this->capacity,
        ]);

        $this->label = null;
        $this->starts_at = null;
        $this->ends_at = null;
        $this->capacity = null;

        $this->dispatch('toast', type: 'success', message: 'Line time added.');
    }

    public function delete(int $id): void
    {
        EventLineTime::where('event_id', $this->event->id)->where('id', $id)->delete();
    }

    public function with(): array
    {
        $rows = EventLineTime::where('event_id', $this->event->id)->orderBy('starts_at')->get();

        return ['times' => $rows];
    }
}; ?>


  <div class="mx-auto max-w-5xl space-y-6">
    <div class="flex items-end justify-between">
      <div>
        <h1 class="text-base font-semibold text-gray-900 dark:text-white">Line times</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400">Define shooting sessions for this event.</p>
      </div>
      <div class="flex gap-2">
        <flux:button as="a" href="{{ route('corporate.events.divisions', $event) }}" variant="ghost">Back</flux:button>
        <flux:button as="a" href="{{ route('corporate.events.lane_map', $event) }}">Next: Lane map</flux:button>
      </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-neutral-900">
      <div class="grid gap-4 sm:grid-cols-4">
        <flux:input wire:model.defer="label" label="Label (optional)" placeholder="Saturday AM" />
        <flux:input type="datetime-local" wire:model.defer="starts_at" label="Starts at" />
        <flux:input type="datetime-local" wire:model.defer="ends_at" label="Ends at" />
        <flux:input type="number" min="1" wire:model.defer="capacity" label="Capacity (optional)" />
      </div>
      <div class="mt-4">
        <flux:button variant="primary" wire:click="add">Add line time</flux:button>
      </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
      <table class="w-full text-left">
        <thead class="bg-white dark:bg-neutral-900">
          <tr>
            <th class="px-4 py-3 text-sm font-semibold">Label</th>
            <th class="px-4 py-3 text-sm font-semibold">Starts</th>
            <th class="px-4 py-3 text-sm font-semibold">Ends</th>
            <th class="px-4 py-3 text-sm font-semibold">Capacity</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
          @forelse($times as $t)
            <tr>
              <td class="px-4 py-3 text-sm">{{ $t->label ?? '—' }}</td>
              <td class="px-4 py-3 text-sm">{{ optional($t->starts_at)->format('Y-m-d H:i') }}</td>
              <td class="px-4 py-3 text-sm">{{ optional($t->ends_at)->format('Y-m-d H:i') }}</td>
              <td class="px-4 py-3 text-sm">{{ $t->capacity ?? '—' }}</td>
              <td class="px-4 py-3"><flux:button size="sm" variant="ghost" wire:click="delete({{ $t->id }})">Delete</flux:button></td>
            </tr>
          @empty
            <tr><td class="px-4 py-6 text-sm text-gray-500" colspan="5">No line times.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

