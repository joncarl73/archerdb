<?php
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Manufacturer;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public bool $showSheet = false;
    public ?int $editingId = null;

    public string $name = '';
    public array $categories = []; // checkbox list

    protected string $pageName = 'adminManufacturers';

    public function goto(int $page): void { $this->gotoPage($page, $this->pageName); }
    public function prevPage(): void      { $this->previousPage($this->pageName); }
    public function nextPage(): void      { $this->nextPage($this->pageName); }

    /** Paging window for page buttons (same pattern as loadouts) */
    public function getPageWindowProperty(): array
    {
        $p = $this->manufacturers;
        $window  = 2;
        $current = max(1, (int) $p->currentPage());
        $last    = max(1, (int) $p->lastPage());
        $start   = max(1, $current - $window);
        $end     = min($last, $current + $window);
        return compact('current','last','start','end');
    }

    /** Categories that power loadout dropdowns */
    public array $equipCategories = [
        'bow','arrow','sight','scope','rest','stabilizer','plunger','release'
    ];

    public function updatingSearch(){ $this->resetPage($this->pageName); }


    public function getManufacturersProperty()
    {
        $term = trim((string) $this->search);

        return Manufacturer::query()
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($qq) use ($term) {
                    $qq->where('name', 'like', "%{$term}%");

                    // If the term looks like a known category, also match JSON categories
                    $cat = strtolower($term);
                    $known = ['bow','arrow','sight','scope','rest','stabilizer','plunger','release'];
                    if (in_array($cat, $known, true)) {
                        $qq->orWhereJsonContains('categories', $cat);
                    }
                });
            })
            ->orderBy('name')
            ->paginate(4, pageName: $this->pageName);
    }


    public function openCreate(): void
    {
        $this->resetForm();
        $this->showSheet = true;
    }

    public function openEdit(int $id): void
    {
        $m = Manufacturer::findOrFail($id);
        $this->editingId = $m->id;
        $this->name = $m->name;
        $this->categories = is_array($m->categories) ? $m->categories : (array) $m->categories;
        $this->showSheet = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required','string','max:120'],
            'categories' => ['array'],
        ]);

        $validCats = array_values(array_intersect($this->categories, $this->equipCategories));

        if ($this->editingId) {
            $m = Manufacturer::findOrFail($this->editingId);
            $m->update([
                'name' => trim($this->name),
                'categories' => $validCats,
            ]);
        } else {
            Manufacturer::create([
                'name' => trim($this->name),
                'categories' => $validCats,
            ]);
        }

        $this->showSheet = false;
        $this->resetForm();
        $this->dispatch('toast', type:'success', message:'Manufacturer saved');
    }

    public function delete(int $id): void
    {
        Manufacturer::findOrFail($id)->delete();
        $this->dispatch('toast', type:'success', message:'Manufacturer deleted');
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->categories = [];
    }
}; ?>

