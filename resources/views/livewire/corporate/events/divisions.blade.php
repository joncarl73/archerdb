<?php

use App\Models\Event;
use App\Models\EventDivision;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    public Event $event;

    public string $name = '';

    public ?string $rules = null;    // JSON

    public ?int $capacity = null;

    public function mount(Event $event): void
    {
        Gate::authorize('update', $event);
        $this->event = $event;
    }

    public function add(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'rules' => ['nullable', 'string'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        // validate JSON if provided
        $rulesArr = null;
        if ($this->rules !== null && $this->rules !== '') {
            try {
                $rulesArr = json_decode($this->rules, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $this->addError('rules', 'Rules must be valid JSON.');

                return;
            }
        }

        EventDivision::create([
            'event_id' => $this->event->id,
            'name' => $this->name,
            'rules' => $rulesArr,
            'capacity' => $this->capacity,
        ]);

        $this->name = '';
        $this->rules = null;
        $this->capacity = null;

        $this->dispatch('toast', type: 'success', message: 'Division added.');
    }

    public function delete(int $id): void
    {
        EventDivision::where('event_id', $this->event->id)->where('id', $id)->delete();
    }

    public function with(): array
    {
        $divs = EventDivision::where('event_id', $this->event->id)->orderBy('name')->get();

        return ['divisions' => $divs];
    }
}; ?>


  <div class="mx-auto max-w-5xl space-y-6">
    <div class="flex items-end justify-between">
      <div>
        <h1 class="text-base font-semibold text-gray-900 dark:text-white">Divisions</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400">Define age/class/equipment buckets. Optional JSON rules for logic.</p>
      </div>
      <div class="flex gap-2">
        <flux:button as="a" href="{{ route('corporate.events.basics', $event) }}" variant="ghost">Back</flux:button>
        <flux:button as="a" href="{{ route('corporate.events.line_times', $event) }}">Next: Line times</flux:button>
      </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-neutral-900">
      <div class="grid gap-4 sm:grid-cols-3">
        <flux:input wire:model.defer="name" label="Name" placeholder="Recurve Women 18m" />
        <flux:input wire:model.defer="capacity" type="number" min="1" label="Capacity (optional)" />
        <div class="sm:col-span-3">
          <flux:textarea wire:model.defer="rules" label="Rules (JSON, optional)" placeholder='{"ageMax": 18, "equipment": "recurve"}' rows="4" />
        </div>
      </div>
      <div class="mt-4">
        <flux:button variant="primary" wire:click="add">Add division</flux:button>
      </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
      <table class="w-full text-left">
        <thead class="bg-white dark:bg-neutral-900">
          <tr>
            <th class="px-4 py-3 text-sm font-semibold">Name</th>
            <th class="px-4 py-3 text-sm font-semibold">Capacity</th>
            <th class="px-4 py-3 text-sm font-semibold">Rules</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
          @forelse($divisions as $d)
            <tr>
              <td class="px-4 py-3 text-sm">{{ $d->name }}</td>
              <td class="px-4 py-3 text-sm">{{ $d->capacity ?? '—' }}</td>
              <td class="px-4 py-3 text-xs">
                <pre class="whitespace-pre-wrap text-xs">{{ $d->rules ? json_encode($d->rules, JSON_PRETTY_PRINT) : '—' }}</pre>
              </td>
              <td class="px-4 py-3">
                <flux:button size="sm" variant="ghost" wire:click="delete({{ $d->id }})">Delete</flux:button>
              </td>
            </tr>
          @empty
            <tr><td class="px-4 py-6 text-sm text-gray-500" colspan="4">No divisions.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

