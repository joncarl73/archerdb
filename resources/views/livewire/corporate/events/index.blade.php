<?php

use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    public string $q = '';

    public ?string $kind = null;

    public function with(): array
    {
        Gate::authorize('viewAny', Event::class);

        $events = Event::query()
            ->when($this->q !== '', fn ($q) => $q->where('title', 'like', '%'.$this->q.'%'))
            ->when($this->kind, fn ($q) => $q->where('kind', $this->kind))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return ['events' => $events];
    }
}; ?>


  <div class="mx-auto max-w-6xl space-y-6">
    <div class="flex items-end justify-between gap-3">
      <div>
        <h1 class="text-base font-semibold text-gray-900 dark:text-white">Events</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400">Create and manage tournaments, clinics, socials, and league wrappers.</p>
      </div>
      <flux:button as="a" href="{{ route('corporate.events.create') }}" variant="primary">New event</flux:button>
    </div>

    <div class="flex gap-3">
      <flux:input wire:model.live="q" placeholder="Search title…" class="w-80" />
      <flux:select wire:model.live="kind" class="w-48">
        <option value="">All kinds</option>
        <option value="tournament">Tournament</option>
        <option value="clinic">Clinic</option>
        <option value="social">Social</option>
        <option value="league">League</option>
      </flux:select>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
      <table class="w-full text-left">
        <thead class="bg-white dark:bg-neutral-900">
          <tr>
            <th class="px-4 py-3 text-sm font-semibold">Title</th>
            <th class="px-4 py-3 text-sm font-semibold">Kind</th>
            <th class="px-4 py-3 text-sm font-semibold">Window</th>
            <th class="px-4 py-3 text-sm font-semibold">Published</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
          @forelse($events as $e)
            <tr>
              <td class="px-4 py-3 text-sm">{{ $e->title }}</td>
              <td class="px-4 py-3 text-sm capitalize">{{ $e->kind }}</td>
              <td class="px-4 py-3 text-sm">
                {{ $e->starts_on ?? '—' }} → {{ $e->ends_on ?? '—' }}
              </td>
              <td class="px-4 py-3 text-sm">{{ $e->is_published ? 'Yes' : 'No' }}</td>
              <td class="px-4 py-3 text-sm">
                <div class="flex gap-2">
                  <flux:button as="a" size="sm" variant="primary" href="{{ route('corporate.events.basics', $e) }}">Basics</flux:button>
                  <flux:button as="a" size="sm" href="{{ route('corporate.events.divisions', $e) }}">Divisions</flux:button>
                  <flux:button as="a" size="sm" href="{{ route('corporate.events.line_times', $e) }}">Line times</flux:button>
                  <flux:button as="a" size="sm" href="{{ route('corporate.events.lane_map', $e) }}">Lane map</flux:button>
                  <flux:button as="a" size="sm" variant="ghost" href="{{ route('corporate.events.info.edit', $e) }}">Info page</flux:button>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-4 py-6 text-sm text-gray-500">No events yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

