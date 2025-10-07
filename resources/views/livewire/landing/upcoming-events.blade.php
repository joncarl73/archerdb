<?php
use App\Models\Event;
use App\Models\League;
use Illuminate\Support\Carbon;
use Livewire\Volt\Component;

new class extends Component
{
    public array $items = [];

    public function mount(): void
    {
        $today = Carbon::today();

        $leagues = League::query()
            ->where('is_published', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('registration_end_date')
                    ->orWhere('registration_end_date', '>=', $today->toDateString());
            })
            ->orderBy('registration_start_date')
            ->limit(8)
            ->get()
            ->map(fn ($L) => [
                'kind' => 'League',
                'title' => $L->info->title ?? $L->title,
                'start' => $L->registration_start_date,
                'end' => $L->registration_end_date,
                'url' => route('public.league.info', $L->public_uuid),
            ]);

        $events = Event::query()
            ->where('is_published', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today->toDateString());
            })
            ->orderBy('starts_on')
            ->limit(8)
            ->get()
            ->map(fn ($E) => [
                'kind' => 'Event',
                'title' => $E->info->title ?? $E->title,
                'start' => $E->starts_on,
                'end' => $E->ends_on,
                'url' => route('public.event.info', $E->public_uuid),
            ]);

        $this->items = collect($leagues)->merge($events)
            ->sortBy(fn ($x) => $x['start'] ?? '9999-12-31')
            ->values()
            ->all();
    }
};
?>

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
  @foreach($items as $it)
    <a href="{{ $it['url'] }}" class="block rounded-xl border border-neutral-200 bg-white p-4 hover:shadow-md dark:border-white/10 dark:bg-neutral-900">
      <div class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ $it['kind'] }}</div>
      <div class="mt-1 text-base font-semibold text-neutral-900 dark:text-neutral-100">
        {{ $it['title'] }}
      </div>
      <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
        @if($it['start']) Starts {{ \Illuminate\Support\Carbon::parse($it['start'])->toDateString() }} @endif
        @if($it['end']) • Ends {{ \Illuminate\Support\Carbon::parse($it['end'])->toDateString() }} @endif
      </div>
    </a>
  @endforeach
</div>
