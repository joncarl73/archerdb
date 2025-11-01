<?php
use App\Models\BowType;
use App\Models\Discipline;
use App\Models\Division;
use App\Models\Ruleset;
use App\Models\RulesetClass;
use App\Models\TargetFace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component
{
    use \Livewire\WithPagination;

    // Table/pagination
    protected string $pageName = 'rulesetsPage';

    public string $search = '';

    public string $sort = 'name';

    public string $direction = 'asc';

    // Drawers
    public bool $showCreate = false;

    public bool $showEdit = false;

    // Lookups (collections)
    public $L_disciplines;

    public $L_bowTypes;

    public $L_targetFaces;

    public $L_divisions;

    public $L_classes;

    // -----------------------
    // CREATE fields
    // -----------------------
    public ?string $c_org = null;

    public string $c_name = '';

    public string $c_slug = '';

    public ?string $c_description = null;

    public array $c_selected_disciplines = [];

    public array $c_selected_bow_types = [];

    public array $c_selected_target_faces = [];

    public array $c_selected_divisions = [];

    public array $c_selected_classes = [];

    public array $c_schema = [];                 // reserved for future detailed scoring JSON

    public string $c_scoring_csv = '1,2,3,4,5,6,7,8,9,10';

    public ?int $c_x_value = 10;

    // NEW: distances
    public string $c_distances_csv = '18,50,60';

    // -----------------------
    // EDIT fields
    // -----------------------
    public ?int $editingId = null;

    public ?string $e_org = null;

    public string $e_name = '';

    public string $e_slug = '';

    public ?string $e_description = null;

    public array $e_selected_disciplines = [];

    public array $e_selected_bow_types = [];

    public array $e_selected_target_faces = [];

    public array $e_selected_divisions = [];

    public array $e_selected_classes = [];

    public array $e_schema = [];

    public string $e_scoring_csv = '';

    public ?int $e_x_value = null;

    // NEW: distances
    public string $e_distances_csv = '';

    // -----------------------
    // Lifecycle
    // -----------------------
    public function mount(): void
    {
        $this->loadLookups();
    }

    private function loadLookups(): void
    {
        $this->L_disciplines = Discipline::orderBy('label')->get(['id', 'key', 'label']);
        $this->L_bowTypes = BowType::orderBy('label')->get(['id', 'key', 'label']);
        $this->L_targetFaces = TargetFace::orderBy('label')->get(['id', 'key', 'label', 'kind', 'diameter_cm', 'zones']);
        $this->L_divisions = Division::orderBy('label')->get(['id', 'key', 'label']);
        $this->L_classes = RulesetClass::orderBy('label')->get(['id', 'key', 'label']);
    }

    public function with(): array
    {
        $q = Ruleset::query()
            ->where('company_id', auth()->user()->company_id)
            ->when($this->search !== '', function ($q) {
                $s = "%{$this->search}%";
                $q->where(fn ($w) => $w->where('name', 'like', $s)
                    ->orWhere('slug', 'like', $s)
                    ->orWhere('org', 'like', $s));
            })
            ->orderBy($this->sort, $this->direction);

        return [
            'rulesets' => $q->paginate(10, ['*'], $this->pageName),
        ];
    }

    public function sortBy(string $col): void
    {
        if (! in_array($col, ['name', 'slug', 'org'], true)) {
            return;
        }

        if ($this->sort === $col) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $col;
            $this->direction = 'asc';
        }

        $this->resetPage($this->pageName);
    }

    // -----------------------
    // Helpers
    // -----------------------
    private function normalizeScoringCsv(?string $csv): array
    {
        if (! $csv) {
            return [];
        }
        $vals = array_filter(array_map('trim', explode(',', $csv)), fn ($v) => $v !== '');
        $ints = array_map('intval', $vals);
        $ints = array_values(array_unique($ints));
        sort($ints, SORT_NUMERIC);

        return $ints;
    }

    // Distances: accept "18,50,60" or "18m, 50M"; store as numeric meters
    private function normalizeDistancesCsv(?string $csv): array
    {
        if (! $csv) {
            return [];
        }
        $vals = array_filter(array_map('trim', explode(',', $csv)), fn ($v) => $v !== '');
        $nums = [];
        foreach ($vals as $v) {
            // strip trailing 'm'/'M'
            $v = preg_replace('/\s*m\s*$/i', '', $v);
            if ($v === '' || ! is_numeric($v)) {
                continue;
            }
            $nums[] = (int) round((float) $v);
        }
        $nums = array_values(array_unique($nums));
        sort($nums, SORT_NUMERIC);

        return $nums;
    }

    private function validateXAgainstScale(?int $x, array $scale, string $field): bool
    {
        if (is_null($x)) {
            return true;
        }
        if (empty($scale)) {
            $this->addError($field, 'Provide a scoring scale first.');

            return false;
        }
        $max = max($scale);
        if (! in_array($x, $scale, true) && $x !== $max + 1) {
            $this->addError($field, 'X value must be in the scale or exactly one more than the max (e.g., 11 for 1–10).');

            return false;
        }

        return true;
    }

    // -----------------------
    // Create drawer
    // -----------------------
    public function openCreate(): void
    {
        Gate::authorize('create', Ruleset::class);
        $this->resetErrorBag();

        $this->c_org = null;
        $this->c_name = '';
        $this->c_slug = '';
        $this->c_description = null;

        $this->c_selected_disciplines = [];
        $this->c_selected_bow_types = [];
        $this->c_selected_target_faces = [];
        $this->c_selected_divisions = [];
        $this->c_selected_classes = [];

        $this->c_schema = [];
        $this->c_scoring_csv = '1,2,3,4,5,6,7,8,9,10';
        $this->c_x_value = 10;

        $this->c_distances_csv = '18,50,60';

        $this->showCreate = true;
    }

    public function updatedCName($val): void
    {
        if ($this->c_slug === '' || $this->c_slug === Str::slug($val)) {
            $this->c_slug = Str::slug($val);
        }
    }

    public function create(): void
    {
        Gate::authorize('create', Ruleset::class);

        $this->validate([
            'c_name' => ['required', 'string', 'max:255'],
            'c_slug' => ['required', 'string', 'max:255', 'unique:rulesets,slug'],
            'c_scoring_csv' => ['required', 'string'],
            'c_x_value' => ['nullable', 'integer', 'min:0', 'max:100'],
            'c_distances_csv' => ['required', 'string'],
        ], [], [
            'c_name' => 'name',
            'c_slug' => 'slug',
            'c_scoring_csv' => 'scoring scale',
            'c_x_value' => 'X value',
            'c_distances_csv' => 'distances',
        ]);

        $scale = $this->normalizeScoringCsv($this->c_scoring_csv);
        if (empty($scale)) {
            $this->addError('c_scoring_csv', 'Enter a comma-separated list of integers (e.g., 1,2,3,4,5,6,7,8,9,10).');

            return;
        }
        if (! $this->validateXAgainstScale($this->c_x_value, $scale, 'c_x_value')) {
            return;
        }

        $distances = $this->normalizeDistancesCsv($this->c_distances_csv);
        if (empty($distances)) {
            $this->addError('c_distances_csv', 'Enter one or more distances in meters (e.g., 18,50,60).');

            return;
        }

        $r = Ruleset::create([
            'company_id' => auth()->user()->company_id,
            'org' => $this->c_org,
            'name' => $this->c_name,
            'slug' => $this->c_slug,
            'description' => $this->c_description,
            'schema' => $this->c_schema,   // future detailed scoring JSON
            'scoring_values' => $scale,            // JSON array
            'x_value' => $this->c_x_value, // int or null
            'distances_m' => $distances,       // JSON array (meters)
        ]);

        // sync lookups
        $r->disciplines()->sync($this->c_selected_disciplines);
        $r->bowTypes()->sync($this->c_selected_bow_types);
        $r->targetFaces()->sync($this->c_selected_target_faces);
        $r->divisions()->sync($this->c_selected_divisions);
        $r->classes()->sync($this->c_selected_classes);

        $this->showCreate = false;
        session()->flash('ok', 'Ruleset created.');
        $this->resetPage($this->pageName);
    }

    // -----------------------
    // Edit drawer
    // -----------------------
    public function openEdit(int $id): void
    {
        $this->resetErrorBag();

        $r = Ruleset::query()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        Gate::authorize('update', $r);

        $this->editingId = $r->id;

        $this->e_org = $r->org;
        $this->e_name = $r->name;
        $this->e_slug = $r->slug;
        $this->e_description = $r->description;

        $this->e_schema = $r->schema ?? [];

        // hydrate selections
        $this->e_selected_disciplines = $r->disciplines()->pluck('id')->toArray();
        $this->e_selected_bow_types = $r->bowTypes()->pluck('id')->toArray();
        $this->e_selected_target_faces = $r->targetFaces()->pluck('id')->toArray();
        $this->e_selected_divisions = $r->divisions()->pluck('id')->toArray();
        $this->e_selected_classes = $r->classes()->pluck('id')->toArray();

        // scoring + distances
        $this->e_scoring_csv = implode(',', array_map('intval', $r->scoring_values ?? []));
        $this->e_x_value = $r->x_value;
        $this->e_distances_csv = implode(',', array_map('intval', $r->distances_m ?? [])); // show as CSV

        $this->showEdit = true;
    }

    public function saveEdit(): void
    {
        if (! $this->editingId) {
            return;
        }

        $r = Ruleset::query()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($this->editingId);

        Gate::authorize('update', $r);

        $this->validate([
            'e_name' => ['required', 'string', 'max:255'],
            'e_slug' => ['required', 'string', 'max:255', "unique:rulesets,slug,{$r->id}"],
            'e_scoring_csv' => ['required', 'string'],
            'e_x_value' => ['nullable', 'integer', 'min:0', 'max:100'],
            'e_distances_csv' => ['required', 'string'],
        ], [], [
            'e_name' => 'name',
            'e_slug' => 'slug',
            'e_scoring_csv' => 'scoring scale',
            'e_x_value' => 'X value',
            'e_distances_csv' => 'distances',
        ]);

        $scale = $this->normalizeScoringCsv($this->e_scoring_csv);
        if (empty($scale)) {
            $this->addError('e_scoring_csv', 'Enter a comma-separated list of integers.');

            return;
        }
        if (! $this->validateXAgainstScale($this->e_x_value, $scale, 'e_x_value')) {
            return;
        }

        $distances = $this->normalizeDistancesCsv($this->e_distances_csv);
        if (empty($distances)) {
            $this->addError('e_distances_csv', 'Enter one or more distances in meters (e.g., 18,50,60).');

            return;
        }

        $r->update([
            'org' => $this->e_org,
            'name' => $this->e_name,
            'slug' => $this->e_slug,
            'description' => $this->e_description,
            'schema' => $this->e_schema,
            'scoring_values' => $scale,
            'x_value' => $this->e_x_value,
            'distances_m' => $distances,
        ]);

        // sync lookups
        $r->disciplines()->sync($this->e_selected_disciplines);
        $r->bowTypes()->sync($this->e_selected_bow_types);
        $r->targetFaces()->sync($this->e_selected_target_faces);
        $r->divisions()->sync($this->e_selected_divisions);
        $r->classes()->sync($this->e_selected_classes);

        $this->showEdit = false;
        session()->flash('ok', 'Ruleset updated.');
    }

    public function delete(int $id): void
    {
        $r = Ruleset::query()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        Gate::authorize('delete', $r);

        $r->delete();
        session()->flash('ok', 'Ruleset deleted.');
        $this->resetPage($this->pageName);
    }
};
?>

