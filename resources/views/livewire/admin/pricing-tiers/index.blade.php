<?php
use App\Models\PricingTier;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // pagination
    protected string $pageName = 'pricingTiersPage';

    public int $perPage = 10;

    // table filters/search (simple name search to match admin look)
    public string $search = '';

    // sheet state
    public bool $showSheet = false;

    public ?int $editingId = null;

    // form fields
    public string $name = '';

    public string $currency = 'usd';

    public int $league_fee_cents = 0;

    public int $competition_fee_cents = 0;

    public bool $is_active = true;

    // keep pager in sync when filters change
    public function updatingSearch()
    {
        $this->resetPage($this->pageName);
    }

    // pager helpers
    public function goto(int $page): void
    {
        $this->gotoPage($page, $this->pageName);
    }

    public function prevPage(): void
    {
        $this->previousPage($this->pageName);
    }

    public function nextPage(): void
    {
        $this->nextPage($this->pageName);
    }

    /** Computed: tiers list (matches admin table styling used elsewhere) */
    public function getTiersProperty()
    {
        $q = PricingTier::query()
            ->when($this->search, fn ($qq) => $qq->where('name', 'like', "%{$this->search}%")
            )
            ->orderBy('name');

        $total = (clone $q)->count();
        $lastPage = max(1, (int) ceil($total / $this->perPage));
        $requested = (int) ($this->paginators[$this->pageName] ?? 1);
        $page = min(max(1, $requested), $lastPage);
        if ($requested !== $page) {
            $this->setPage($page, $this->pageName);
        }

        return $q->paginate($this->perPage, ['*'], $this->pageName, $page);
    }

    /** Paging window for buttons (same pattern as users/leagues) */
    public function getPageWindowProperty(): array
    {
        $p = $this->tiers;
        $w = 2;
        $current = max(1, (int) $p->currentPage());
        $last = max(1, (int) $p->lastPage());
        $start = max(1, $current - $w);
        $end = min($last, $current + $w);

        return compact('current', 'last', 'start', 'end');
    }

    /** Open create sheet */
    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showSheet = true;
    }

    /** Open edit sheet */
    public function openEdit(int $id): void
    {
        $t = PricingTier::findOrFail($id);

        $this->editingId = $t->id;
        $this->name = (string) $t->name;
        $this->currency = strtolower($t->currency ?? 'usd');
        $this->league_fee_cents = (int) $t->league_participant_fee_cents;
        $this->competition_fee_cents = (int) $t->competition_participant_fee_cents;
        $this->is_active = (bool) $t->is_active;

        $this->showSheet = true;
    }

    /** Toggle active from table */
    public function toggleActive(int $id): void
    {
        $t = PricingTier::findOrFail($id);
        $t->is_active = ! $t->is_active;
        $t->save();

        $this->dispatch('toast', type: 'success', message: $t->is_active ? 'Tier activated' : 'Tier deactivated');
    }

    /** Persist form */
    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'currency' => ['required', 'string', 'size:3'],
            'league_fee_cents' => ['required', 'integer', 'min:0'],
            'competition_fee_cents' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $payload = [
            'name' => trim($this->name),
            'currency' => strtolower($this->currency),
            'league_participant_fee_cents' => (int) $this->league_fee_cents,
            'competition_participant_fee_cents' => (int) $this->competition_fee_cents,
            'is_active' => (bool) $this->is_active,
        ];

        if ($this->editingId) {
            PricingTier::whereKey($this->editingId)->update($payload);
            $this->dispatch('toast', type: 'success', message: 'Pricing tier updated');
        } else {
            PricingTier::create($payload);
            $this->dispatch('toast', type: 'success', message: 'Pricing tier created');
        }

        $this->showSheet = false;
        $this->resetForm();
        // refresh current page data
        $this->tiers;
    }

    /** Delete from sheet */
    public function delete(): void
    {
        if (! $this->editingId) {
            return;
        }

        PricingTier::whereKey($this->editingId)->delete();
        $this->dispatch('toast', type: 'success', message: 'Pricing tier deleted');

        $this->showSheet = false;
        $this->resetForm();
        $this->tiers;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->currency = 'usd';
        $this->league_fee_cents = 0;
        $this->competition_fee_cents = 0;
        $this->is_active = true;
    }
};
?>

