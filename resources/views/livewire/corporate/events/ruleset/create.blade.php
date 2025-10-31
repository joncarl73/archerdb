<?php
use App\Models\Event;
use App\Models\Ruleset;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component
{
    public Event $event;

    public ?int $baseId = null;

    public string $name = '';

    public string $description = '';

    public array $schema = [];

    public function mount()
    {
        if ($this->event->ruleset) {
            $this->baseId = $this->event->ruleset->id;
            $this->schema = $this->event->ruleset->schema;
            $this->name = $this->event->ruleset->name.' (Custom)';
            $this->description = 'Customized from '.$this->event->ruleset->name;
        }
    }

    public function chooseBase($id)
    {
        $b = Ruleset::findOrFail($id);
        $this->baseId = $b->id;
        $this->schema = $b->schema;
        if (! $this->name) {
            $this->name = $b->name.' (Custom)';
        }
    }

    public function save()
    {
        $r = Ruleset::create([
            'org' => 'CUSTOM',
            'name' => $this->name,
            'slug' => Str::slug($this->name).'-'.Str::random(5),
            'description' => $this->description,
            'is_system' => false,
            'schema' => $this->schema,
        ]);
        $this->event->update(['ruleset_id' => $r->id]);

        return redirect()->route('events.ruleset.show', ['event' => $this->event->id])
            ->with('ok', 'Custom ruleset created and assigned.');
    }
};
?>
<div class="space-y-4">
  <x-flux::header title="Create Custom Ruleset" subtitle="Start from the current ruleset or pick another as a template" />
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <x-flux::card class="md:col-span-1">
      <x-flux::card.header title="Template"/>
      <x-flux::card.content>
        <livewire:common.ruleset-picker wire:onSelect="chooseBase" />
      </x-flux::card.content>
    </x-flux::card>

    <x-flux::card class="md:col-span-2">
      <x-flux::card.header title="Details"/>
      <x-flux::card.content class="space-y-3">
        <x-flux::input label="Name" wire:model="name" />
        <x-flux::textarea label="Description" rows="3" wire:model="description" />
        <label class="text-sm font-medium">Schema (JSON)</label>
        <textarea class="w-full text-xs font-mono bg-neutral-900/40 rounded p-3 min-h-[320px]" wire:model.lazy="schema"></textarea>
        <div class="flex justify-end">
          <x-flux::button wire:click="save">Save</x-flux::button>
        </div>
      </x-flux::card.content>
    </x-flux::card>
  </div>
</div>
