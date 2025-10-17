<?php
use App\Models\Company;
use App\Models\PricingTier;
use Livewire\Volt\Component;

new class extends Component
{
    public $companies;

    public $tiers;

    public function mount(): void
    {
        // Match your schema: order by company_name, eager load tier
        $this->tiers = PricingTier::orderBy('name')->get();
        $this->companies = Company::with('pricingTier')
            ->orderBy('company_name')
            ->get();
    }

    public function setTier(int $companyId, $tierId): void
    {
        if (! $c = Company::find($companyId)) {
            return;
        }
        $c->pricing_tier_id = $tierId ?: null;
        $c->save();

        // Refresh lists to reflect updates in UI
        $this->mount();

        $this->dispatch('toast', type: 'success', message: 'Pricing tier applied.');
    }
};
?>

<section class="mx-auto max-w-7xl">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold text-gray-900 dark:text-white">Company Pricing</h1>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                Assign pricing tiers to companies. New leagues and competitions created by a company will inherit its assigned tier.
            </p>
        </div>
    </div>

    <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
        <table class="w-full text-left">
            <thead class="bg-white dark:bg-gray-900">
                <tr>
                    <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Company</th>
                    <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Tier</th>
                    <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Current fee (league)</th>
                    <th class="py-3.5 pl-3 pr-4"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @forelse($companies as $c)
                    @php
                        $effectiveFeeCents = optional($c->pricingTier)->league_participant_fee_cents
                            ?? (int) config('pricing.defaults.league_participant_fee_cents', 200);
                        $currency = optional($c->pricingTier)->currency
                            ?? (string) config('pricing.defaults.currency', 'usd');
                    @endphp
                    <tr>
                        <td class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white">
                            {{ $c->company_name }}
                            @if($c->pricingTier)
                                <div class="mt-1">
                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-300 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                                        Assigned: {{ $c->pricingTier->name }}
                                    </span>
                                </div>
                            @else
                                <div class="mt-1">
                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-700/10 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30">
                                        Using defaults
                                    </span>
                                </div>
                            @endif
                        </td>

                        <td class="px-3 py-4 text-sm">
                            <flux:select class="min-w-56"
                                         wire:change="setTier({{ $c->id }}, $event.target.value)">
                                <option value="">— None (use defaults) —</option>
                                @foreach($this->tiers as $t)
                                    <option value="{{ $t->id }}" @selected($c->pricing_tier_id === $t->id)>{{ $t->name }}</option>
                                @endforeach
                            </flux:select>
                        </td>

                        <td class="px-3 py-4 text-sm text-gray-600 dark:text-gray-300">
                            ${{ number_format($effectiveFeeCents / 100, 2) }}
                            <span class="uppercase">{{ $currency }}</span>
                        </td>

                        <td class="py-4 pl-3 pr-4 text-right text-sm font-medium">
                            <span class="text-xs opacity-60">—</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-8 px-4 text-sm text-gray-500 dark:text-gray-400">
                            No companies found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
