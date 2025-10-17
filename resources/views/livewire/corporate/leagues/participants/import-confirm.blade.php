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

@php
    // Currency formatting helpers
    $currency   = strtoupper((string) ($import->currency ?? 'USD'));
    $prefix     = $currency === 'USD' ? '$' : '';
    $suffix     = $currency === 'USD' ? ''  : ' ' . $currency;

    // Prefer staged unit; if missing, derive from total/qty
    $rowCount   = (int) ($import->row_count ?? 0);
    $stagedUnit = (int) ($import->unit_price_cents ?? 0);
    $totalCents = (int) ($import->amount_cents ?? 0);

    if ($stagedUnit <= 0 && $rowCount > 0 && $totalCents > 0) {
        // derive unit (integer cents) safely
        $stagedUnit = (int) floor($totalCents / max(1, $rowCount));
    }

    $unitCents  = max(0, $stagedUnit);
    $unitDisp   = $prefix . number_format($unitCents / 100, 2) . $suffix;
    $totalDisp  = $prefix . number_format($totalCents / 100, 2) . $suffix;

    $payable    = ($import->status === 'pending_payment');
@endphp

<div class="space-y-6">
  {{-- Title kept at uniform size per your spec --}}
  <h1 class="text-base font-semibold">Confirm Participant Import</h1>

  {{-- Details block: square corners + gentle gradient --}}
  <div class="border border-gray-200 bg-gradient-to-br from-purple-50 via-indigo-50 to-blue-50 p-4 dark:from-purple-900/20 dark:via-indigo-900/20 dark:to-blue-900/20">
    <dl class="grid grid-cols-2 gap-4 text-base">
      <dt class="text-neutral-600 dark:text-neutral-300">League</dt>
      <dd class="font-medium">{{ $league->title }}</dd>

      <dt class="text-neutral-600 dark:text-neutral-300">Participants</dt>
      <dd class="font-medium">{{ $rowCount }}</dd>

      <dt class="text-neutral-600 dark:text-neutral-300">Price per participant</dt>
      <dd class="font-medium">{{ $unitDisp }}</dd>

      <dt class="text-neutral-600 dark:text-neutral-300">Total</dt>
      <dd class="font-medium">{{ $totalDisp }}</dd>

      <dt class="text-neutral-600 dark:text-neutral-300">Status</dt>
      <dd class="font-medium capitalize">{{ str_replace('_',' ', (string) $import->status) }}</dd>
    </dl>
  </div>

  {{-- Purple Flux button; disabled unless status is pending_payment --}}
  <form method="POST" action="{{ route('corporate.leagues.participants.import.startCheckout', ['league'=>$league->id, 'import'=>$import->id]) }}">
    @csrf
  <flux:button
      type="submit"
      variant="primary"
      color="indigo"
      icon="credit-card"
      :disabled="!$payable"
  >
    Pay &amp; Continue
  </flux:button>

  </form>

  <p class="text-base text-neutral-600 dark:text-neutral-300">
    You’ll be redirected to Stripe Checkout. Once payment succeeds, we’ll automatically add these participants to your league.
    If you cancel or the payment fails, nothing will be imported and you can retry later.
  </p>
</div>
