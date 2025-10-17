<?php
use App\Models\PricingTier;
use Livewire\Volt\Component;

new class extends Component
{
    // If editing, Volt will inject the model via route-model binding on mount
    public ?PricingTier $tier = null;

    // Form state
    public string $name = '';

    public string $currency = 'usd';

    public int $league_fee_cents = 0;

    public int $competition_fee_cents = 0;

    public bool $is_active = true;

    public function mount(?PricingTier $tier = null): void
    {
        $this->tier = $tier;

        if ($tier) {
            // Editing existing
            $this->name = (string) $tier->name;
            $this->currency = strtolower($tier->currency ?? 'usd');
            $this->league_fee_cents = (int) $tier->league_participant_fee_cents;
            $this->competition_fee_cents = (int) $tier->competition_participant_fee_cents;
            $this->is_active = (bool) $tier->is_active;
        } else {
            // Creating new
            $this->currency = 'usd';
            $this->is_active = true;
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'currency' => ['required', 'string', 'size:3'],
            'league_fee_cents' => ['required', 'integer', 'min:0'],
            'competition_fee_cents' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $payload = [
            'name' => trim($this->name),
            'currency' => strtolower($this->currency),
            'league_participant_fee_cents' => (int) $this->league_fee_cents,
            'competition_participant_fee_cents' => (int) $this->competition_fee_cents,
            'is_active' => (bool) $this->is_active,
        ];

        if ($this->tier) {
            $this->tier->update($payload);
            $this->dispatch('toast', type: 'success', message: 'Pricing tier updated.');
        } else {
            $this->tier = PricingTier::create($payload);
            $this->dispatch('toast', type: 'success', message: 'Pricing tier created.');
        }

        // Back to index
        $this->redirectRoute('admin.pricing.tiers.index', navigate: true);
    }

    public function delete(): void
    {
        if (! $this->tier) {
            return;
        }

        // You can add guards here (e.g., prevent delete if in use)
        $this->tier->delete();

        $this->dispatch('toast', type: 'success', message: 'Pricing tier deleted.');
        $this->redirectRoute('admin.pricing.tiers.index', navigate: true);
    }

    public function render(): mixed
    {
        return view('livewire.admin.pricing-tiers.edit');
    }
};
?>

<section class="mx-auto max-w-3xl">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-base font-semibold text-gray-900 dark:text-white">
        {{ $tier ? 'Edit Pricing Tier' : 'New Pricing Tier' }}
      </h1>
      <p class="text-sm text-gray-600 dark:text-gray-300">
        Set per-participant fees and currency.
      </p>
    </div>

    <div class="flex gap-2">
      <flux:button as="a" href="{{ route('admin.pricing.tiers.index') }}" variant="ghost">
        ← Back
      </flux:button>

      @if($tier)
        <flux:button variant="destructive" wire:click="delete">
          Delete
        </flux:button>
      @endif
    </div>
  </div>

  <div class="mt-6 rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
    <div class="p-6 space-y-6">
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <flux:label for="name">Name</flux:label>
          <flux:input id="name" wire:model.defer="name" placeholder="e.g. Corporate Tier A" />
          @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
          <flux:label for="currency">Currency (ISO 4217)</flux:label>
          <flux:input id="currency" wire:model.defer="currency" placeholder="usd" />
          @error('currency') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
          <flux:label for="league_fee_cents">League fee (cents)</flux:label>
          <flux:input id="league_fee_cents" type="number" min="0" step="1" wire:model.defer="league_fee_cents" />
          @error('league_fee_cents') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
          <flux:label for="competition_fee_cents">Competition fee (cents)</flux:label>
          <flux:input id="competition_fee_cents" type="number" min="0" step="1" wire:model.defer="competition_fee_cents" />
          @error('competition_fee_cents') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3">
          <flux:switch id="is_active" wire:model.defer="is_active" />
          <flux:label for="is_active">Active</flux:label>
        </div>
      </div>

      <div class="flex items-center justify-end gap-3">
        <flux:button as="a" href="{{ route('admin.pricing.tiers.index') }}" variant="ghost">Cancel</flux:button>
        <flux:button variant="primary" wire:click="save">Save</flux:button>
      </div>
    </div>
  </div>
</section>