<section class="mx-auto max-w-7xl">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold text-gray-900 dark:text-white">Pricing Tiers</h1>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                Manage platform per-participant fees for leagues and competitions.
            </p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <button
                wire:click="openCreate"
                class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-xs
                       hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600
                       dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                New tier
            </button>
        </div>
    </div>

    <div class="mt-4 max-w-md">
        <flux:input icon="magnifying-glass" placeholder="Search by tier name…" wire:model.live.debounce.300ms="search" />
    </div>

    <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
        <table class="w-full text-left">
            <thead class="bg-white dark:bg-gray-900">
                <tr>
                    <th class="py-3.5 pl-4 pr-3 text-sm font-semibold text-gray-900 dark:text-white">Name</th>
                    <th class="px-3 py-3.5 text-sm font-semibold text-gray-900 dark:text-white">League fee</th>
                    <th class="hidden md:table-cell px-3 py-3.5 text-sm font-semibold text-gray-900 dark:text-white">Competition fee</th>
                    <th class="hidden sm:table-cell px-3 py-3.5 text-sm font-semibold text-gray-900 dark:text-white">Currency</th>
                    <th class="px-3 py-3.5 text-sm font-semibold text-gray-900 dark:text-white">Active</th>
                    <th class="py-3.5 pl-3 pr-4"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @forelse($this->tiers as $t)
                    <tr>
                        <td class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white">
                            {{ $t->name }}
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-700 dark:text-gray-300">
                            ${{ number_format(($t->league_participant_fee_cents ?? 0)/100, 2) }}
                        </td>
                        <td class="hidden md:table-cell px-3 py-4 text-sm text-gray-700 dark:text-gray-300">
                            ${{ number_format(($t->competition_participant_fee_cents ?? 0)/100, 2) }}
                        </td>
                        <td class="hidden sm:table-cell px-3 py-4 text-sm text-gray-700 dark:text-gray-300 uppercase">
                            {{ $t->currency }}
                        </td>
                        <td class="px-3 py-4 text-sm">
                            <flux:switch :checked="$t->is_active" wire:click="toggleActive({{ $t->id }})" />
                        </td>
                        <td class="py-4 pl-3 pr-4 text-right text-sm font-medium">
                            <div class="inline-flex items-center gap-1.5">
                                <flux:button
                                    variant="ghost"
                                    size="xs"
                                    icon="pencil-square"
                                    title="Edit"
                                    wire:click="openEdit({{ $t->id }})">
                                    <span class="sr-only">Edit {{ $t->name }}</span>
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-8 px-4 text-sm text-gray-500 dark:text-gray-400">
                            No tiers yet. Click “New tier” to create your first one.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pagination footer (same UX as Leagues/Users) --}}
        @php($p = $this->tiers)
        @php($w = $this->pageWindow)
        <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 dark:border-white/10 dark:bg-transparent">
            <div class="flex flex-1 justify-between sm:hidden">
                <button wire:click="prevPage" @disabled($p->onFirstPage()) class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">Previous</button>
                <button wire:click="nextPage" @disabled(!$p->hasMorePages()) class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">Next</button>
            </div>

            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        Showing <span class="font-medium">{{ $p->firstItem() ?? 0 }}</span>
                        to <span class="font-medium">{{ $p->lastItem() ?? 0 }}</span>
                        of <span class="font-medium">{{ $p->total() }}</span> results
                    </p>
                </div>
                <div>
                    <nav aria-label="Pagination" class="isolate inline-flex -space-x-px rounded-md shadow-xs dark:shadow-none">
                        <button wire:click="prevPage" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:inset-ring-gray-700 dark:hover:bg-white/5" @disabled($p->onFirstPage())>
                            <span class="sr-only">Previous</span>
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5"><path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" fill-rule="evenodd" /></svg>
                        </button>
                        @for ($i = $w['start']; $i <= $w['end']; $i++)
                            @if ($i === $w['current'])
                                <span aria-current="page" class="relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 dark:bg-indigo-500">{{ $i }}</span>
                            @else
                                <button wire:click="goto({{ $i }})" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 dark:text-gray-200 dark:inset-ring-gray-700 dark:hover:bg-white/5">{{ $i }}</button>
                            @endif
                        @endfor
                        <button wire:click="nextPage" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 dark:inset-ring-gray-700 dark:hover:bg-white/5" @disabled(!$p->hasMorePages())>
                            <span class="sr-only">Next</span>
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5"><path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" /></svg>
                        </button>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    {{-- Right sheet (create/edit) --}}
    @if($showSheet)
        <div class="fixed inset-0 z-40">
            <div class="absolute inset-0 bg-black/40" wire:click="$set('showSheet', false)"></div>

            <div class="absolute inset-y-0 right-0 w-full max-w-2xl h-full overflow-y-auto bg-white p-6 shadow-xl dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $editingId ? 'Edit tier' : 'Create tier' }}
                    </h2>
                    <button class="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10"
                            wire:click="$set('showSheet', false)">✕</button>
                </div>

                <form wire:submit.prevent="save" class="mt-6 space-y-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <flux:label for="name">Tier name</flux:label>
                            <flux:input id="name" type="text" wire:model="name" placeholder="e.g. Corporate A" />
                            @error('name') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                        </div>

                        <div>
                            <flux:label for="currency">Currency</flux:label>
                            <flux:select id="currency" wire:model="currency" class="w-full">
                                <option value="usd">USD</option>
                                <option value="cad">CAD</option>
                                <option value="eur">EUR</option>
                                <option value="gbp">GBP</option>
                            </flux:select>
                            @error('currency') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                        </div>

                        <div>
                            <flux:label for="league_fee_cents">League fee (cents)</flux:label>
                            <flux:input id="league_fee_cents" type="number" min="0" step="1" wire:model="league_fee_cents" />
                            @error('league_fee_cents') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                        </div>

                        <div>
                            <flux:label for="competition_fee_cents">Competition fee (cents)</flux:label>
                            <flux:input id="competition_fee_cents" type="number" min="0" step="1" wire:model="competition_fee_cents" />
                            @error('competition_fee_cents') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                        </div>

                        <div class="flex items-center gap-3">
                            <flux:switch id="is_active" wire:model="is_active" />
                            <flux:label for="is_active">Active</flux:label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        @if($editingId)
                            <flux:button type="button" variant="destructive" wire:click="delete">Delete</flux:button>
                        @endif
                        <flux:button type="button" variant="ghost" wire:click="$set('showSheet', false)">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create' }}</flux:button>
                    </div>

                    <p class="text-xs opacity-60 pt-2">
                        Fees apply per participant when uploading to a league or (future) competition. Currency is ISO 4217.
                    </p>
                </form>
            </div>
        </div>
    @endif
</section>
