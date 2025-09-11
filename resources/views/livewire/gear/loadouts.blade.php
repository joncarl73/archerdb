<?php
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use App\Models\{Loadout, LoadoutItem, Manufacturer};

new class extends Component {
    use WithPagination;

    // pagination
    protected string $pageName = 'loadoutsPage';

    // sorting
    public string $sort = 'updated_at';
    public string $direction = 'desc';

    // sheet form state
    public bool $showSheet = false;
    public ?int $editingId = null;

    // loadout fields
    public string $name = '';
    public bool $is_primary = false;
    public ?string $bow_type = null; // recurve | compound | longbow
    public string $notes = '';

    // Bow fields
    public ?int $bow_manufacturer_id = null;
    public string $bow_model = '';
    public ?int $bow_draw_weight = null; // lbs
    public string $bow_notes = '';

    // Arrow fields
    public ?int $arrow_manufacturer_id = null;
    public string $arrow_model = '';
    public string $arrow_spine = '';
    public ?float $arrow_length = null; // inches

    // Sight
    public ?int $sight_manufacturer_id = null;
    public string $sight_model = '';

    // Scope (typically compound)
    public ?int $scope_manufacturer_id = null;
    public string $scope_model = '';

    // Rest
    public ?int $rest_manufacturer_id = null;
    public string $rest_model = '';

    // Stabilizers
    public ?int $stabilizer_manufacturer_id = null;
    public string $stabilizer_model = '';

    // Plunger (typically recurve)
    public ?int $plunger_manufacturer_id = null;
    public string $plunger_model = '';

    // Release (typically compound)
    public ?int $release_manufacturer_id = null;
    public string $release_model = '';

    // dropdown lists
    public array $bowManufacturers = [];
    public array $arrowManufacturers = [];
    public array $sightManufacturers = [];
    public array $scopeManufacturers = [];
    public array $restManufacturers = [];
    public array $stabilizerManufacturers = [];
    public array $plungerManufacturers = [];
    public array $releaseManufacturers = [];

    // Undo support
    public ?array $lastDeleted = null;

    public function mount(): void
    {
        // preload manufacturer dropdowns by category
        $this->bowManufacturers = Manufacturer::query()
            ->whereJsonContains('categories', 'bow')
            ->orderBy('name')->get(['id','name'])->toArray();

        $this->arrowManufacturers = Manufacturer::query()
            ->whereJsonContains('categories', 'arrow')
            ->orderBy('name')->get(['id','name'])->toArray();

        $this->sightManufacturers = Manufacturer::query()
            ->whereJsonContains('categories', 'sight')
            ->orderBy('name')->get(['id','name'])->toArray();

        $this->scopeManufacturers = Manufacturer::query()
            ->whereJsonContains('categories', 'scope')
            ->orderBy('name')->get(['id','name'])->toArray();

        $this->restManufacturers = Manufacturer::query()
            ->whereJsonContains('categories', 'rest')
            ->orderBy('name')->get(['id','name'])->toArray();

        $this->stabilizerManufacturers = Manufacturer::query()
            ->whereJsonContains('categories', 'stabilizer')
            ->orderBy('name')->get(['id','name'])->toArray();

        $this->plungerManufacturers = Manufacturer::query()
            ->whereJsonContains('categories', 'plunger')
            ->orderBy('name')->get(['id','name'])->toArray();

        $this->releaseManufacturers = Manufacturer::query()
            ->whereJsonContains('categories', 'release')
            ->orderBy('name')->get(['id','name'])->toArray();
    }

    public function updatingSort()     { $this->resetPage($this->pageName); }
    public function updatingDirection(){ $this->resetPage($this->pageName); }

    public function goto(int $page): void { $this->gotoPage($page, $this->pageName); }
    public function prevPage(): void      { $this->previousPage($this->pageName); }
    public function nextPage(): void      { $this->nextPage($this->pageName); }

    // --- Inline actions ---
    public function makePrimary(int $id): void
    {
        $user = Auth::user();
        $loadout = $user->loadouts()->findOrFail($id);
        $user->loadouts()->update(['is_primary' => false]);
        $loadout->update(['is_primary' => true]);
        $this->dispatch('toast', type:'success', message:'Primary updated');
    }

    /** One-click delete with undo toast */
    public function delete(int $id): void
    {
        $user = Auth::user();
        $loadout = $user->loadouts()->findOrFail($id);

        $wasPrimary = (bool) $loadout->is_primary;
        $loadout->delete(); // soft delete

        // promote newest remaining if we deleted primary
        $promotedId = null;
        if ($wasPrimary) {
            $next = $user->loadouts()->latest('updated_at')->first();
            if ($next) {
                $next->update(['is_primary' => true]);
                $promotedId = $next->id;
            }
        }

        $this->lastDeleted = [
            'id'          => $loadout->id,
            'was_primary' => $wasPrimary,
            'promoted_id' => $promotedId,
        ];

        $this->dispatch('toast',
            type: 'success',
            message: 'Loadout deleted',
            duration: 6000,
            action: [
                'label'   => 'Undo',
                'event'   => 'undo-loadout',
                'payload' => ['id' => $loadout->id],
            ],
        );
    }

    #[On('undo-loadout')]
    public function undoDelete(int $id): void
    {
        $user = Auth::user();
        if (!$this->lastDeleted || ($this->lastDeleted['id'] ?? null) !== $id) return;

        $restored = $user->loadouts()->withTrashed()->findOrFail($id);
        if ($restored->trashed()) {
            $restored->restore();
        }

        if (!empty($this->lastDeleted['was_primary'])) {
            $user->loadouts()->update(['is_primary' => false]);
            $restored->update(['is_primary' => true]);
        }

        $this->lastDeleted = null;
        $this->dispatch('toast', type:'success', message:'Undo complete', duration:2500);
    }

    public function getLoadoutsProperty()
    {
        return Auth::user()
            ->loadouts()
            ->withCount('items')
            ->orderBy($this->sort, $this->direction)
            ->paginate(5, pageName: $this->pageName);
    }

    /** Paging window for page buttons */
    public function getPageWindowProperty(): array
    {
        $p = $this->loadouts;
        $window  = 2;
        $current = max(1, (int) $p->currentPage());
        $last    = max(1, (int) $p->lastPage());
        $start   = max(1, $current - $window);
        $end     = min($last, $current + $window);
        return compact('current','last','start','end');
    }

    /** Open empty create form */
    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showSheet = true;
    }

    /** Open edit with data */
    public function openEdit(int $id): void
    {
        $loadout = Auth::user()->loadouts()->with('items')->findOrFail($id);
        $this->editingId = $loadout->id;
        $this->name = $loadout->name;
        $this->is_primary = (bool) $loadout->is_primary;
        $this->bow_type = $loadout->bow_type;
        $this->notes = (string) ($loadout->notes ?? '');

        // Map items -> fields
        $bow        = $loadout->items->firstWhere('category','bow');
        $arrow      = $loadout->items->firstWhere('category','arrow');
        $sight      = $loadout->items->firstWhere('category','sight');
        $scope      = $loadout->items->firstWhere('category','scope');
        $rest       = $loadout->items->firstWhere('category','rest');
        $stabilizer = $loadout->items->firstWhere('category','stabilizer');
        $plunger    = $loadout->items->firstWhere('category','plunger');
        $release    = $loadout->items->firstWhere('category','release');

        // Bow
        $this->bow_manufacturer_id = $bow?->manufacturer_id;
        $this->bow_model           = $bow?->model ?? '';
        $this->bow_draw_weight     = $bow?->specs['draw_weight'] ?? null;
        $this->bow_notes           = $bow?->specs['notes'] ?? '';

        // Arrows
        $this->arrow_manufacturer_id = $arrow?->manufacturer_id;
        $this->arrow_model           = $arrow?->model ?? '';
        $this->arrow_spine           = $arrow?->specs['spine'] ?? '';
        $this->arrow_length          = $arrow?->specs['length'] ?? null;

        // Sight/Scope
        $this->sight_manufacturer_id = $sight?->manufacturer_id;
        $this->sight_model           = $sight?->model ?? '';
        $this->scope_manufacturer_id = $scope?->manufacturer_id;
        $this->scope_model           = $scope?->model ?? '';

        // Rest / Stabilizers / Plunger / Release
        $this->rest_manufacturer_id       = $rest?->manufacturer_id;
        $this->rest_model                 = $rest?->model ?? '';
        $this->stabilizer_manufacturer_id = $stabilizer?->manufacturer_id;
        $this->stabilizer_model           = $stabilizer?->model ?? '';
        $this->plunger_manufacturer_id    = $plunger?->manufacturer_id;
        $this->plunger_model              = $plunger?->model ?? '';
        $this->release_manufacturer_id    = $release?->manufacturer_id;
        $this->release_model              = $release?->model ?? '';

        $this->showSheet = true;
    }

    /** Persist create or update based on $editingId */
    public function save(): void
    {
        $this->validate([
            'name' => ['required','string','max:80'],
            'is_primary' => ['boolean'],
            'bow_type' => ['nullable','in:recurve,compound,longbow'],
            'notes' => ['nullable','string','max:1000'],

            'bow_manufacturer_id' => ['nullable','exists:manufacturers,id'],
            'bow_model' => ['nullable','string','max:120'],
            'bow_draw_weight' => ['nullable','integer','min:10','max:80'],
            'bow_notes' => ['nullable','string','max:400'],

            'arrow_manufacturer_id' => ['nullable','exists:manufacturers,id'],
            'arrow_model' => ['nullable','string','max:120'],
            'arrow_spine' => ['nullable','string','max:40'],
            'arrow_length' => ['nullable','numeric','min:10','max:35'],

            'sight_manufacturer_id' => ['nullable','exists:manufacturers,id'],
            'sight_model' => ['nullable','string','max:120'],

            'scope_manufacturer_id' => ['nullable','exists:manufacturers,id'],
            'scope_model' => ['nullable','string','max:120'],

            'rest_manufacturer_id' => ['nullable','exists:manufacturers,id'],
            'rest_model' => ['nullable','string','max:120'],

            'stabilizer_manufacturer_id' => ['nullable','exists:manufacturers,id'],
            'stabilizer_model' => ['nullable','string','max:120'],

            'plunger_manufacturer_id' => ['nullable','exists:manufacturers,id'],
            'plunger_model' => ['nullable','string','max:120'],

            'release_manufacturer_id' => ['nullable','exists:manufacturers,id'],
            'release_model' => ['nullable','string','max:120'],
        ]);

        // If this is becoming primary, unset others for this user
        if ($this->is_primary) {
            Auth::user()->loadouts()->update(['is_primary' => false]);
        }

        if ($this->editingId) {
            $loadout = Auth::user()->loadouts()->findOrFail($this->editingId);
            $loadout->update([
                'name'       => trim($this->name),
                'is_primary' => (bool) $this->is_primary,
                'bow_type'   => $this->bow_type ?: null,
                'notes'      => $this->notes ?: null,
            ]);
        } else {
            $loadout = Auth::user()->loadouts()->create([
                'name'       => trim($this->name),
                'is_primary' => (bool) $this->is_primary,
                'bow_type'   => $this->bow_type ?: null,
                'notes'      => $this->notes ?: null,
            ]);
        }

        // Upsert Bow
        $this->upsertItem($loadout, 'bow', $this->bow_manufacturer_id, $this->bow_model, array_filter([
            'draw_weight' => $this->bow_draw_weight,
            'notes'       => $this->bow_notes,
        ]), position: 10);

        // Upsert Arrows
        $this->upsertItem($loadout, 'arrow', $this->arrow_manufacturer_id, $this->arrow_model, array_filter([
            'spine'  => $this->arrow_spine,
            'length' => $this->arrow_length,
        ]), position: 20);

        // Sight / Scope / Rest / Stabilizer / Plunger / Release
        $this->upsertItem($loadout, 'sight', $this->sight_manufacturer_id, $this->sight_model, [], position: 30);
        $this->upsertItem($loadout, 'scope', $this->scope_manufacturer_id, $this->scope_model, [], position: 40);
        $this->upsertItem($loadout, 'rest', $this->rest_manufacturer_id, $this->rest_model, [], position: 50);
        $this->upsertItem($loadout, 'stabilizer', $this->stabilizer_manufacturer_id, $this->stabilizer_model, [], position: 60);
        $this->upsertItem($loadout, 'plunger', $this->plunger_manufacturer_id, $this->plunger_model, [], position: 70);
        $this->upsertItem($loadout, 'release', $this->release_manufacturer_id, $this->release_model, [], position: 80);

        $this->showSheet = false;
        $this->resetForm(keepLists: true);
        $this->dispatch('toast', type:'success', message:'Loadout saved');
    }

    protected function upsertItem($loadout, string $category, ?int $manufacturerId, ?string $model, array $specs, int $position): void
    {
        // if nothing was provided for this category & an item exists, leave it as-is
        if (!$manufacturerId && !$model && empty($specs)) {
            return;
        }

        $item = $loadout->items()->firstOrNew(['category' => $category]);
        $item->manufacturer_id = $manufacturerId ?: null;
        $item->model = $model ?: null;
        $item->specs = $specs ?: null;
        $item->position = $item->exists ? $item->position : $position;
        $item->save();
    }

    protected function resetForm(bool $keepLists = false): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->is_primary = false;
        $this->bow_type = null;
        $this->notes = '';

        $this->bow_manufacturer_id = null;
        $this->bow_model = '';
        $this->bow_draw_weight = null;
        $this->bow_notes = '';

        $this->arrow_manufacturer_id = null;
        $this->arrow_model = '';
        $this->arrow_spine = '';
        $this->arrow_length = null;

        $this->sight_manufacturer_id = null;
        $this->sight_model = '';
        $this->scope_manufacturer_id = null;
        $this->scope_model = '';

        $this->rest_manufacturer_id = null;
        $this->rest_model = '';
        $this->stabilizer_manufacturer_id = null;
        $this->stabilizer_model = '';
        $this->plunger_manufacturer_id = null;
        $this->plunger_model = '';
        $this->release_manufacturer_id = null;
        $this->release_model = '';

        if (!$keepLists) {
            $this->mount();
        }
    }
}; ?>

