<?php

use App\Models\Event;
use App\Models\EventLaneMap;
use App\Models\EventLineTime;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    public Event $event;

    // Builder inputs
    public ?int $line_time_id = null;   // nullable → applies to "no line time"

    public int $lanes = 10;

    public string $slot_plan = 'single'; // single | AB | ABCD

    public int $slot_capacity = 1;

    public function mount(Event $event): void
    {
        Gate::authorize('update', $event);
        $this->event = $event;
    }

    public function generate(): void
    {
        $this->validate([
            'lanes' => ['required', 'integer', 'min:1', 'max:200'],
            'slot_plan' => ['required', 'in:single,AB,ABCD'],
            'slot_capacity' => ['required', 'integer', 'min:1', 'max:8'],
            'line_time_id' => ['nullable', 'integer'],
        ]);

        $slots = match ($this->slot_plan) {
            'single' => [null], // no letter
            'AB' => ['A', 'B'],
            'ABCD' => ['A', 'B', 'C', 'D'],
        };

        // Wipe existing map for this (event, line_time_id) to avoid dupes
        EventLaneMap::where('event_id', $this->event->id)
            ->where('line_time_id', $this->line_time_id)
            ->delete();

        $bulk = [];
        for ($lane = 1; $lane <= $this->lanes; $lane++) {
            foreach ($slots as $s) {
                $bulk[] = [
                    'event_id' => $this->event->id,
                    'line_time_id' => $this->line_time_id,
                    'lane_number' => $lane,
                    'slot' => $s,
                    'capacity' => $this->slot_capacity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        // Minimize deadlocks for big inserts
        foreach (array_chunk($bulk, 500) as $chunk) {
            EventLaneMap::insert($chunk);
        }

        $this->dispatch('toast', type: 'success', message: 'Lane map generated.');
    }

    public function clear(): void
    {
        EventLaneMap::where('event_id', $this->event->id)
            ->where('line_time_id', $this->line_time_id)
            ->delete();

        $this->dispatch('toast', type: 'success', message: 'Lane map cleared.');
    }

    public function with(): array
    {
        $times = EventLineTime::where('event_id', $this->event->id)->orderBy('starts_at')->get();
        $rows = EventLaneMap::where('event_id', $this->event->id)
            ->when($this->line_time_id !== null, fn ($q) => $q->where('line_time_id', $this->line_time_id))
            ->orderBy('lane_number')->orderBy('slot')->get();

        return ['times' => $times, 'map' => $rows];
    }
}; ?>


  <div class="mx-auto max-w-5xl space-y-6">
    <div class="flex items-end justify-between">
      <div>
        <h1 class="text-base font-semibold text-gray-900 dark:text-white">Lane map</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400">Auto-generate lanes & slots; per line time or global.</p>
      </div>
      <div class="flex gap-2">
        <flux:button as="a" href="{{ route('corporate.events.line_times', $event) }}" variant="ghost">Back</flux:button>
        <flux:button as="a" href="{{ route('corporate.events.info.edit', $event) }}">Next: Info page</flux:button>
      </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-neutral-900">
      <div class="grid gap-4 sm:grid-cols-5">
        <div class="sm:col-span-2">
          <flux:label>Line time</flux:label>
          <flux:select wire:model.live="line_time_id" class="w-full">
            <option value="">— No specific line time —</option>
            @foreach($times as $t)
              <option value="{{ $t->id }}">
                {{ $t->label ? $t->label.' — ' : '' }}{{ optional($t->starts_at)->format('M j, H:i') }}
              </option>
            @endforeach
          </flux:select>
        </div>
        <flux:input type="number" min="1" max="200" wire:model.defer="lanes" label="# Lanes" />
        <flux:select wire:model.defer="slot_plan" label="Slots">
          <option value="single">Single</option>
          <option value="AB">A/B</option>
          <option value="ABCD">A/B/C/D</option>
        </flux:select>
        <flux:input type="number" min="1" max="8" wire:model.defer="slot_capacity" label="Capacity / slot" />
      </div>

      <div class="mt-4 flex gap-2">
        <flux:button variant="primary" wire:click="generate">Generate</flux:button>
        <flux:button variant="ghost" wire:click="clear">Clear</flux:button>
      </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
      <table class="w-full text-left">
        <thead class="bg-white dark:bg-neutral-900">
          <tr>
            <th class="px-4 py-3 text-sm font-semibold">Lane</th>
            <th class="px-4 py-3 text-sm font-semibold">Slot</th>
            <th class="px-4 py-3 text-sm font-semibold">Capacity</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
          @forelse($map as $row)
            <tr>
              <td class="px-4 py-3 text-sm">{{ $row->lane_number }}</td>
              <td class="px-4 py-3 text-sm">{{ $row->slot ?? '—' }}</td>
              <td class="px-4 py-3 text-sm">{{ $row->capacity }}</td>
            </tr>
          @empty
            <tr><td class="px-4 py-6 text-sm text-gray-500" colspan="3">No lane map for the selected context.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

