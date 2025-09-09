{{-- resources/views/livewire/pages/onboarding.blade.php --}}
<?php
use Illuminate\Support\Facades\Auth;
use App\Models\ArcherProfile;
use function Livewire\Volt\{state, rules, layout, title};

layout('components.layouts.app');
title('Onboarding');

state([
    'gender' => '',
    'birth_date' => '',
    'handedness' => '',
    'para_archer' => false,
    'uses_wheelchair' => false,
    'club_affiliation' => '',
    'us_archery_number' => '',
    'country' => '',
]);

rules(fn () => [
    'gender'            => ['required','in:male,female,nonbinary,other,prefer_not_to_say'],
    'birth_date'        => ['required','date','before:today'],
    'handedness'        => ['required','in:right,left'],
    'para_archer'       => ['boolean'],
    'uses_wheelchair'   => ['boolean'],
    'club_affiliation'  => ['nullable','string','max:120'],
    'us_archery_number' => ['nullable','string','max:30'],
    'country'           => ['required','size:2'],
]);

$save = function () {
    $this->validate();

    ArcherProfile::updateOrCreate(
        ['user_id' => Auth::id()],
        [
            'gender'            => $this->gender ?: null,
            'birth_date'        => $this->birth_date ?: null,
            'handedness'        => $this->handedness ?: null,
            'para_archer'       => (bool) $this->para_archer,
            'uses_wheelchair'   => (bool) $this->uses_wheelchair,
            'club_affiliation'  => $this->club_affiliation ?: null,
            'us_archery_number' => $this->us_archery_number ?: null,
            'country'           => $this->country ?: null,
            'completed_at'      => now(),
        ]
    );

    return $this->redirect(route('dashboard'), navigate: true);
};
?>

{{-- SINGLE ROOT, no <flux:main>, aligned with your app shell --}}
<div class="container mx-auto px-4 py-8">
    <div class="mx-auto max-w-3xl rounded-2xl border border-zinc-200/60 bg-white p-6 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
        <flux:heading size="xl">Welcome! Let’s set up your profile</flux:heading>
        <flux:text class="mt-2">This helps personalize equipment, training, and stats.</flux:text>

        <form wire:submit.prevent="save" class="mt-8 space-y-8">
            {{-- grid keeps the two-column rhythm on md+ --}}
            <div class="grid gap-6 md:grid-cols-2">

                {{-- Gender (dropdown) --}}
                <flux:field class="md:col-span-1">
                    <flux:label for="gender" class="mb-1">Gender</flux:label>
                    <flux:select id="gender" wire:model="gender">
                        <option value="">Select…</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="nonbinary">Non-binary</option>
                        <option value="other">Other</option>
                        <option value="prefer_not_to_say">Prefer not to say</option>
                    </flux:select>
                    @error('gender') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </flux:field>

                {{-- Birth date (tighter label spacing) --}}
                <flux:field class="md:col-span-1">
                    <flux:label for="birth_date" class="mb-1">Birth Date</flux:label>
                    <flux:input id="birth_date" type="date" wire:model="birth_date" class="max-w-sm" />
                    @error('birth_date') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </flux:field>

            {{-- Handedness + Country in one row --}}
            <div class="md:col-span-2 flex flex-col md:flex-row md:space-x-6 space-y-4 md:space-y-0">
                {{-- Handedness dropdown --}}
                <div class="flex-1">
                    <flux:label for="handedness" class="mb-1">Handedness</flux:label>
                    <flux:select id="handedness" wire:model="handedness" class="w-full">
                        <option value="">Select…</option>
                        <option value="right">Right-handed</option>
                        <option value="left">Left-handed</option>
                    </flux:select>
                    @error('handedness') 
                        <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> 
                    @enderror
                </div>

                {{-- Country dropdown --}}
                <div class="flex-1">
                    @php($countries = collect(config('countries.list'))->sort()->all())
                    <flux:label for="country" class="mb-1">Country</flux:label>
                    <flux:select id="country" wire:model="country" class="w-full">
                        <option value="">{{ __('Select…') }}</option>
                        @foreach($countries as $code => $name)
                            <option value="{{ $code }}">{{ $name }}</option>
                        @endforeach
                    </flux:select>

                    @error('country') 
                        <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> 
                    @enderror
                </div>
            </div>

            {{-- Para archer + Wheelchair in one row --}}
            <div class="md:col-span-2 flex flex-col md:flex-row md:items-center md:space-x-6 space-y-4 md:space-y-0">
                {{-- Para archer toggle --}}
                <label class="flex items-center space-x-2">
                    <flux:switch id="para_archer" wire:model="para_archer" />
                    <span>Para archer</span>
                </label>

                {{-- Uses wheelchair toggle --}}
                <label class="flex items-center space-x-2">
                    <flux:switch id="uses_wheelchair" wire:model="uses_wheelchair" />
                    <span>Uses A Wheelchair</span>
                </label>
            </div>



                {{-- Club affiliation --}}
                <flux:field class="md:col-span-2">
                    <flux:label for="club_affiliation" class="mb-1">Club Affiliation</flux:label>
                    <flux:input id="club_affiliation" type="text" wire:model="club_affiliation" placeholder="Your club / team" />
                    @error('club_affiliation') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </flux:field>

                {{-- USA Archery number --}}
                <flux:field class="md:col-span-2">
                    <flux:label for="us_archery_number" class="mb-1">USA Archery Number</flux:label>
                    <flux:input id="us_archery_number" type="text" wire:model="us_archery_number" placeholder="(optional)" />
                    @error('us_archery_number') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </flux:field>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <flux:text size="sm" class="opacity-70">You can update this anytime in Settings.</flux:text>
                <flux:button variant="primary" type="submit" icon="check">Save</flux:button>
            </div>
        </form>
    </div>
</div>
