<?php

use App\Models\Range;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    use \Livewire\WithPagination;

    protected string $pageName = 'rangesPage';

    public string $search = '';

    public string $sort = 'name';

    public string $direction = 'asc';

    public bool $showCreate = false;

    public bool $showEdit = false;

    public ?int $editingId = null;

    // -----------------------
    // Create fields
    // -----------------------
    public string $c_name = '';

    public string $c_environment = 'indoor';

    public string $c_distances = '18m';

    public int $c_bales_count = 10;

    public int $c_targets_per_bale = 4;

    public int $c_lanes_per_bale = 2;

    // NEW (CSV): "A,B" or blank
    public string $c_lane_slots_csv = 'A,B';

    public bool $c_is_active = true;

    public ?string $c_notes = null;

    // -----------------------
    // Edit fields
    // -----------------------
    public string $e_name = '';

    public string $e_environment = 'indoor';

    public string $e_distances = '18m';

    public int $e_bales_count = 10;

    public int $e_targets_per_bale = 4;

    public int $e_lanes_per_bale = 2;

    // NEW (CSV): "A,B" or blank
    public string $e_lane_slots_csv = 'A,B';

    public bool $e_is_active = true;

    public ?string $e_notes = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Range::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage($this->pageName);
    }

    public function with(): array
    {
        $companyId = (int) auth()->user()->company_id;

        $ranges = Range::query()
            ->where('company_id', $companyId)
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy($this->sort, $this->direction)
            ->paginate(15, ['*'], $this->pageName);

        return [
            'ranges' => $ranges,
        ];
    }

    // -----------------------
    // CSV <-> lane_slot_groups helpers
    // -----------------------

    protected function parseSlotsCsv(?string $csv): array
    {
        $csv = trim((string) $csv);

        if ($csv === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', $csv) ?: [];
        $parts = array_map(fn ($x) => strtoupper(trim((string) $x)), $parts);
        $parts = array_values(array_filter($parts, fn ($x) => $x !== ''));

        // de-dupe (preserve order)
        $seen = [];
        $out = [];
        foreach ($parts as $p) {
            if (! isset($seen[$p])) {
                $seen[$p] = true;
                $out[] = $p;
            }
        }

        return $out;
    }

    /**
     * Store lane_slot_groups in DB as array-of-arrays.
     * We store ONE group [[A,B]] and expand at runtime based on lanes_per_bale.
     * Blank CSV => [] (lane dropdown becomes 1,2,3... only).
     */
    protected function buildLaneSlotGroups(int $lanesPerBale, ?string $csv): array
    {
        $lanesPerBale = max(1, $lanesPerBale);

        // If only 1 lane/bale, slots are typically irrelevant. Allow blank.
        $slots = $this->parseSlotsCsv($csv);

        if (count($slots) === 0) {
            return [];
        }

        // Store one group; Range model (or your option builder) can repeat as needed.
        return [$slots];
    }

    protected function laneSlotsCsvFromGroups($groups): string
    {
        if (! is_array($groups) || count($groups) === 0) {
            return '';
        }

        $first = $groups[0] ?? [];
        if (! is_array($first) || count($first) === 0) {
            return '';
        }

        $slots = array_values(array_filter(array_map(
            fn ($x) => strtoupper(trim((string) $x)),
            $first
        ), fn ($x) => $x !== ''));

        return implode(',', $slots);
    }

    public function create(): void
    {
        Gate::authorize('create', Range::class);

        $this->validate([
            'c_name' => ['required', 'string', 'max:255'],
            'c_environment' => ['required', 'in:indoor,outdoor'],
            'c_distances' => ['required', 'string', 'max:255'],

            'c_bales_count' => ['required', 'integer', 'min:1', 'max:999'],
            'c_targets_per_bale' => ['required', 'integer', 'min:1', 'max:24'],
            'c_lanes_per_bale' => ['required', 'integer', 'min:1', 'max:12'],

            // CSV is optional; blank means "no slots"
            'c_lane_slots_csv' => ['nullable', 'string', 'max:255'],
        ]);

        $groups = $this->buildLaneSlotGroups((int) $this->c_lanes_per_bale, $this->c_lane_slots_csv);

        Range::create([
            'company_id' => (int) auth()->user()->company_id,
            'name' => $this->c_name,
            'environment' => $this->c_environment,
            'distances' => $this->c_distances,
            'bales_count' => $this->c_bales_count,
            'targets_per_bale' => $this->c_targets_per_bale,
            'lanes_per_bale' => $this->c_lanes_per_bale,
            'lane_slot_groups' => $groups,
            'is_active' => $this->c_is_active,
            'notes' => $this->c_notes,
        ]);

        $this->showCreate = false;

        // Reset to defaults
        $this->reset(['c_name', 'c_notes']);

        $this->c_environment = 'indoor';
        $this->c_distances = '18m';
        $this->c_bales_count = 10;
        $this->c_targets_per_bale = 4;
        $this->c_lanes_per_bale = 2;
        $this->c_lane_slots_csv = 'A,B';
        $this->c_is_active = true;
    }

    public function edit(int $id): void
    {
        $range = Range::query()
            ->where('company_id', (int) auth()->user()->company_id)
            ->findOrFail($id);

        Gate::authorize('update', $range);

        $this->editingId = $range->id;

        $this->e_name = (string) $range->name;
        $this->e_environment = (string) $range->environment;
        $this->e_distances = (string) $range->distances;
        $this->e_bales_count = (int) $range->bales_count;
        $this->e_targets_per_bale = (int) $range->targets_per_bale;
        $this->e_lanes_per_bale = (int) $range->lanes_per_bale;

        // Convert existing lane_slot_groups -> CSV for editing
        $this->e_lane_slots_csv = $this->laneSlotsCsvFromGroups($range->lane_slot_groups);

        $this->e_is_active = (bool) $range->is_active;
        $this->e_notes = $range->notes;

        $this->showEdit = true;
    }

    public function update(): void
    {
        $range = Range::query()
            ->where('company_id', (int) auth()->user()->company_id)
            ->findOrFail((int) $this->editingId);

        Gate::authorize('update', $range);

        $this->validate([
            'e_name' => ['required', 'string', 'max:255'],
            'e_environment' => ['required', 'in:indoor,outdoor'],
            'e_distances' => ['required', 'string', 'max:255'],

            'e_bales_count' => ['required', 'integer', 'min:1', 'max:999'],
            'e_targets_per_bale' => ['required', 'integer', 'min:1', 'max:24'],
            'e_lanes_per_bale' => ['required', 'integer', 'min:1', 'max:12'],

            'e_lane_slots_csv' => ['nullable', 'string', 'max:255'],
        ]);

        $groups = $this->buildLaneSlotGroups((int) $this->e_lanes_per_bale, $this->e_lane_slots_csv);

        $range->update([
            'name' => $this->e_name,
            'environment' => $this->e_environment,
            'distances' => $this->e_distances,
            'bales_count' => $this->e_bales_count,
            'targets_per_bale' => $this->e_targets_per_bale,
            'lanes_per_bale' => $this->e_lanes_per_bale,
            'lane_slot_groups' => $groups,
            'is_active' => $this->e_is_active,
            'notes' => $this->e_notes,
        ]);

        $this->showEdit = false;
        $this->editingId = null;
    }

    public function delete(int $id): void
    {
        $range = Range::query()
            ->where('company_id', (int) auth()->user()->company_id)
            ->findOrFail($id);

        Gate::authorize('delete', $range);

        $range->delete();
    }
};
?>

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="lg">Ranges</flux:heading>
            <flux:text size="sm" class="text-zinc-500">Company-owned shooting layouts</flux:text>
        </div>

        <div class="flex items-center gap-2">
            <flux:input placeholder="Search…" wire:model.live="search" />
            <flux:button variant="primary" wire:click="$set('showCreate', true)">New Range</flux:button>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-800">
                <thead class="bg-neutral-50 dark:bg-neutral-950/50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-neutral-700 dark:text-neutral-200">Name</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-700 dark:text-neutral-200">Environment</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-700 dark:text-neutral-200">Distances</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-700 dark:text-neutral-200">Layout</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-700 dark:text-neutral-200">Slots (CSV)</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-700 dark:text-neutral-200">Positions</th>
                        <th class="px-4 py-3 text-right font-medium text-neutral-700 dark:text-neutral-200">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                    @forelse ($ranges as $r)
                        @php
                            $lanesCount = max(1, (int)$r->bales_count) * max(1, (int)$r->lanes_per_bale);

                            $groups = is_array($r->lane_slot_groups) ? $r->lane_slot_groups : [];
                            $first = (is_array($groups) && isset($groups[0]) && is_array($groups[0])) ? $groups[0] : [];
                            $slotsCsv = implode(',', array_values(array_filter(array_map(fn($x) => strtoupper(trim((string)$x)), $first), fn($x) => $x !== '')));

                            // Positions per bale:
                            // - if slots provided: lanes_per_bale * slots_per_lane
                            // - if blank: lanes_per_bale (lane number only)
                            $slotsPerLane = count($first);
                            $positionsPerBale = $slotsPerLane > 0
                                ? max(1,(int)$r->lanes_per_bale) * $slotsPerLane
                                : max(1,(int)$r->lanes_per_bale);

                            $positions = max(1, (int) $r->bales_count) * max(1, (int) $r->lanes_per_bale);
                        @endphp

                        <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-800/40">
                            <td class="px-4 py-3">
                                <div class="font-medium text-neutral-900 dark:text-neutral-100">{{ $r->name }}</div>
                                <div class="text-xs text-neutral-500">{{ $r->is_active ? 'Active' : 'Inactive' }}</div>
                            </td>

                            <td class="px-4 py-3 capitalize text-neutral-700 dark:text-neutral-200">{{ $r->environment }}</td>

                            <td class="px-4 py-3 text-neutral-700 dark:text-neutral-200">{{ $r->distances }}</td>

                            <td class="px-4 py-3 text-neutral-700 dark:text-neutral-200">
                                {{ (int)$r->bales_count }} bales •
                                {{ (int)$r->targets_per_bale }}/bale •
                                {{ (int)$r->lanes_per_bale }} lanes/bale
                                <div class="text-xs text-neutral-500">{{ $lanesCount }} total lanes</div>
                            </td>

                            <td class="px-4 py-3 text-neutral-700 dark:text-neutral-200">
                                <span class="font-mono text-xs text-neutral-600 dark:text-neutral-300">
                                    {{ $slotsCsv !== '' ? $slotsCsv : '—' }}
                                </span>
                            </td>

                            <td class="px-4 py-3 text-neutral-700 dark:text-neutral-200">{{ $positions }}</td>

                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <flux:button size="sm" wire:click="edit({{ $r->id }})">Edit</flux:button>

                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        wire:click="delete({{ $r->id }})"
                                        onclick="return confirm('Delete this range?')"
                                    >Delete</flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-neutral-500">
                                No ranges found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3">
            {{ $ranges->links() }}
        </div>
    </div>

    {{-- CREATE PANEL (rulesets-style) --}}
    <div
        x-data="{ open: @entangle('showCreate') }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50"
        role="dialog"
        aria-modal="true"
    >
        <div class="absolute inset-0 bg-black/40" @click="open = false"></div>

        <aside class="absolute right-0 top-0 h-full w-full max-w-xl overflow-y-auto bg-white p-5 shadow-xl dark:bg-neutral-900">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">New Range</flux:heading>
                <button class="text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-100" @click="open = false">✕</button>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <flux:label>Name</flux:label>
                    <flux:input wire:model.defer="c_name" />
                    @error('c_name') <div class="mt-1 text-sm text-red-500">{{ $message }}</div> @enderror
                </div>

                <div>
                    <flux:label>Environment</flux:label>
                    <flux:select wire:model.defer="c_environment">
                        <option value="indoor">Indoor</option>
                        <option value="outdoor">Outdoor</option>
                    </flux:select>
                </div>

                <div>
                    <flux:label>Distances (comma separated)</flux:label>
                    <flux:input wire:model.defer="c_distances" placeholder="9m,18m" />
                </div>

                <div>
                    <flux:label># Bales</flux:label>
                    <flux:input type="number" min="1" wire:model.defer="c_bales_count" />
                </div>

                <div>
                    <flux:label>Targets per Bale</flux:label>
                    <flux:input type="number" min="1" wire:model.defer="c_targets_per_bale" />
                </div>

                <div>
                    <flux:label>Lanes per Bale</flux:label>
                    <flux:input type="number" min="1" wire:model.defer="c_lanes_per_bale" />
                </div>

                <div class="sm:col-span-2">
                    <flux:label>Lane Slots (CSV)</flux:label>
                    <flux:input
                        wire:model.defer="c_lane_slots_csv"
                        placeholder="A,B"
                    />
                    <div class="mt-1 text-xs text-neutral-500">
                        Example: <code>A,B</code> → lane options <code>1A,1B,2A,2B…</code>.
                        Leave blank for lane numbers only (<code>1,2,3…</code>).
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-200">
                        <input type="checkbox" class="rounded border-neutral-300 dark:border-neutral-700" wire:model.defer="c_is_active">
                        Active
                    </label>
                </div>

                <div class="sm:col-span-2">
                    <flux:label>Notes</flux:label>
                    <flux:textarea rows="3" wire:model.defer="c_notes" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <flux:button wire:click="$set('showCreate', false)">Cancel</flux:button>
                <flux:button variant="primary" wire:click="create">Create</flux:button>
            </div>
        </aside>
    </div>

    {{-- EDIT PANEL (rulesets-style) --}}
    <div
        x-data="{ open: @entangle('showEdit') }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50"
        role="dialog"
        aria-modal="true"
    >
        <div class="absolute inset-0 bg-black/40" @click="open = false"></div>

        <aside class="absolute right-0 top-0 h-full w-full max-w-xl overflow-y-auto bg-white p-5 shadow-xl dark:bg-neutral-900">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Edit Range</flux:heading>
                <button class="text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-100" @click="open = false">✕</button>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <flux:label>Name</flux:label>
                    <flux:input wire:model.defer="e_name" />
                    @error('e_name') <div class="mt-1 text-sm text-red-500">{{ $message }}</div> @enderror
                </div>

                <div>
                    <flux:label>Environment</flux:label>
                    <flux:select wire:model.defer="e_environment">
                        <option value="indoor">Indoor</option>
                        <option value="outdoor">Outdoor</option>
                    </flux:select>
                </div>

                <div>
                    <flux:label>Distances (comma separated)</flux:label>
                    <flux:input wire:model.defer="e_distances" placeholder="9m,18m" />
                </div>

                <div>
                    <flux:label># Bales</flux:label>
                    <flux:input type="number" min="1" wire:model.defer="e_bales_count" />
                </div>

                <div>
                    <flux:label>Targets per Bale</flux:label>
                    <flux:input type="number" min="1" wire:model.defer="e_targets_per_bale" />
                </div>

                <div>
                    <flux:label>Lanes per Bale</flux:label>
                    <flux:input type="number" min="1" wire:model.defer="e_lanes_per_bale" />
                </div>

                <div class="sm:col-span-2">
                    <flux:label>Lane Slots (CSV)</flux:label>
                    <flux:input
                        wire:model.defer="e_lane_slots_csv"
                        placeholder="A,B"
                    />
                    <div class="mt-1 text-xs text-neutral-500">
                        Example: <code>A,B</code> → lane options <code>1A,1B,2A,2B…</code>.
                        Leave blank for lane numbers only (<code>1,2,3…</code>).
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-200">
                        <input type="checkbox" class="rounded border-neutral-300 dark:border-neutral-700" wire:model.defer="e_is_active">
                        Active
                    </label>
                </div>

                <div class="sm:col-span-2">
                    <flux:label>Notes</flux:label>
                    <flux:textarea rows="3" wire:model.defer="e_notes" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <flux:button wire:click="$set('showEdit', false)">Cancel</flux:button>
                <flux:button variant="primary" wire:click="update">Save</flux:button>
            </div>
        </aside>
    </div>
</div>