<section class="w-full">
    {{-- Header --}}
    <div class="mx-auto max-w-7xl">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">Loadouts</h1>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    Create and manage your archery equipment setups. Choose a primary loadout for quick selection later.
                </p>
            </div>
            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <button wire:click="openCreate"
                        class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-xs
                               hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600
                               dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                    Add loadout
                </button>
            </div>
        </div>
    </div>

    {{-- Table with outer border --}}
    <div class="mt-8">
        <div class="mx-auto max-w-7xl">
            <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
                <table class="w-full text-left">
                    <thead class="bg-white dark:bg-gray-900">
                        <tr>
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                Name
                            </th>
                            <th scope="col" class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 sm:table-cell dark:text-white">Items</th>
                            <th scope="col" class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 md:table-cell dark:text-white">Updated</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Primary</th>
                            <th scope="col" class="py-3.5 pl-3 pr-4"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse($this->loadouts as $ld)
                            <tr>
                                <td class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $ld->name }}
                                </td>
                                <td class="hidden px-3 py-4 text-sm text-gray-500 sm:table-cell dark:text-gray-400">
                                    {{ $ld->items_count }} item{{ $ld->items_count === 1 ? '' : 's' }}
                                </td>
                                <td class="hidden px-3 py-4 text-sm text-gray-500 md:table-cell dark:text-gray-400">
                                    {{ $ld->updated_at?->format('Y-m-d') }}
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    @if($ld->is_primary)
                                        <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/30">
                                            Primary
                                        </span>
                                    @else
                                        <span class="text-xs opacity-60">—</span>
                                    @endif
                                </td>
                                <td class="py-4 pl-3 pr-4 text-right text-sm font-medium space-x-3">
                                    {{-- Edit --}}
                                    <button wire:click="openEdit({{ $ld->id }})"
                                            class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                        Edit<span class="sr-only">, {{ $ld->name }}</span>
                                    </button>

                                    {{-- Set Primary (only if not already primary) --}}
                                    @unless($ld->is_primary)
                                        <button wire:click="makePrimary({{ $ld->id }})"
                                                class="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100">
                                            Set primary
                                        </button>
                                    @endunless

                                    {{-- Delete (soft, with Undo toast) --}}
                                    <button wire:click="delete({{ $ld->id }})"
                                            class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 px-4 text-sm text-gray-500 dark:text-gray-400">
                                    No loadouts yet. Click “Add loadout” to create your first setup.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- Pagination footer --}}
                @php($p = $this->loadouts)
                @php($w = $this->pageWindow)
                <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 dark:border-white/10 dark:bg-transparent">
                    <!-- Mobile Prev/Next -->
                    <div class="flex flex-1 justify-between sm:hidden">
                        <button wire:click="prevPage" @disabled($p->onFirstPage()) class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg白/10">Previous</button>
                        <button wire:click="nextPage" @disabled(!$p->hasMorePages()) class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg白/10">Next</button>
                    </div>

                    <!-- Desktop pager -->
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
                                        <span aria-current="page" class="relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:focus-visible:outline-indigo-500">{{ $i }}</span>
                                    @else
                                        <button wire:click="goto({{ $i }})" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:text-gray-200 dark:inset-ring-gray-700 dark:hover:bg-white/5">{{ $i }}</button>
                                    @endif
                                @endfor

                                <button wire:click="nextPage" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:inset-ring-gray-700 dark:hover:bg-white/5" @disabled(!$p->hasMorePages())>
                                    <span class="sr-only">Next</span>
                                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5"><path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" /></svg>
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div> {{-- /bordered wrapper --}}
        </div>
    </div>

    {{-- Right "sheet" for create/edit --}}
    @if($showSheet)
        <div class="fixed inset-0 z-40">
            {{-- overlay --}}
            <div class="absolute inset-0 bg-black/40" wire:click="$set('showSheet', false)"></div>

            {{-- panel --}}
            <div class="absolute inset-y-0 right-0 w-full max-w-2xl h-full overflow-y-auto bg-white p-6 shadow-xl dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $editingId ? 'Edit loadout' : 'Create loadout' }}
                    </h2>
                    <button class="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10"
                            wire:click="$set('showSheet', false)">
                        ✕
                    </button>
                </div>

                <form wire:submit.prevent="save" class="mt-6 space-y-6">
                    <div>
                        <flux:label for="name">Loadout name</flux:label>
                        <flux:input id="name" type="text" wire:model="name" placeholder="e.g., Indoor Recurve, Outdoor Compound" />
                        @error('name') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror

                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <div class="flex items-center gap-3">
                                <flux:switch id="is_primary" wire:model="is_primary" />
                                <flux:label for="is_primary">Set as primary</flux:label>
                            </div>

                            <div>
                                <flux:label for="bow_type">Bow type</flux:label>
                                <flux:select id="bow_type" wire:model="bow_type" class="w-full">
                                    <option value="">{{ __('Select…') }}</option>
                                    <option value="recurve">Recurve</option>
                                    <option value="compound">Compound</option>
                                    <option value="longbow">Longbow</option>
                                </flux:select>
                            </div>
                        </div>
                    </div>

                    {{-- Bow --}}
                    <div class="pt-2">
                        <flux:heading size="sm" class="opacity-70">Bow</flux:heading>
                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <div>
                                <flux:label for="bow_manufacturer_id">Manufacturer</flux:label>
                                <flux:select id="bow_manufacturer_id" wire:model="bow_manufacturer_id" class="w-full">
                                    <option value="">{{ __('Select…') }}</option>
                                    @foreach($bowManufacturers as $m)
                                        <option value="{{ $m['id'] }}">{{ $m['name'] }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div>
                                <flux:label for="bow_model">Model</flux:label>
                                <flux:input id="bow_model" type="text" wire:model="bow_model" placeholder="e.g., Formula XD, V3X 33" />
                            </div>
                            <div>
                                <flux:label for="bow_draw_weight">Draw weight (lbs)</flux:label>
                                <flux:input id="bow_draw_weight" type="number" min="10" max="80" wire:model="bow_draw_weight" class="max-w-[10rem]" />
                            </div>
                            <div>
                                <flux:label for="bow_notes">Notes</flux:label>
                                <flux:input id="bow_notes" type="text" wire:model="bow_notes" placeholder="optional" />
                            </div>
                        </div>
                    </div>

                    {{-- Arrows --}}
                    <div class="pt-2">
                        <flux:heading size="sm" class="opacity-70">Arrows</flux:heading>
                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <div>
                                <flux:label for="arrow_manufacturer_id">Manufacturer</flux:label>
                                <flux:select id="arrow_manufacturer_id" wire:model="arrow_manufacturer_id" class="w-full">
                                    <option value="">{{ __('Select…') }}</option>
                                    @foreach($arrowManufacturers as $m)
                                        <option value="{{ $m['id'] }}">{{ $m['name'] }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div>
                                <flux:label for="arrow_model">Model</flux:label>
                                <flux:input id="arrow_model" type="text" wire:model="arrow_model" placeholder="e.g., X10, Pierce Tour" />
                            </div>
                            <div>
                                <flux:label for="arrow_spine">Spine</flux:label>
                                <flux:input id="arrow_spine" type="text" wire:model="arrow_spine" class="max-w-[10rem]" placeholder="e.g., 500" />
                            </div>
                            <div>
                                <flux:label for="arrow_length">Length (in)</flux:label>
                                <flux:input id="arrow_length" type="number" step="0.25" min="10" max="35" wire:model="arrow_length" class="max-w-[10rem]" />
                            </div>
                        </div>
                    </div>

                    {{-- Sight --}}
                    <div class="pt-4">
                        <flux:heading size="sm" class="opacity-70">Sight</flux:heading>
                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <div>
                                <flux:label for="sight_manufacturer_id">Manufacturer</flux:label>
                                <flux:select id="sight_manufacturer_id" wire:model="sight_manufacturer_id" class="w-full">
                                    <option value="">{{ __('Select…') }}</option>
                                    @foreach($sightManufacturers as $m)
                                        <option value="{{ $m['id'] }}">{{ $m['name'] }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div>
                                <flux:label for="sight_model">Model</flux:label>
                                <flux:input id="sight_model" type="text" wire:model="sight_model" placeholder="e.g., Axcel Achieve, Shibuya Ultima" />
                            </div>
                        </div>
                    </div>

                    {{-- Scope (compound only visually) --}}
                    <div class="pt-4" x-data x-show="$wire.bow_type === 'compound'">
                        <flux:heading size="sm" class="opacity-70">Scope</flux:heading>
                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <div>
                                <flux:label for="scope_manufacturer_id">Manufacturer</flux:label>
                                <flux:select id="scope_manufacturer_id" wire:model="scope_manufacturer_id" class="w-full">
                                    <option value="">{{ __('Select…') }}</option>
                                    @foreach($scopeManufacturers as $m)
                                        <option value="{{ $m['id'] }}">{{ $m['name'] }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div>
                                <flux:label for="scope_model">Model</flux:label>
                                <flux:input id="scope_model" type="text" wire:model="scope_model" placeholder="e.g., Shrewd Optum, Axcel AVX" />
                            </div>
                        </div>
                    </div>

                    {{-- Rest --}}
                    <div class="pt-4">
                        <flux:heading size="sm" class="opacity-70">Rest</flux:heading>
                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <div>
                                <flux:label for="rest_manufacturer_id">Manufacturer</flux:label>
                                <flux:select id="rest_manufacturer_id" wire:model="rest_manufacturer_id" class="w-full">
                                    <option value="">{{ __('Select…') }}</option>
                                    @foreach($restManufacturers as $m)
                                        <option value="{{ $m['id'] }}">{{ $m['name'] }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div>
                                <flux:label for="rest_model">Model</flux:label>
                                <flux:input id="rest_model" type="text" wire:model="rest_model" placeholder="e.g., Hamskea Trinity, Spigarelli ZT" />
                            </div>
                        </div>
                    </div>

                    {{-- Stabilizers --}}
                    <div class="pt-4">
                        <flux:heading size="sm" class="opacity-70">Stabilizers</flux:heading>
                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <div>
                                <flux:label for="stabilizer_manufacturer_id">Manufacturer</flux:label>
                                <flux:select id="stabilizer_manufacturer_id" wire:model="stabilizer_manufacturer_id" class="w-full">
                                    <option value="">{{ __('Select…') }}</option>
                                    @foreach($stabilizerManufacturers as $m)
                                        <option value="{{ $m['id'] }}">{{ $m['name'] }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div>
                                <flux:label for="stabilizer_model">Model</flux:label>
                                <flux:input id="stabilizer_model" type="text" wire:model="stabilizer_model" placeholder="e.g., Doinker Platinum, Bee Stinger Competitor" />
                            </div>
                        </div>
                    </div>

                    {{-- Plunger (recurve mostly) --}}
                    <div class="pt-4" x-data x-show="$wire.bow_type === 'recurve'">
                        <flux:heading size="sm" class="opacity-70">Plunger</flux:heading>
                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <div>
                                <flux:label for="plunger_manufacturer_id">Manufacturer</flux:label>
                                <flux:select id="plunger_manufacturer_id" wire:model="plunger_manufacturer_id" class="w-full">
                                    <option value="">{{ __('Select…') }}</option>
                                    @foreach($plungerManufacturers as $m)
                                        <option value="{{ $m['id'] }}">{{ $m['name'] }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div>
                                <flux:label for="plunger_model">Model</flux:label>
                                <flux:input id="plunger_model" type="text" wire:model="plunger_model" placeholder="e.g., Beiter, Shibuya DX" />
                            </div>
                        </div>
                    </div>

                    {{-- Release (compound) --}}
                    <div class="pt-4" x-data x-show="$wire.bow_type === 'compound'">
                        <flux:heading size="sm" class="opacity-70">Release</flux:heading>
                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <div>
                                <flux:label for="release_manufacturer_id">Manufacturer</flux:label>
                                <flux:select id="release_manufacturer_id" wire:model="release_manufacturer_id" class="w-full">
                                    <option value="">{{ __('Select…') }}</option>
                                    @foreach($releaseManufacturers as $m)
                                        <option value="{{ $m['id'] }}">{{ $m['name'] }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div>
                                <flux:label for="release_model">Model</flux:label>
                                <flux:input id="release_model" type="text" wire:model="release_model" placeholder="e.g., Carter Honey, Stan Onnex" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <flux:label for="notes">Loadout notes</flux:label>
                        <flux:input id="notes" type="text" wire:model="notes" placeholder="string material, nock type, etc." />
                    </div>

                    <div class="flex justify-end gap-3">
                        <flux:button type="button" variant="ghost" wire:click="$set('showSheet', false)">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create' }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</section>
