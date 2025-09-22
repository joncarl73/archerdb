<?php
use App\Models\KioskSession;
use App\Models\League;
use App\Models\LeagueWeek;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component
{
    public League $league;

    // form state
    public int $week_number = 1;

    public array $lanes = [];

    // derived for UI
    public array $laneOptions = []; // ['1','1A','1B',...]

    public $weeks;

    public $sessions;

    // flash reveal
    public ?string $createdToken = null;

    public function mount(League $league): void
    {
        Gate::authorize('update', $league);

        $this->league = $league->load([
            'weeks' => fn ($q) => $q->orderBy('week_number'),
        ]);

        // weeks list for select
        $this->weeks = $this->league->weeks->map(fn ($w) => [
            'week_number' => (int) $w->week_number,
            'date' => $w->date,
        ]);

        $this->week_number = (int) ($this->weeks[0]['week_number'] ?? 1);

        // sessions table
        $this->sessions = KioskSession::where('league_id', $league->id)
            ->latest()->get();

        // lane options (exactly like your public check-in)
        $letters = $league->lane_breakdown->letters();                  // [] or ['A','B'] or ['A','B','C','D']
        $positionsPerLane = $league->lane_breakdown->positionsPerLane(); // 1,2,4
        $opts = [];
        for ($i = 1; $i <= (int) $league->lanes_count; $i++) {
            if ($positionsPerLane === 1) {
                $opts[] = (string) $i;
            } else {
                foreach ($letters as $L) {
                    $opts[] = $i.$L;
                }
            }
        }
        $this->laneOptions = $opts;
    }

    public function createSession(): void
    {
        Gate::authorize('update', $this->league);

        $this->validate([
            'week_number' => ['required', 'integer', 'between:1,'.$this->league->length_weeks],
            'lanes' => ['required', 'array', 'min:1'],
            'lanes.*' => ['string', 'max:10'],
        ]);

        // ensure the week exists on this league
        $exists = LeagueWeek::where('league_id', $this->league->id)
            ->where('week_number', $this->week_number)->exists();
        if (! $exists) {
            $this->addError('week_number', 'Selected week does not exist for this league.');

            return;
        }

        $session = KioskSession::create([
            'league_id' => $this->league->id,
            'week_number' => $this->week_number,
            'lanes' => array_values($this->lanes),
            'token' => Str::random(40),
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        // refresh list, reset form, show token
        $this->sessions = KioskSession::where('league_id', $this->league->id)
            ->latest()->get();

        $this->lanes = [];
        $this->createdToken = $session->token;

        $this->dispatch('toast', type: 'success', message: 'Kiosk session created.');
    }

    public function toggleSession(int $id): void
    {
        Gate::authorize('update', $this->league);

        $s = KioskSession::where('league_id', $this->league->id)->findOrFail($id);
        $s->is_active = ! $s->is_active;
        $s->save();

        $this->sessions = KioskSession::where('league_id', $this->league->id)
            ->latest()->get();
    }
};
?>

<section class="w-full">
    {{-- Header --}}
    <div class="mx-auto max-w-7xl">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ $league->title }} — Kiosk Sessions
                </h1>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    Assign lanes to a tablet and share the tokenized URL with archers at those lanes.
                </p>
            </div>

            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <a href="{{ route('corporate.leagues.show', $league) }}"
                   class="rounded-md bg-white px-3 py-2 text-sm font-medium inset-ring inset-ring-gray-300 hover:bg-gray-50
                          dark:bg-white/5 dark:text-gray-200 dark:inset-ring-white/10 dark:hover:bg-white/10">
                    Back to League
                </a>
            </div>
        </div>

        {{-- Flash reveal of the URL --}}
        @if ($createdToken)
            @php($kioskUrl = url('/k/'.$createdToken))
            <div class="mt-4 rounded-xl border border-emerald-300/40 bg-emerald-50 p-4 text-emerald-900
                        dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                <div class="font-medium">Kiosk session created</div>
                <div class="mt-1 text-sm">
                    Tablet URL:
                    <a class="underline hover:no-underline" href="{{ $kioskUrl }}" target="_blank" rel="noopener">
                        {{ $kioskUrl }}
                    </a>
                </div>
            </div>
        @endif

        {{-- Create session --}}
        <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/5">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Create kiosk session</h2>
            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                Pick a week and one or more lanes. You’ll get a shareable tablet link.
            </p>

            <form wire:submit.prevent="createSession" class="mt-5 space-y-6">
                {{-- Week select --}}
                <div>
                    <label for="week_number" class="block text-sm font-medium text-gray-800 dark:text-gray-200">Week</label>
                    <select id="week_number" wire:model="week_number"
                            class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm
                                   focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                        @foreach ($weeks as $w)
                            <option value="{{ $w['week_number'] }}">
                                Week {{ $w['week_number'] }} — {{ \Illuminate\Support\Carbon::parse($w['date'])->toFormattedDateString() }}
                            </option>
                        @endforeach
                    </select>
                    @error('week_number') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                {{-- Lane checkboxes --}}
                <div>
                    <div class="flex items-center justify-between">
                        <label class="block text-sm font-medium text-gray-800 dark:text-gray-200">Lanes</label>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Select one or more</div>
                    </div>

                    <div class="mt-2 grid gap-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        @foreach ($laneOptions as $laneCode)
                            <label class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white p-2 text-sm
                                           dark:border-white/10 dark:bg-white/5">
                                <input type="checkbox" wire:model="lanes" value="{{ $laneCode }}"
                                       class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600">
                                <span class="text-gray-800 dark:text-gray-200">Lane {{ $laneCode }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('lanes') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('lanes.*') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs
                                   hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600
                                   dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                        Create kiosk session
                    </button>
                    <p class="text-xs text-gray-500 dark:text-gray-400">A unique tokenized URL will be generated.</p>
                </div>
            </form>
        </div>

        {{-- Sessions table --}}
        <div class="mt-8 overflow-hidden rounded-2xl border border-gray-200 shadow-sm dark:border-white/10">
            <table class="w-full text-left">
                <thead class="bg-white dark:bg-gray-900">
                    <tr>
                        <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Created</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Week</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Lanes</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Status</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Tablet URL</th>
                        <th class="py-3.5 pl-3 pr-4 text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10 bg-white dark:bg-transparent">
                    @forelse ($sessions as $s)
                        @php($url = url('/k/'.$s->token))
                        <tr>
                            <td class="py-3.5 pl-4 pr-3 text-sm text-gray-800 dark:text-gray-200">
                                {{ $s->created_at?->format('Y-m-d H:i') ?? '—' }}
                            </td>
                            <td class="px-3 py-3.5 text-sm text-gray-800 dark:text-gray-200">
                                Week {{ $s->week_number }}
                            </td>
                            <td class="px-3 py-3.5 text-sm text-gray-800 dark:text-gray-200">
                                @if (is_array($s->lanes) && count($s->lanes))
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($s->lanes as $lc)
                                            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                                {{ $lc }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-3.5 text-sm">
                                @if ($s->is_active)
                                    <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">Active</span>
                                @else
                                    <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-300">Inactive</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5 text-sm">
                                <a href="{{ $url }}" class="truncate text-indigo-600 underline hover:no-underline dark:text-indigo-400" target="_blank" rel="noopener">
                                    {{ $url }}
                                </a>
                            </td>
                            <td class="py-3.5 pl-3 pr-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ $url }}" target="_blank" rel="noopener"
                                       class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                                        Open
                                    </a>
                                    <button type="button"
                                            x-data="{copied:false}"
                                            @click="navigator.clipboard.writeText('{{ $url }}'); copied=true; setTimeout(()=>copied=false,1500)"
                                            class="rounded-md bg-white px-3 py-1.5 text-xs font-medium inset-ring inset-ring-gray-300 hover:bg-gray-50
                                                   dark:bg-white/5 dark:text-gray-200 dark:inset-ring-white/10 dark:hover:bg-white/10">
                                        <span x-show="!copied">Copy</span>
                                        <span x-show="copied">Copied!</span>
                                    </button>
                                    <button wire:click="toggleSession({{ $s->id }})"
                                            class="rounded-md bg-white px-3 py-1.5 text-xs font-medium inset-ring inset-ring-gray-300 hover:bg-gray-50
                                                   dark:bg-white/5 dark:text-gray-200 dark:inset-ring-white/10 dark:hover:bg-white/10">
                                        {{ $s->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 px-4 text-sm text-gray-500 dark:text-gray-400">
                                No kiosk sessions yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</section>
