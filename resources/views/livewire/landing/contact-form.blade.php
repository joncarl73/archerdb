@if($sent)
  <div class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800 ring-1 ring-inset ring-emerald-200
              dark:bg-emerald-900/20 dark:text-emerald-200 dark:ring-emerald-900/40">
    Thanks! Your message has been sent.
  </div>
@endif

@if($sendError)
  <div class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-800 ring-1 ring-inset ring-rose-200
              dark:bg-rose-900/20 dark:text-rose-200 dark:ring-rose-900/40">
    {{ $sendError }}
  </div>
@endif

<form wire:submit.prevent="send" class="space-y-4" autocomplete="off">
  {{-- honeypot --}}
  <div class="sr-only" aria-hidden="true">
    <label>Website</label>
    <input type="text" wire:model.lazy="website" tabindex="-1" autocomplete="off" />
  </div>

  <div>
    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200">Name</label>
    <input type="text" wire:model.defer="name" autocomplete="name"
           class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 shadow-sm
                  focus:border-primary-500 focus:ring-primary-500 sm:text-sm
                  dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100">
    @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200">Email</label>
    <input type="email" wire:model.defer="email" autocomplete="email"
           class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 shadow-sm
                  focus:border-primary-500 focus:ring-primary-500 sm:text-sm
                  dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100">
    @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
  </div>

  <div>
    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-200">Message</label>
    <textarea rows="5" wire:model.defer="message"
              class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 shadow-sm
                     focus:border-primary-500 focus:ring-primary-500 sm:text-sm
                     dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"></textarea>
    @error('message') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
  </div>

  <x-flux::button type="submit" variant="primary" size="sm" wire:loading.attr="disabled">
    <span wire:loading.remove>Send message</span>
    <span wire:loading>Sending…</span>
  </x-flux::button>
</form>
