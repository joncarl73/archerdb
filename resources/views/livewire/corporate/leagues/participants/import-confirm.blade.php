<?php
use App\Models\League;
use App\Models\ParticipantImport;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    public League $league;

    public ParticipantImport $import;

    public function mount(League $league, ParticipantImport $import): void
    {
        Gate::authorize('update', $league);
        abort_if($import->league_id !== $league->id, 404);
    }

    public function render(): mixed
    {
        return view('livewire.corporate.leagues.participants.import-confirm');
    }
};
?>
<div class="space-y-6">
  {{-- Title (same font size as the rest per your request) --}}
  <h1 class="text-base font-semibold">Confirm Participant Import</h1>

  {{-- Details block: square corners + gradient background --}}
  <div class="border border-gray-200 bg-gradient-to-br from-purple-50 via-indigo-50 to-blue-50 p-4 dark:from-purple-900/20 dark:via-indigo-900/20 dark:to-blue-900/20">
    <dl class="grid grid-cols-2 gap-4 text-base">
      <dt class="text-neutral-600 dark:text-neutral-300">League</dt>
      <dd class="font-medium">{{ $league->title }}</dd>

      <dt class="text-neutral-600 dark:text-neutral-300">Participants</dt>
      <dd class="font-medium">{{ $import->row_count }}</dd>

      <dt class="text-neutral-600 dark:text-neutral-300">Price per participant</dt>
      <dd class="font-medium">$2.00</dd>

      <dt class="text-neutral-600 dark:text-neutral-300">Total</dt>
      <dd class="font-medium">${{ number_format($import->amount_cents / 100, 2) }}</dd>

      <dt class="text-neutral-600 dark:text-neutral-300">Status</dt>
      <dd class="font-medium capitalize">{{ str_replace('_',' ', $import->status) }}</dd>
    </dl>
  </div>

  {{-- Purple Flux button --}}
  <form method="POST" action="{{ route('corporate.leagues.participants.import.startCheckout', ['league'=>$league->id, 'import'=>$import->id]) }}">
    @csrf
    <flux:button type="submit" variant="primary" color="indigo" icon="credit-card">
      Pay &amp; Continue
    </flux:button>
  </form>

  <p class="text-base text-neutral-600 dark:text-neutral-300">
    You’ll be redirected to Stripe Checkout. Once payment succeeds, we’ll automatically add these participants to your league.
    If you cancel or the payment fails, nothing will be imported and you can retry later.
  </p>
</div>
