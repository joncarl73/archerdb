<?php
use App\Models\Event;
use App\Models\Ruleset;
use Livewire\Volt\Component;

new class extends Component
{
    public Event $event;

    public string $search = '';

    public int $perPage = 10;

    public bool $showAssign = false; // right-side drawer

    public function rulesets()
    {
        return Ruleset::query()
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('org', 'like', "%{$this->search}%")))
            ->orderBy('is_system', 'desc')->orderBy('org')->paginate($this->perPage, pageName: 'rulesetsPage');
    }

    public function assign(int $rulesetId)
    {
        $this->event->update(['ruleset_id' => $rulesetId]);
        session()->flash('ok', 'Ruleset assigned.');
        $this->showAssign = false;
    }
};
?>
<div>
  <div class="flex items-center gap-3 mb-4">
    <h2 class="text-xl font-semibold">Ruleset</h2>
    @if($event->ruleset)
      <span class="px-2 py-1 rounded bg-emerald-600/10 text-emerald-600 text-sm">{{$event->ruleset->name}}</span>
    @else
      <span class="px-2 py-1 rounded bg-amber-600/10 text-amber-600 text-sm">None selected</span>
    @endif
    <x-flux::button size="sm" wire:click="$set('showAssign',true)">Choose / Change</x-flux::button>
    <x-flux::button variant="ghost" size="sm" href="{{ route('events.ruleset.overrides',['event'=>$event->id]) }}">Overrides</x-flux::button>
  </div>

  <x-flux::card>
    <x-flux::card.content>
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @if($event->ruleset)
          <x-flux::card class="col-span-1 md:col-span-2">
            <x-flux::card.header title="Effective Rules" subtitle="Base + Event Overrides"/>
            <x-flux::card.content>
              <pre class="text-xs overflow-auto max-h-80">{{ json_encode($event->effectiveRules(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
            </x-flux::card.content>
          </x-flux::card>
        @endif
      </div>
    </x-flux::card.content>
  </x-flux::card>

  <x-flux::slideover wire:model="showAssign" title="Assign ruleset" subtitle="Choose a canned ruleset or create your own">
    <div class="space-y-3">
      <x-flux::input placeholder="Search org/name…" wire:model.live="search" />
      @php $page = $this->rulesets(); @endphp
      <div class="divide-y">
        @foreach($page as $r)
          <div class="py-3 flex items-center justify-between">
            <div>
              <div class="font-medium">{{$r->name}}</div>
              <div class="text-xs text-neutral-500">{{$r->org}} {{ $r->is_system ? '• system' : '' }}</div>
            </div>
            <x-flux::button size="sm" wire:click="assign({{$r->id}})">Use</x-flux::button>
          </div>
        @endforeach
      </div>
      <div class="pt-2">
        {{ $page->links() }}
      </div>
      <div class="pt-4">
        <x-flux::button href="{{ route('events.ruleset.create',['event'=>$event->id]) }}" variant="outline" class="w-full">
          Create Custom Ruleset
        </x-flux::button>
      </div>
    </div>
  </x-flux::slideover>
</div>
