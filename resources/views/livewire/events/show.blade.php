<?php
use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    public Event $event;

    public function mount(Event $event)
    {
        Gate::authorize('view', $event);
        $this->event = $event;
    }
}; ?>

<div class="mx-auto max-w-5xl">
  <div class="flex items-center justify-between">
    <flux:text as="h1" class="text-2xl font-semibold">{{ $event->title }}</flux:text>
    <a href="{{ route('corporate.events.basics', $event) }}"><flux:button appearance="secondary" icon="cog">Manage</flux:button></a>
  </div>

  <div class="mt-6 grid gap-4 md:grid-cols-3">
    <div class="rounded-xl border border-gray-200 p-4 dark:border-zinc-700">
      <flux:text as="p" class="text-sm opacity-70">Dates</flux:text>
      <div class="mt-1">
        {{ $event->starts_on->toFormattedDateString() }}
        @unless($event->ends_on->isSameDay($event->starts_on))
          – {{ $event->ends_on->toFormattedDateString() }}
        @endunless
      </div>
    </div>

    <div class="rounded-xl border border-gray-200 p-4 dark:border-zinc-700">
      <flux:text as="p" class="text-sm opacity-70">Kind</flux:text>
      <div class="mt-1">{{ $event->kind->value }}</div>
    </div>

    <div class="rounded-xl border border-gray-200 p-4 dark:border-zinc-700">
      <flux:text as="p" class="text-sm opacity-70">Location</flux:text>
      <div class="mt-1">{{ $event->location ?: '—' }}</div>
    </div>
  </div>
</div>