<div class="mx-auto max-w-7xl relative">
  {{-- Header --}}
  <div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
      <h1 class="text-base font-semibold text-gray-900 dark:text-white">Rulesets</h1>
      <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
        Define disciplines, bow types, target faces, divisions, classes, scoring, and distances for your company.
      </p>
    </div>
    <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
      <flux:button variant="primary" color="indigo" icon="plus" wire:click="openCreate">
        New ruleset
      </flux:button>
    </div>
  </div>

  {{-- Flash OK --}}
  @if (session('ok'))
    <div class="mt-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">
      {{ session('ok') }}
    </div>
  @endif

  {{-- Search --}}
  <div class="mt-4 max-w-sm">
    <flux:input icon="magnifying-glass" placeholder="Search name, slug, org…" wire:model.live.debounce.250ms="search" />
  </div>

  {{-- Table --}}
  <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-zinc-700">
    <table class="w-full text-left">
      <thead class="bg-white dark:bg-gray-900">
        <tr>
          <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold">
            <button wire:click="sortBy('name')" class="flex items-center gap-1">
              Name @if($sort==='name')<span class="text-xs opacity-70">{{ $direction==='asc'?'▲':'▼' }}</span>@endif
            </button>
          </th>
          <th class="hidden md:table-cell px-3 py-3.5 text-sm font-semibold">
            <button wire:click="sortBy('slug')" class="flex items-center gap-1">
              Slug @if($sort==='slug')<span class="text-xs opacity-70">{{ $direction==='asc'?'▲':'▼' }}</span>@endif
            </button>
          </th>
          <th class="hidden md:table-cell px-3 py-3.5 text-sm font-semibold">
            <button wire:click="sortBy('org')" class="flex items-center gap-1">
              Org @if($sort==='org')<span class="text-xs opacity-70">{{ $direction==='asc'?'▲':'▼' }}</span>@endif
            </button>
          </th>
          <th class="hidden lg:table-cell px-3 py-3.5 text-sm font-semibold">Scoring</th>
          <th class="hidden lg:table-cell px-3 py-3.5 text-sm font-semibold">Distances</th>
          <th class="py-3.5 pl-3 pr-4 text-right text-sm font-semibold">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-white/10">
        @foreach ($rulesets as $r)
          <tr>
            <td class="py-3.5 pl-4 pr-3 text-sm font-medium">{{ $r->name }}</td>
            <td class="hidden md:table-cell px-3 py-3.5 text-sm text-gray-600 dark:text-gray-300">{{ $r->slug }}</td>
            <td class="hidden md:table-cell px-3 py-3.5 text-sm text-gray-600 dark:text-gray-300">{{ $r->org ?: '—' }}</td>
            <td class="hidden lg:table-cell px-3 py-3.5 text-sm text-gray-600 dark:text-gray-300">
              @php
                $vals = is_array($r->scoring_values ?? null) ? $r->scoring_values : [];
                $preview = empty($vals) ? '—' : (count($vals) > 6 ? (implode(',', array_slice($vals, 0, 6)).',…') : implode(',', $vals));
              @endphp
              <span class="whitespace-nowrap">{{ $preview }}</span>
              @if(!is_null($r->x_value))
                <span class="text-gray-400"> · X={{ $r->x_value }}</span>
              @endif
            </td>
            <td class="hidden lg:table-cell px-3 py-3.5 text-sm text-gray-600 dark:text-gray-300">
              @php
                $d = is_array($r->distances_m ?? null) ? $r->distances_m : [];
                $dPreview = empty($d) ? '—' : (count($d) > 4 ? (implode(',', array_slice($d, 0, 4)).',…') : implode(',', $d));
              @endphp
              <span class="whitespace-nowrap">{{ $dPreview }}</span><span class="text-gray-400">{{ empty($d) ? '' : ' m' }}</span>
            </td>
            <td class="py-3.5 pl-3 pr-4 text-right text-sm">
              <div class="inline-flex items-center gap-2">
                <flux:button size="sm" appearance="secondary" icon="pencil-square" wire:click="openEdit({{ $r->id }})">Edit</flux:button>
                <flux:button size="sm" appearance="danger" icon="trash" wire:click="delete({{ $r->id }})"
                             onclick="return confirm('Delete this ruleset?');">Delete</flux:button>
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{-- Pagination --}}
  <div class="mt-4">
    {{ $rulesets->links() }}
  </div>

  {{-- =========================
       CREATE DRAWER
     ========================= --}}
  @if($showCreate)
    <div class="fixed inset-0 z-40 bg-black/40" wire:click="$set('showCreate', false)" aria-hidden="true"></div>
    <aside class="fixed inset-y-0 right-0 z-50 w/full max-w-3xl bg-white dark:bg-zinc-900 shadow-xl border-l border-gray-200 dark:border-zinc-800 flex flex-col">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-zinc-800">
        <flux:text as="h2" class="text-lg font-semibold">New ruleset</flux:text>
        <flux:button icon="x-mark" appearance="ghost" size="sm" wire:click="$set('showCreate', false)" />
      </div>

      <div class="flex-1 overflow-auto p-5 space-y-6">
        {{-- Meta --}}
        <div class="grid gap-4 md:grid-cols-3">
          <div>
            <flux:label>Org (optional)</flux:label>
            <flux:input wire:model.defer="c_org" />
          </div>
          <div>
            <flux:label>Name</flux:label>
            <flux:input wire:model.live="c_name" />
            @error('c_name')<flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>@enderror
          </div>
          <div>
            <flux:label>Slug</flux:label>
            <flux:input wire:model.defer="c_slug" />
            @error('c_slug')<flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>@enderror
          </div>
          <div class="md:col-span-3">
            <flux:label>Description (optional)</flux:label>
            <flux:textarea rows="2" wire:model.defer="c_description" />
          </div>
        </div>

        {{-- Scoring + Distances --}}
        <div class="grid gap-4 md:grid-cols-3">
          <div class="md:col-span-2">
            <flux:label>Scoring scale (CSV)</flux:label>
            <flux:input wire:model.defer="c_scoring_csv" placeholder="e.g., 1,2,3,4,5,6,7,8,9,10" />
            @error('c_scoring_csv')<flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>@enderror
            <flux:text class="text-xs text-gray-500">We’ll normalize this and store as an array.</flux:text>
          </div>
          <div>
            <flux:label>X value (optional)</flux:label>
            <flux:input type="number" min="0" max="100" wire:model.defer="c_x_value" placeholder="e.g., 10 or 11" />
            @error('c_x_value')<flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>@enderror
          </div>
          <div class="md:col-span-3">
            <flux:label>Distances (m, CSV)</flux:label>
            <flux:input wire:model.defer="c_distances_csv" placeholder="e.g., 18,50,60" />
            @error('c_distances_csv')<flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>@enderror
          </div>
        </div>

        {{-- Lookups as checkboxes --}}
        <div class="grid gap-6 md:grid-cols-2">
          <div>
            <flux:label>Disciplines</flux:label>
            <div class="mt-2 grid grid-cols-1 gap-2">
              @foreach($L_disciplines as $d)
                <label class="inline-flex items-center gap-2">
                  <flux:checkbox wire:model="c_selected_disciplines" value="{{ $d->id }}" />
                  <span>{{ $d->label }}</span>
                </label>
              @endforeach
            </div>
          </div>
          <div>
            <flux:label>Bow types</flux:label>
            <div class="mt-2 grid grid-cols-1 gap-2">
              @foreach($L_bowTypes as $b)
                <label class="inline-flex items-center gap-2">
                  <flux:checkbox wire:model="c_selected_bow_types" value="{{ $b->id }}" />
                  <span>{{ $b->label }}</span>
                </label>
              @endforeach
            </div>
          </div>
          <div>
            <flux:label>Target faces</flux:label>
            <div class="mt-2 grid grid-cols-1 gap-2">
              @foreach($L_targetFaces as $f)
                <label class="inline-flex items-center gap-2">
                  <flux:checkbox wire:model="c_selected_target_faces" value="{{ $f->id }}" />
                  <span>{{ $f->label }}</span>
                </label>
              @endforeach
            </div>
          </div>
          <div>
            <flux:label>Divisions</flux:label>
            <div class="mt-2 grid grid-cols-1 gap-2">
              @foreach($L_divisions as $v)
                <label class="inline-flex items-center gap-2">
                  <flux:checkbox wire:model="c_selected_divisions" value="{{ $v->id }}" />
                  <span>{{ $v->label }}</span>
                </label>
              @endforeach
            </div>
          </div>
          <div class="md:col-span-2">
            <flux:label>Classes</flux:label>
            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
              @foreach($L_classes as $c)
                <label class="inline-flex items-center gap-2">
                  <flux:checkbox wire:model="c_selected_classes" value="{{ $c->id }}" />
                  <span>{{ $c->label }}</span>
                </label>
              @endforeach
            </div>
          </div>
        </div>
      </div>

      <div class="border-t border-gray-200 dark:border-zinc-800 px-5 py-4 flex items-center gap-2">
        <div class="ml-auto flex items-center gap-2">
          <flux:button size="sm" appearance="secondary" wire:click="$set('showCreate', false)">Cancel</flux:button>
          <flux:button size="sm" icon="check" wire:click="create">Save</flux:button>
        </div>
      </div>
    </aside>
  @endif

  {{-- =========================
       EDIT DRAWER
     ========================= --}}
  @if($showEdit)
    <div class="fixed inset-0 z-40 bg-black/40" wire:click="$set('showEdit', false)" aria-hidden="true"></div>
    <aside class="fixed inset-y-0 right-0 z-50 w/full max-w-3xl bg-white dark:bg-zinc-900 shadow-xl border-l border-gray-200 dark:border-zinc-800 flex flex-col">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-zinc-800">
        <flux:text as="h2" class="text-lg font-semibold">Edit ruleset</flux:text>
        <flux:button icon="x-mark" appearance="ghost" size="sm" wire:click="$set('showEdit', false)" />
      </div>

      <div class="flex-1 overflow-auto p-5 space-y-6">
        {{-- Meta --}}
        <div class="grid gap-4 md:grid-cols-3">
          <div>
            <flux:label>Org (optional)</flux:label>
            <flux:input wire:model.defer="e_org" />
          </div>
          <div>
            <flux:label>Name</flux:label>
            <flux:input wire:model.defer="e_name" />
            @error('e_name')<flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>@enderror
          </div>
          <div>
            <flux:label>Slug</flux:label>
            <flux:input wire:model.defer="e_slug" />
            @error('e_slug')<flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>@enderror
          </div>
          <div class="md:col-span-3">
            <flux:label>Description (optional)</flux:label>
            <flux:textarea rows="2" wire:model.defer="e_description" />
          </div>
        </div>

        {{-- Scoring + Distances --}}
        <div class="grid gap-4 md:grid-cols-3">
          <div class="md:col-span-2">
            <flux:label>Scoring scale (CSV)</flux:label>
            <flux:input wire:model.defer="e_scoring_csv" placeholder="e.g., 1,2,3,4,5,6,7,8,9,10" />
            @error('e_scoring_csv')<flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>@enderror
            <flux:text class="text-xs text-gray-500">Stored as an array (JSON) on the ruleset.</flux:text>
          </div>
          <div>
            <flux:label>X value (optional)</flux:label>
            <flux:input type="number" min="0" max="100" wire:model.defer="e_x_value" placeholder="e.g., 10 or 11" />
            @error('e_x_value')<flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>@enderror
          </div>
          <div class="md:col-span-3">
            <flux:label>Distances (m, CSV)</flux:label>
            <flux:input wire:model.defer="e_distances_csv" placeholder="e.g., 18,50,60" />
            @error('e_distances_csv')<flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>@enderror
          </div>
        </div>

        {{-- Lookups as checkboxes --}}
        <div class="grid gap-6 md:grid-cols-2">
          <div>
            <flux:label>Disciplines</flux:label>
            <div class="mt-2 grid grid-cols-1 gap-2">
              @foreach($L_disciplines as $d)
                <label class="inline-flex items-center gap-2">
                  <flux:checkbox wire:model="e_selected_disciplines" value="{{ $d->id }}" />
                  <span>{{ $d->label }}</span>
                </label>
              @endforeach
            </div>
          </div>
          <div>
            <flux:label>Bow types</flux:label>
            <div class="mt-2 grid grid-cols-1 gap-2">
              @foreach($L_bowTypes as $b)
                <label class="inline-flex items-center gap-2">
                  <flux:checkbox wire:model="e_selected_bow_types" value="{{ $b->id }}" />
                  <span>{{ $b->label }}</span>
                </label>
              @endforeach
            </div>
          </div>
          <div>
            <flux:label>Target faces</flux:label>
            <div class="mt-2 grid grid-cols-1 gap-2">
              @foreach($L_targetFaces as $f)
                <label class="inline-flex items-center gap-2">
                  <flux:checkbox wire:model="e_selected_target_faces" value="{{ $f->id }}" />
                  <span>{{ $f->label }}</span>
                </label>
              @endforeach
            </div>
          </div>
          <div>
            <flux:label>Divisions</flux:label>
            <div class="mt-2 grid grid-cols-1 gap-2">
              @foreach($L_divisions as $v)
                <label class="inline-flex items-center gap-2">
                  <flux:checkbox wire:model="e_selected_divisions" value="{{ $v->id }}" />
                  <span>{{ $v->label }}</span>
                </label>
              @endforeach
            </div>
          </div>
          <div class="md:col-span-2">
            <flux:label>Classes</flux:label>
            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
              @foreach($L_classes as $c)
                <label class="inline-flex items-center gap-2">
                  <flux:checkbox wire:model="e_selected_classes" value="{{ $c->id }}" />
                  <span>{{ $c->label }}</span>
                </label>
              @endforeach
            </div>
          </div>
        </div>
      </div>

      <div class="border-t border-gray-200 dark:border-zinc-800 px-5 py-4 flex items-center gap-2">
        <flux:button size="sm" appearance="danger" icon="trash"
          wire:click="delete({{ $editingId ?? 0 }})"
          onclick="return confirm('Delete this ruleset?');">Delete</flux:button>
        <div class="ml-auto flex items-center gap-2">
          <flux:button size="sm" appearance="secondary" wire:click="$set('showEdit', false)">Cancel</flux:button>
          <flux:button size="sm" icon="check" wire:click="saveEdit">Save</flux:button>
        </div>
      </div>
    </aside>
  @endif
</div>