<section class="mx-auto max-w-7xl">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-base font-semibold text-gray-900 dark:text-white">Manufacturers</h1>
      <p class="text-sm text-gray-600 dark:text-gray-300">These power your loadout dropdowns.</p>
    </div>
    <div class="flex gap-2">
      <flux:input placeholder="Search…" wire:model.live.debounce.400ms="search" />
      <flux:button wire:click="openCreate" variant="primary">Add</flux:button>
    </div>
  </div>

  <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
    <table class="w-full text-left">
      <thead class="bg-white dark:bg-gray-900">
        <tr>
          <th class="py-3.5 pl-4 pr-3 text-sm font-semibold">Name</th>
          <th class="px-3 py-3.5 text-sm font-semibold">Categories</th>
          <th class="py-3.5 pl-3 pr-4"><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-white/10">
        @foreach($this->manufacturers as $m)
        <tr>
          <td class="py-4 pl-4 pr-3 text-sm font-medium">{{ $m->name }}</td>
          <td class="px-3 py-4 text-sm text-gray-500">
            @foreach((array)$m->categories as $c)
              <span class="mr-1 inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/30">{{ $c }}</span>
            @endforeach
          </td>
          <td class="py-4 pl-3 pr-4 text-right text-sm space-x-3">
            <button wire:click="openEdit({{ $m->id }})" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">Edit</button>
            <button wire:click="delete({{ $m->id }})" class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">Delete</button>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>

    {{-- Pagination footer that visually connects to the table (same UX as Loadouts) --}}
    @php($p = $this->manufacturers)
    @php($w = $this->pageWindow)
    <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 dark:border-white/10 dark:bg-transparent">
        <!-- Mobile Prev/Next -->
        <div class="flex flex-1 justify-between sm:hidden">
            <button
                wire:click="prevPage"
                class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                {{ $p->onFirstPage() ? 'disabled aria-disabled=true' : '' }}
            >Previous</button>

            <button
                wire:click="nextPage"
                class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                {{ $p->hasMorePages() ? '' : 'disabled aria-disabled=true' }}
            >Next</button>
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
                    <button
                        wire:click="prevPage"
                        class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:inset-ring-gray-700 dark:hover:bg-white/5"
                        {{ $p->onFirstPage() ? 'disabled aria-disabled=true' : '' }}
                    >
                        <span class="sr-only">Previous</span>
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5"><path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" fill-rule="evenodd" /></svg>
                    </button>

                    @for ($i = $w['start']; $i <= $w['end']; $i++)
                        @if ($i === $w['current'])
                            <span aria-current="page" class="relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:focus-visible:outline-indigo-500">{{ $i }}</span>
                        @else
                            <button
                                wire:click="goto({{ $i }})"
                                class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:text-gray-200 dark:inset-ring-gray-700 dark:hover:bg-white/5"
                            >{{ $i }}</button>
                        @endif
                    @endfor

                    <button
                        wire:click="nextPage"
                        class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:inset-ring-gray-700 dark:hover:bg-white/5"
                        {{ $p->hasMorePages() ? '' : 'disabled aria-disabled=true' }}
                    >
                        <span class="sr-only">Next</span>
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5"><path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" /></svg>
                    </button>
                </nav>
            </div>
        </div>
    </div>

  {{-- Slide-over with scrolling body --}}
  @if($showSheet)
  <div class="fixed inset-0 z-40">
    <div class="absolute inset-0 bg-black/40" wire:click="$set('showSheet', false)"></div>

    <div class="absolute inset-y-0 right-0 w-full max-w-xl">
      <div class="flex h-full flex-col bg-white shadow-xl dark:bg-zinc-900">
        <div class="sticky top-0 z-10 flex items-center justify-between px-6 py-4 border-b border-zinc-200 bg-white/90 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/90">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ $editingId ? 'Edit manufacturer' : 'Add manufacturer' }}
          </h2>
          <button class="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10"
                  wire:click="$set('showSheet', false)">✕</button>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
          <form wire:submit.prevent="save" class="space-y-6">
            <div>
              <flux:label for="name">Name</flux:label>
              <flux:input id="name" type="text" wire:model="name" />
              @error('name') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
            </div>

            <div>
              <flux:heading size="sm" class="opacity-70">Categories</flux:heading>
              <div class="mt-3 grid grid-cols-2 gap-3">
                @foreach($this->equipCategories as $cat)
                  <label class="inline-flex items-center gap-2">
                    <input type="checkbox" class="rounded border-gray-300 dark:border-white/10"
                           value="{{ $cat }}" wire:model="categories">
                    <span class="text-sm">{{ ucfirst($cat) }}</span>
                  </label>
                @endforeach
              </div>
            </div>

            <div class="flex justify-end gap-3">
              <flux:button type="button" variant="ghost" wire:click="$set('showSheet', false)">Cancel</flux:button>
              <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  @endif
</section>
