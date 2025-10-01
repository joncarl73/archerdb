<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    // ---- Existing fields ----
    public string $name = '';

    public string $email = '';

    // ---- Avatar upload ----
    // NOTE: don't type-hint to avoid needing imports here. Livewire will handle it.
    public $avatar = null; // \Livewire\Features\SupportFileUploads\TemporaryUploadedFile

    // Use the Livewire uploads trait (fully-qualified to avoid top-of-file imports)
    use \Livewire\WithFileUploads;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Compute the current avatar URL (or null if none).
     */
    public function getAvatarUrlProperty(): ?string
    {
        $userId = Auth::id();
        $path = "avatars/{$userId}.webp";

        return Storage::disk('public')->exists($path) ? Storage::url($path) : null;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Upload/replace avatar (image -> resized -> webp).
     * Smallest safe size: max 512px, webp quality ~75.
     */
    public function uploadAvatar(): void
    {
        $this->validate([
            'avatar' => [
                'required',
                'image',       // jpg, png, webp, gif, etc.
                'max:5120',    // 5MB
                'mimes:jpg,jpeg,png,webp,avif',
            ],
        ]);

        $tmpPath = $this->avatar->getRealPath();
        if (! $tmpPath || ! file_exists($tmpPath)) {
            $this->addError('avatar', 'Upload failed. Please try again.');

            return;
        }

        // Read file and create an image resource (GD), fallback to original if decode fails
        $raw = @file_get_contents($tmpPath);
        if ($raw === false) {
            $this->addError('avatar', 'Could not read uploaded file.');

            return;
        }

        $src = @imagecreatefromstring($raw);
        if (! $src) {
            // If the uploaded file is already a valid web image but GD can’t decode (rare),
            // simply store it as-is (and we’ll attempt to transcode next time).
            Storage::disk('public')->putFileAs('avatars', $this->avatar, Auth::id().'.webp');
            $this->avatar = null;
            $this->dispatch('profile-updated');

            return;
        }

        // Original dimensions
        $ow = imagesx($src);
        $oh = imagesy($src);

        // Target: max side 512px, keep aspect ratio
        $maxSide = 512;
        $scale = min(1, $maxSide / max($ow, $oh));
        $tw = max(1, (int) floor($ow * $scale));
        $th = max(1, (int) floor($oh * $scale));

        $dst = imagecreatetruecolor($tw, $th);

        // For PNGs with transparency, keep alpha while resampling
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $ow, $oh);

        // Encode to WEBP (quality 75 for good compression)
        ob_start();
        // If imagewebp returns false (older GD), fallback to JPEG with quality 75
        $ok = function_exists('imagewebp') ? imagewebp($dst, null, 75) : false;
        if (! $ok) {
            $ok = imagejpeg($dst, null, 75);
        }
        $binary = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        if ($binary === false || ! $ok) {
            $this->addError('avatar', 'Could not process image.');

            return;
        }

        // Store as .webp if we encoded webp, else .jpg fallback
        $ext = (function_exists('imagewebp')) ? 'webp' : 'jpg';
        $path = 'avatars/'.Auth::id().".{$ext}";

        // Clean up any old variant to save space
        Storage::disk('public')->delete(['avatars/'.Auth::id().'.webp', 'avatars/'.Auth::id().'.jpg']);

        Storage::disk('public')->put($path, $binary, 'public');

        // Reset upload and notify UI
        $this->avatar = null;
        $this->dispatch('profile-updated');
    }

    /**
     * Delete the current avatar (frees storage).
     */
    public function deleteAvatar(): void
    {
        Storage::disk('public')->delete([
            'avatars/'.Auth::id().'.webp',
            'avatars/'.Auth::id().'.jpg',
        ]);

        $this->dispatch('profile-updated');
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();
        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name, email, and avatar')">
        {{-- Avatar uploader --}}
        <div class="my-6 w-full">
            <div class="flex items-start gap-4">
                <div class="relative">
                    {{-- Preview current or new avatar --}}
                    @php
                        $currentAvatar = $this->avatar_url; // from getAvatarUrlProperty
                    @endphp

                    @if($this->avatar)
                        {{-- Temporary preview of the newly-selected file --}}
                        <img src="{{ $this->avatar->temporaryUrl() }}"
                             alt="Avatar preview"
                             class="h-20 w-20 rounded-full object-cover ring-1 ring-black/5" />
                    @elseif($currentAvatar)
                        <img src="{{ $currentAvatar }}"
                             alt="{{ auth()->user()->name }}"
                             class="h-20 w-20 rounded-full object-cover ring-1 ring-black/5" />
                    @else
                        <div class="flex h-20 w-20 items-center justify-center rounded-full bg-neutral-200 text-neutral-600 ring-1 ring-black/5 dark:bg-neutral-800 dark:text-neutral-300">
                            <span class="text-lg font-semibold">
                                {{ \Illuminate\Support\Str::of(auth()->user()->name)->replaceMatches('/[^A-Za-z ]/', '')->trim()->explode(' ')->map(fn($p)=>\Illuminate\Support\Str::substr($p,0,1))->take(2)->join('') ?: 'U' }}
                            </span>
                        </div>
                    @endif
                </div>

                <div class="flex-1">
                    <form wire:submit.prevent="uploadAvatar" class="space-y-3">
                        <flux:input
                            type="file"
                            accept="image/*"
                            wire:model.live="avatar"
                            :label="__('Avatar')"
                        />

                        @error('avatar')
                            <flux:text class="!text-red-600 !dark:text-red-400 text-sm">{{ $message }}</flux:text>
                        @enderror

                        <div class="flex items-center gap-3">
                            <flux:button variant="primary" type="submit">
                                {{ __('Upload') }}
                            </flux:button>

                            @if($currentAvatar)
                                <flux:button variant="ghost" type="button" wire:click="deleteAvatar">
                                    {{ __('Remove') }}
                                </flux:button>
                            @endif
                        </div>

                        <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                            {{ __('Max 5 MB. We’ll resize to 512px and store as WebP/JPEG to save space.') }}
                        </flux:text>
                    </form>
                </div>
            </div>
        </div>

        {{-- Name / Email form --}}
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        <livewire:settings.delete-user-form />
    </x-settings.layout>
</section>
