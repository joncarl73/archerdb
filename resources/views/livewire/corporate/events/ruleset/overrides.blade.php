<?php
use App\Models\Event;
use App\Models\EventRulesetOverride;
use Livewire\Volt\Component;

new class extends Component
{
    public Event $event;

    public array $overrides = [];

    public function mount()
    {
        $this->overrides = $this->event->rulesetOverrides?->overrides ?? [];
    }

    public function save()
    {
        EventRulesetOverride::updateOrCreate(
            ['event_id' => $this->event->id],
            ['overrides' => $this->overrides]
        );
        session()->flash('ok', 'Overrides saved.');

        return redirect()->route('events.ruleset.show', ['event' => $this->event->id]);
    }
};
?>
<div class="space-y-4">
  <x-flux::header title="Ruleset Overrides" subtitle="Only set what you want to change for this event" />
  <x-flux::card>
    <x-flux::card.content class="space-y-2">
      <label class="text-sm font-medium">Overrides (JSON — partial)</label>
      <textarea class="w-full text-xs font-mono bg-neutral-900/40 rounded p-3 min-h-[340px]" wire:model.lazy="overrides"></textarea>
      <div class="flex justify-end">
        <x-flux::button wire:click="save">Save</x-flux::button>
      </div>
    </x-flux::card.content>
  </x-flux::card>
  <x-flux::card>
    <x-flux::card.header title="Preview: Effective Rules"/>
    <x-flux::card.content>
      <pre class="text-xs overflow-auto max-h-80">{{ json_encode($event->effectiveRules(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
    </x-flux::card.content>
  </x-flux::card>
</div>
