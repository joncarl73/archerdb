{{-- resources/views/livewire/settings/company.blade.php --}}
<?php

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    // Form state
    public string $company_name = '';

    public string $legal_name = '';

    public string $website = '';

    public string $support_email = '';

    public string $phone = '';

    public string $address_line1 = '';

    public string $address_line2 = '';

    public string $city = '';

    public string $state_region = '';

    public string $postal_code = '';

    public string $country = '';

    public string $industry = '';

    // Logo handling
    public $logo;          // temporary upload

    public ?string $logo_path = null;

    public function mount(): void
    {
        $user = Auth::user();

        // Ensure a company row exists for corporate users
        $company = $user->company ?? ($user->role === 'corporate'
            ? Company::create([
                'owner_user_id' => $user->id,
                'company_name' => $user->name, // sensible default
            ])
            : null);

        if ($company && $user->company_id !== $company->id) {
            $user->company_id = $company->id;
            $user->save();
        }

        $company = $user->company; // reload

        // Hydrate form if present
        if ($company) {
            $this->company_name = (string) ($company->company_name ?? '');
            $this->legal_name = (string) ($company->legal_name ?? '');
            $this->website = (string) ($company->website ?? '');
            $this->support_email = (string) ($company->support_email ?? '');
            $this->phone = (string) ($company->phone ?? '');
            $this->address_line1 = (string) ($company->address_line1 ?? '');
            $this->address_line2 = (string) ($company->address_line2 ?? '');
            $this->city = (string) ($company->city ?? '');
            $this->state_region = (string) ($company->state_region ?? '');
            $this->postal_code = (string) ($company->postal_code ?? '');
            $this->country = (string) ($company->country ?? '');
            $this->industry = (string) ($company->industry ?? '');
            $this->logo_path = $company->logo_path;
        }
    }

    public function save(): void
    {
        $this->validate([
            'company_name' => ['required', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'website' => ['nullable', 'url', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],

            'address_line1' => ['nullable', 'string', 'max:160'],
            'address_line2' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:120'],
            'state_region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'size:2'],

            'industry' => ['nullable', 'string', 'max:120'],

            // Logo upload: 2MB max
            'logo' => ['nullable', 'file', 'max:2048', 'mimes:png,jpg,jpeg,webp,svg,svg+xml'],
        ]);

        $user = Auth::user();

        if (! in_array($user->role, ['corporate', 'administrator'], true)) {
            abort(403);
        }

        $company = $user->company ?? Company::create([
            'owner_user_id' => $user->id,
            'company_name' => $this->company_name,
        ]);

        // Process logo if a new file is uploaded
        $logoPath = $company->logo_path;
        if ($this->logo) {
            $logoPath = $this->logo->store('company-logos', 'public'); // storage/app/public/company-logos/...
        }

        $company->fill([
            'company_name' => $this->company_name,
            'legal_name' => $this->legal_name ?: null,
            'website' => $this->website ?: null,
            'support_email' => $this->support_email ?: null,
            'phone' => $this->phone ?: null,
            'address_line1' => $this->address_line1 ?: null,
            'address_line2' => $this->address_line2 ?: null,
            'city' => $this->city ?: null,
            'state_region' => $this->state_region ?: null,
            'postal_code' => $this->postal_code ?: null,
            'country' => $this->country ?: null,
            'industry' => $this->industry ?: null,
            'logo_path' => $logoPath,
            // Do not touch completed_at here; onboarding owns that.
        ])->save();

        // Keep local state in sync
        $this->logo_path = $company->logo_path;

        $this->dispatch('company-updated');
    }

    // Optional: allow removing the existing logo
    public function removeLogo(): void
    {
        $user = Auth::user();
        $company = $user->company;
        if (! $company) {
            return;
        }

        $company->logo_path = null;
        $company->save();

        $this->logo = null;
        $this->logo_path = null;
        $this->dispatch('company-updated');
    }
};
?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Company')" :subheading="__('Manage your organization details and branding')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <flux:label for="company_name" class="mb-1">{{ __('Company Name') }}</flux:label>
                    <flux:input id="company_name" type="text" wire:model="company_name" />
                    @error('company_name') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </div>

                <div>
                    <flux:label for="legal_name" class="mb-1">{{ __('Legal Name (optional)') }}</flux:label>
                    <flux:input id="legal_name" type="text" wire:model="legal_name" />
                    @error('legal_name') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </div>

                <div>
                    <flux:label for="website" class="mb-1">{{ __('Website') }}</flux:label>
                    <flux:input id="website" type="url" wire:model="website" placeholder="https://example.com" />
                    @error('website') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </div>

                <div>
                    <flux:label for="support_email" class="mb-1">{{ __('Support/Contact Email') }}</flux:label>
                    <flux:input id="support_email" type="email" wire:model="support_email" />
                    @error('support_email') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </div>

                <div>
                    <flux:label for="phone" class="mb-1">{{ __('Phone') }}</flux:label>
                    <flux:input id="phone" type="text" wire:model="phone" />
                    @error('phone') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </div>

                <div class="md:col-span-2 grid gap-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <flux:label for="address_line1" class="mb-1">{{ __('Address Line 1') }}</flux:label>
                        <flux:input id="address_line1" type="text" wire:model="address_line1" />
                        @error('address_line1') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                    </div>

                    <div>
                        <flux:label for="address_line2" class="mb-1">{{ __('Address Line 2') }}</flux:label>
                        <flux:input id="address_line2" type="text" wire:model="address_line2" />
                        @error('address_line2') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                    </div>

                    <div>
                        <flux:label for="city" class="mb-1">{{ __('City') }}</flux:label>
                        <flux:input id="city" type="text" wire:model="city" />
                        @error('city') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                    </div>

                    <div>
                        <flux:label for="state_region" class="mb-1">{{ __('State/Region') }}</flux:label>
                        <flux:input id="state_region" type="text" wire:model="state_region" />
                        @error('state_region') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                    </div>

                    <div>
                        <flux:label for="postal_code" class="mb-1">{{ __('Postal Code') }}</flux:label>
                        <flux:input id="postal_code" type="text" wire:model="postal_code" />
                        @error('postal_code') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                    </div>

                    <div>
                        @php($countries = collect(config('countries.list'))->sort()->all())
                        <flux:label for="country" class="mb-1">{{ __('Country') }}</flux:label>
                        <flux:select id="country" wire:model="country">
                            <option value="">{{ __('Select…') }}</option>
                            @foreach($countries as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </flux:select>
                        @error('country') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                    </div>
                    <div>
                        <flux:label for="industry" class="mb-1">{{ __('Industry') }}</flux:label>
                        <flux:input id="industry" type="text" wire:model="industry" placeholder="{{ __('e.g., Sporting Goods') }}" />
                        @error('industry') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                    </div>
                </div>
            </div>

            {{-- Logo uploader --}}
            <div class="space-y-3">
                <flux:label class="mb-2">{{ __('Company Logo') }}</flux:label>

                <div class="flex items-center gap-4">
                    @if($logo)
                        <img src="{{ $logo->temporaryUrl() }}" alt="Logo preview" class="h-14 w-14 rounded-lg object-contain border border-zinc-200 dark:border-zinc-700" />
                    @elseif($logo_path)
                        <img src="{{ Storage::url($logo_path) }}" alt="Logo" class="h-14 w-14 rounded-lg object-contain border border-zinc-200 dark:border-zinc-700" />
                    @else
                        <div class="h-14 w-14 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600 flex items-center justify-center text-xs opacity-70">
                            {{ __('No logo') }}
                        </div>
                    @endif

                    <flux:input type="file" wire:model="logo" accept=".png,.jpg,.jpeg,.webp,.svg" />
                    @if($logo_path)
                        <flux:button type="button" variant="ghost" icon="trash" wire:click="removeLogo">{{ __('Remove') }}</flux:button>
                    @endif
                </div>

                <flux:text size="sm" class="opacity-70">{{ __('PNG/JPG/WebP/SVG, up to 2 MB.') }}</flux:text>
                @error('logo') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" class="w-full md:w-auto">{{ __('Save') }}</flux:button>

                <x-action-message class="me-3" on="company-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
