{{-- resources/views/livewire/pages/corporate-onboarding.blade.php --}}
<?php
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;

use function Livewire\Volt\layout;
use function Livewire\Volt\rules;
use function Livewire\Volt\state;
use function Livewire\Volt\title;
use function Livewire\Volt\uses;

uses(WithFileUploads::class);

layout('components.layouts.app');
title('Corporate Onboarding');

$prefill = function () {
    $user = Auth::user();

    return optional($user?->company ?? null);
};

state([
    // Basics
    'company_name' => fn () => $prefill()->company_name,
    'legal_name' => fn () => $prefill()->legal_name,
    'website' => fn () => $prefill()->website,
    'support_email' => fn () => $prefill()->support_email,
    'phone' => fn () => $prefill()->phone,

    // Address
    'address_line1' => fn () => $prefill()->address_line1,
    'address_line2' => fn () => $prefill()->address_line2,
    'city' => fn () => $prefill()->city,
    'state_region' => fn () => $prefill()->state_region,
    'postal_code' => fn () => $prefill()->postal_code,
    'country' => fn () => $prefill()->country,

    // Extra
    'industry' => fn () => $prefill()->industry,

    // Logo
    'logo' => null,                         // temporary uploaded file
    'logo_path' => fn () => $prefill()->logo_path, // stored relative path (e.g., company-logos/abc.png)
]);

rules(fn () => [
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
    'country' => ['required', 'size:2'],

    'industry' => ['nullable', 'string', 'max:120'],

    // Logo upload: 2MB, common formats incl. SVG
    'logo' => ['nullable', 'file', 'max:2048', 'mimes:png,jpg,jpeg,webp,svg,svg+xml'],
]);

$save = function () {
    $this->validate();
    $user = Auth::user();

    $company = $user->company ?? new Company([
        'owner_user_id' => $user->id,
    ]);

    // Handle logo upload if provided
    $logoPath = $company->logo_path; // keep existing if no new upload
    if ($this->logo) {
        // Store publicly and keep relative path (e.g., company-logos/xxxx.png)
        $stored = $this->logo->store('company-logos', 'public');
        $logoPath = $stored;
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
        'country' => $this->country,

        'industry' => $this->industry ?: null,
        'logo_path' => $logoPath,
        'completed_at' => now(),
    ])->save();

    // Link user to company
    if ($user->company_id !== $company->id) {
        $user->company_id = $company->id;
        $user->save();
    }

    // Go back to intended corporate page if we saved one
    $target = session()->pull('intended_url') ?? route('dashboard');

    return $this->redirect($target, navigate: true);
};
?>

<div class="container mx-auto px-4 py-8">
  <div class="mx-auto max-w-4xl rounded-2xl border border-zinc-200/60 bg-white p-6 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
    <flux:heading size="xl">Welcome! Let’s set up your company</flux:heading>
    <flux:text class="mt-2">We’ll use this to prefill registrations, billing, and references across the site.</flux:text>

    <form wire:submit.prevent="save" class="mt-8 space-y-8">
      <div class="grid gap-6 md:grid-cols-2">
        <flux:field>
          <flux:label for="company_name" class="mb-1">Company Name</flux:label>
          <flux:input id="company_name" type="text" wire:model="company_name" />
          @error('company_name') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
        </flux:field>

        <flux:field>
          <flux:label for="legal_name" class="mb-1">Legal Name (optional)</flux:label>
          <flux:input id="legal_name" type="text" wire:model="legal_name" />
          @error('legal_name') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
        </flux:field>

        <flux:field>
          <flux:label for="website" class="mb-1">Website</flux:label>
          <flux:input id="website" type="url" wire:model="website" placeholder="https://example.com" />
          @error('website') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
        </flux:field>

        <flux:field>
          <flux:label for="support_email" class="mb-1">Support/Contact Email</flux:label>
          <flux:input id="support_email" type="email" wire:model="support_email" />
          @error('support_email') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
        </flux:field>

        <flux:field>
          <flux:label for="phone" class="mb-1">Phone</flux:label>
          <flux:input id="phone" type="text" wire:model="phone" />
          @error('phone') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
        </flux:field>

        <div class="md:col-span-2 grid gap-6 md:grid-cols-2">
          <flux:field class="md:col-span-2">
            <flux:label for="address_line1" class="mb-1">Address Line 1</flux:label>
            <flux:input id="address_line1" type="text" wire:model="address_line1" />
            @error('address_line1') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
          </flux:field>

          <flux:field>
            <flux:label for="address_line2" class="mb-1">Address Line 2</flux:label>
            <flux:input id="address_line2" type="text" wire:model="address_line2" />
            @error('address_line2') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
          </flux:field>

          <flux:field>
            <flux:label for="city" class="mb-1">City</flux:label>
            <flux:input id="city" type="text" wire:model="city" />
            @error('city') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
          </flux:field>

          <flux:field>
            <flux:label for="state_region" class="mb-1">State/Region</flux:label>
            <flux:input id="state_region" type="text" wire:model="state_region" />
            @error('state_region') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
          </flux:field>

          <flux:field>
            <flux:label for="postal_code" class="mb-1">Postal Code</flux:label>
            <flux:input id="postal_code" type="text" wire:model="postal_code" />
            @error('postal_code') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
          </flux:field>

          <flux:field>
            <flux:label for="country" class="mb-1">Country</flux:label>
            @php($countries = collect(config('countries.list'))->sort()->all())
            <flux:select id="country" wire:model="country">
              <option value="">Select…</option>
              @foreach($countries as $code => $name)
                <option value="{{ $code }}">{{ $name }}</option>
              @endforeach
            </flux:select>
            @error('country') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
          </flux:field>

            <flux:field>
                <flux:label for="industry" class="mb-1">Industry</flux:label>
                <flux:input id="industry" type="text" wire:model="industry" placeholder="e.g., Sporting Goods" />
                @error('industry') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
            </flux:field>
        </div>

        {{-- Logo uploader --}}
        <div class="md:col-span-2">
          <flux:label class="mb-2">Company Logo (optional)</flux:label>
          <div class="flex items-center gap-4">
            @if($logo)
              {{-- Temporary preview for newly selected file --}}
              <img src="{{ $logo->temporaryUrl() }}" alt="Logo preview" class="h-14 w-14 rounded-lg object-contain border border-zinc-200 dark:border-zinc-700" />
            @elseif($logo_path)
              {{-- Existing stored logo --}}
              <img src="{{ Storage::url($logo_path) }}" alt="Logo" class="h-14 w-14 rounded-lg object-contain border border-zinc-200 dark:border-zinc-700" />
            @else
              <div class="h-14 w-14 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600 flex items-center justify-center text-xs opacity-70">No logo</div>
            @endif>

            <flux:input type="file" wire:model="logo" accept=".png,.jpg,.jpeg,.webp,.svg" />
          </div>
          @error('logo') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror

          {{-- Optional: tiny helper text --}}
          <flux:text size="sm" class="opacity-70 mt-1">PNG/JPG/WebP/SVG, up to 2 MB.</flux:text>
        </div>
      </div>

      <div class="mt-6 flex items-center justify-between">
        <flux:text size="sm" class="opacity-70">You can edit this later in Settings → Company.</flux:text>
        <flux:button variant="primary" type="submit" icon="check">Save</flux:button>
      </div>
    </form>
  </div>
</div>
