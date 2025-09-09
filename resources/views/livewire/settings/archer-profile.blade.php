<?php

use App\Models\ArcherProfile;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $gender = '';
    public string $birth_date = '';
    public string $handedness = '';
    public bool $para_archer = false;
    public bool $uses_wheelchair = false;
    public string $club_affiliation = '';
    public string $us_archery_number = '';
    public string $country = '';

    /**
     * Mount the component: hydrate from (or create) the user's Archer Profile.
     */
    public function mount(): void
    {
        $user = Auth::user();
        $profile = $user->archerProfile()->firstOrCreate(['user_id' => $user->id]);

        $this->gender            = (string) ($profile->gender ?? '');
        $this->birth_date        = $profile->birth_date ? $profile->birth_date->format('Y-m-d') : '';
        $this->handedness        = (string) ($profile->handedness ?? '');
        $this->para_archer       = (bool)   ($profile->para_archer ?? false);
        $this->uses_wheelchair   = (bool)   ($profile->uses_wheelchair ?? false);
        $this->club_affiliation  = (string) ($profile->club_affiliation ?? '');
        $this->us_archery_number = (string) ($profile->us_archery_number ?? '');
        $this->country           = (string) ($profile->country ?? '');
    }

    /**
     * Save updates to the Archer Profile.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'gender'            => ['required','in:male,female,nonbinary,other,prefer_not_to_say'],
            'birth_date'        => ['nullable','date','before:today'],
            'handedness'        => ['required','in:right,left'],
            'para_archer'       => ['boolean'],
            'uses_wheelchair'   => ['boolean'],
            'club_affiliation'  => ['nullable','string','max:120'],
            'us_archery_number' => ['nullable','string','max:30'],
            'country'           => ['nullable','size:2'], // ISO-3166 alpha-2
        ]);

        ArcherProfile::updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'gender'            => $validated['gender'] ?: null,
                'birth_date'        => $validated['birth_date'] ?: null,
                'handedness'        => $validated['handedness'] ?: null,
                'para_archer'       => (bool) $validated['para_archer'],
                'uses_wheelchair'   => (bool) $validated['uses_wheelchair'],
                'club_affiliation'  => $validated['club_affiliation'] ?: null,
                'us_archery_number' => $validated['us_archery_number'] ?: null,
                'country'           => $validated['country'] ?: null,
                // Note: we do NOT touch completed_at here (onboarding controls that).
            ]
        );

        $this->dispatch('archer-profile-updated');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Archer Profile')" :subheading="__('Update your archer details and preferences')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <div class="grid gap-6 md:grid-cols-2">
                {{-- Gender --}}
                <div>
                    <flux:label for="gender" class="mb-1">{{ __('Gender') }}</flux:label>
                    <flux:select id="gender" wire:model="gender" class="w-full">
                        <option value="">{{ __('Select…') }}</option>
                        <option value="male">{{ __('Male') }}</option>
                        <option value="female">{{ __('Female') }}</option>
                        <option value="nonbinary">{{ __('Non-binary') }}</option>
                        <option value="other">{{ __('Other') }}</option>
                        <option value="prefer_not_to_say">{{ __('Prefer not to say') }}</option>
                    </flux:select>
                    @error('gender') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </div>

                {{-- Birth date --}}
                <div>
                    <flux:label for="birth_date" class="mb-1">{{ __('Birth Date') }}</flux:label>
                    <flux:input id="birth_date" type="date" wire:model="birth_date" class="max-w-sm" />
                    @error('birth_date') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </div>

                {{-- Handedness + Country (same row, responsive) --}}
                <div class="md:col-span-2 flex flex-col md:flex-row md:space-x-6 space-y-4 md:space-y-0">
                    <div class="flex-1">
                        <flux:label for="handedness" class="mb-1">{{ __('Handedness') }}</flux:label>
                        <flux:select id="handedness" wire:model="handedness" class="w-full">
                            <option value="">{{ __('Select…') }}</option>
                            <option value="right">{{ __('Right-handed') }}</option>
                            <option value="left">{{ __('Left-handed') }}</option>
                        </flux:select>
                        @error('handedness') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                    </div>

                    <div class="flex-1">
                        @php($countries = collect(config('countries.list'))->sort()->all())
                        <flux:label for="country" class="mb-1">{{ __('Country') }}</flux:label>
                        <flux:select id="country" wire:model="country" class="w-full">
                            <option value="">{{ __('Select…') }}</option>
                            @foreach($countries as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </flux:select>
                        @error('country') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                    </div>
                </div>

                {{-- Para archer + Uses wheelchair (same row, responsive) --}}
                <div class="md:col-span-2 flex flex-col md:flex-row md:items-center md:space-x-6 space-y-4 md:space-y-0">
                    <label class="flex items-center space-x-2">
                        <flux:switch id="para_archer" wire:model="para_archer" />
                        <span>{{ __('Para Archer') }}</span>
                    </label>

                    <label class="flex items-center space-x-2">
                        <flux:switch id="uses_wheelchair" wire:model="uses_wheelchair" />
                        <span>{{ __('Uses A Wheelchair') }}</span>
                    </label>
                </div>

                {{-- Club affiliation --}}
                <div class="md:col-span-2">
                    <flux:label for="club_affiliation" class="mb-1">{{ __('Club Affiliation') }}</flux:label>
                    <flux:input id="club_affiliation" type="text" wire:model="club_affiliation" placeholder="{{ __('Your club / team') }}" />
                    @error('club_affiliation') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </div>

                {{-- USA Archery number --}}
                <div class="md:col-span-2">
                    <flux:label for="us_archery_number" class="mb-1">{{ __('USA Archery Number') }}</flux:label>
                    <flux:input id="us_archery_number" type="text" wire:model="us_archery_number" placeholder="{{ __('(optional)') }}" />
                    @error('us_archery_number') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </div>
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="archer-profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        {{-- Keep or add other settings blocks as needed --}}
        {{-- <livewire:settings.delete-user-form /> --}}
    </x-settings.layout>
</section>
