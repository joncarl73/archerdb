<?php
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\{Loadout, LoadoutItem, Manufacturer};
use Illuminate\Auth\Access\AuthorizationException;

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

    // dropdown lists
    public array $bowManufacturers = [];
    public array $arrowManufacturers = [];


    public function mount(): void
    {
        // preload manufacturer dropdowns
        $this->bowManufacturers = Manufacturer::query()
            ->whereJsonContains('categories', 'bow')
            ->orderBy('name')->get(['id','name'])->toArray();

        $this->arrowManufacturers = Manufacturer::query()
            ->whereJsonContains('categories', 'arrow')
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

        // unset others, set this one
        $user->loadouts()->update(['is_primary' => false]);
        $loadout->update(['is_primary' => true]);
    }

    public function delete(int $id): void
    {
        $user = Auth::user();
        $loadout = $user->loadouts()->findOrFail($id); // scoped to owner

        $wasPrimary = (bool) $loadout->is_primary;
        $loadout->delete(); // soft delete

        if ($wasPrimary) {
            $next = $user->loadouts()->latest('updated_at')->first();
            if ($next) $next->update(['is_primary' => true]);
        }

        $this->dispatch('toast', type: 'success', message: 'Loadout deleted');
        // Optionally: $this->resetPage($this->pageName); // if you want to jump back a page when list empties
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
        $this->notes = (string) ($loadout->notes ?? '');

        // Map items -> fields
        $bow   = $loadout->items->firstWhere('category','bow');
        $arrow = $loadout->items->firstWhere('category','arrow');

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

        $this->showSheet = true;
    }

    /** Persist create or update based on $editingId */
    public function save(): void
    {
        $this->validate([
            'name' => ['required','string','max:80'],
            'is_primary' => ['boolean'],
            'notes' => ['nullable','string','max:1000'],

            'bow_manufacturer_id' => ['nullable','exists:manufacturers,id'],
            'bow_model' => ['nullable','string','max:120'],
            'bow_draw_weight' => ['nullable','integer','min:10','max:80'],
            'bow_notes' => ['nullable','string','max:400'],

            'arrow_manufacturer_id' => ['nullable','exists:manufacturers,id'],
            'arrow_model' => ['nullable','string','max:120'],
            'arrow_spine' => ['nullable','string','max:40'],
            'arrow_length' => ['nullable','numeric','min:10','max:35'],
        ]);

        // If this is becoming primary, unset others for this user
        if ($this->is_primary) {
            Auth::user()->loadouts()->update(['is_primary' => false]);
        }

        if ($this->editingId) {
            $loadout = Auth::user()->loadouts()->findOrFail($this->editingId);
            $loadout->update([
                'name' => trim($this->name),
                'is_primary' => (bool) $this->is_primary,
                'notes' => $this->notes ?: null,
            ]);
        } else {
            $loadout = Auth::user()->loadouts()->create([
                'name' => trim($this->name),
                'is_primary' => (bool) $this->is_primary,
                'notes' => $this->notes ?: null,
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

        $this->showSheet = false;
        $this->resetForm(keepLists: true);
        $this->dispatch('loadout-saved');
        // refresh table by bumping pagination (keeps on page)
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
        $this->notes = '';

        $this->bow_manufacturer_id = null;
        $this->bow_model = '';
        $this->bow_draw_weight = null;
        $this->bow_notes = '';

        $this->arrow_manufacturer_id = null;
        $this->arrow_model = '';
        $this->arrow_spine = '';
        $this->arrow_length = null;

        if (!$keepLists) {
            // keep dropdowns from mount
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

                                    {{-- Delete (soft) --}}
                                    <button
                                        wire:click="delete({{ $ld->id }})"
                                        class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                    >
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

                {{-- Pagination footer that visually connects to the table --}}
                @php($p = $this->loadouts)
                @php($w = $this->pageWindow)
                <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 dark:border-white/10 dark:bg-transparent">
                    <!-- Mobile Prev/Next -->
                    <div class="flex flex-1 justify-between sm:hidden">
                        <button wire:click="prevPage" @disabled($p->onFirstPage()) class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">Previous</button>
                        <button wire:click="nextPage" @disabled(!$p->hasMorePages()) class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10">Next</button>
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
            <div class="absolute inset-y-0 right-0 w-full max-w-2xl bg-white p-6 shadow-xl dark:bg-zinc-900">
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

                        <div class="mt-3 flex items-center gap-3">
                            <flux:switch id="is_primary" wire:model="is_primary" />
                            <flux:label for="is_primary">Set as primary</flux:label>
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

    {{-- Delete confirm modal --}}
    <div
        x-data="{ open: false }"
        x-effect="open = $wire.entangle('showDeleteModal').defer"
        @keydown.escape.window="open=false; $wire.cancelDelete()"
    >
        <!-- Backdrop -->
        <div
            x-cloak
            x-show="open"
            x-transition.opacity
            class="fixed inset-0 z-40 bg-black/40"
            @click="$wire.cancelDelete()"
        ></div>

        <!-- Wrapper (only present/interactive when open) -->
        <div
            x-cloak
            x-show="open"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
        >
            <!-- Centered panel -->
            <div
                x-show="open"
                x-transition
                x-trap="open"
                @click.outside="open=false; $wire.cancelDelete()"
                class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-900"
            >
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Delete loadout?</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    This will remove the loadout from your list. You can’t use it in sessions or competitions once deleted.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" @click="$wire.cancelDelete()">Cancel</flux:button>
                    <flux:button type="button" variant="danger" wire:click="deleteConfirmed">Delete</flux:button>
                </div>
            </div>
        </div>
    </div>


</section>
