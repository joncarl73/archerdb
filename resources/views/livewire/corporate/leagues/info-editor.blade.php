<?php

use App\Models\League;
use App\Models\LeagueInfo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;

new class extends Component
{
    use \Livewire\WithFileUploads;

    public League $league;

    public ?LeagueInfo $info = null;

    public string $title = '';

    public ?string $registration_url = null; // NEW

    public string $content_html = '';

    public bool $is_published = false;

    public ?string $registration_start_date = null;

    public ?string $registration_end_date = null;

    public $banner; // TemporaryUploadedFile

    public ?string $banner_url = null;

    public function mount(League $league): void
    {
        Gate::authorize('update', $league);

        $this->league = $league->load('info');
        $this->info = $league->info ?: new LeagueInfo(['league_id' => $league->id]);

        $this->title = (string) ($this->info->title ?? $league->title);
        $this->registration_url = $this->info->registration_url ?? null; // NEW
        $this->content_html = (string) ($this->info->content_html ?? '');
        $this->is_published = (bool) ($this->info->is_published ?? false);

        if ($this->info?->banner_path && Storage::disk('public')->exists($this->info->banner_path)) {
            $this->banner_url = Storage::url($this->info->banner_path);
        }
    }

    public function save(): void
    {
        Gate::authorize('update', $this->league);

        $this->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'registration_url' => ['nullable', 'url', 'max:255'], // NEW
            'content_html' => ['nullable', 'string'],
            'is_published' => ['boolean'],
            'registration_start_date' => ['nullable', 'date'],
            'registration_end_date' => ['nullable', 'date'],
        ]);

        $info = $this->league->info ?: new LeagueInfo(['league_id' => $this->league->id]);

        $info->fill([
            'title' => $this->title ?: $this->league->title,
            'registration_url' => $this->registration_url, // NEW
            'content_html' => $this->content_html,
            'is_published' => $this->is_published,
        ]);

        $info->save();
        $this->info = $info;

        $this->league->registration_start_date = $this->registration_start_date ?: null;
        $this->league->registration_end_date = $this->registration_end_date ?: null;
        $this->league->save();

        $this->dispatch('toast', type: 'success', message: 'League info saved.');
    }

    public function uploadBanner(): void
    {
        Gate::authorize('update', $this->league);

        $this->validate([
            'banner' => ['required', 'image', 'max:8192', 'mimes:jpg,jpeg,png,webp,avif'],
        ]);

        $tmp = $this->banner->getRealPath();
        if (! $tmp || ! file_exists($tmp)) {
            $this->addError('banner', 'Upload failed.');

            return;
        }

        $raw = @file_get_contents($tmp);
        $src = $raw ? @imagecreatefromstring($raw) : null;
        if (! $src) {
            $path = "leagues/{$this->league->id}/banner.webp";
            \Storage::disk('public')->putFileAs("leagues/{$this->league->id}", $this->banner, 'banner.webp');
            $this->finalizeBanner($path);

            return;
        }

        $ow = imagesx($src);
        $oh = imagesy($src);
        $maxW = 1600;
        $scale = min(1, $maxW / max(1, $ow));
        $tw = max(1, (int) floor($ow * $scale));
        $th = max(1, (int) floor($oh * $scale));

        $dst = imagecreatetruecolor($tw, $th);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $ow, $oh);

        ob_start();
        $ok = function_exists('imagewebp') ? imagewebp($dst, null, 80) : false;
        if (! $ok) {
            $ok = imagejpeg($dst, null, 80);
        }
        $binary = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        if (! $ok || $binary === false) {
            $this->addError('banner', 'Could not process image.');

            return;
        }

        $ext = function_exists('imagewebp') ? 'webp' : 'jpg';
        $path = "leagues/{$this->league->id}/banner.{$ext}";

        \Storage::disk('public')->delete([
            "leagues/{$this->league->id}/banner.webp",
            "leagues/{$this->league->id}/banner.jpg",
        ]);
        \Storage::disk('public')->put($path, $binary, 'public');

        $this->finalizeBanner($path);
    }

    private function finalizeBanner(string $path): void
    {
        $info = $this->league->info ?: new LeagueInfo(['league_id' => $this->league->id]);
        $info->banner_path = $path;
        $info->save();
        $this->info = $info;
        $this->banner = null;
        $this->banner_url = \Storage::url($path);
        $this->dispatch('toast', type: 'success', message: 'Banner updated.');
    }

    public function removeBanner(): void
    {
        Gate::authorize('update', $this->league);

        if ($this->info?->banner_path) {
            \Storage::disk('public')->delete($this->info->banner_path);
            $this->info->update(['banner_path' => null]);
        }
        $this->banner_url = null;
        $this->dispatch('toast', type: 'success', message: 'Banner removed.');
    }
}; ?>

<section class="w-full">
    <div class="mx-auto max-w-5xl">
        <div class="mb-6">
            <h1 class="text-base font-semibold text-gray-900 dark:text-white">League info: {{ $league->title }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Upload a banner and write a rich info page for your league.</p>
        </div>

        {{-- Banner card --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-neutral-900">
            <div class="p-4 border-b border-gray-200 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Banner</h2>
                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Max ~8 MB. We’ll resize to 1600px wide and store efficiently (WebP/JPEG).</p>
            </div>
            <div class="p-4 flex items-start gap-4">
                <div class="w-56">
                    @if($banner_url)
                        <img src="{{ $banner_url }}" alt="League banner" class="w-full rounded-lg ring-1 ring-black/5" />
                    @else
                        <div class="aspect-[16/9] w-full rounded-lg bg-neutral-100 dark:bg-neutral-800 ring-1 ring-black/5"></div>
                    @endif
                </div>
                <div class="flex-1">
                    <form wire:submit.prevent="uploadBanner" class="space-y-3">
                        <flux:input type="file" accept="image/*" wire:model="banner" :label="__('Upload banner')" />
                        @error('banner') <flux:text class="!text-red-600 !dark:text-red-400 text-sm">{{ $message }}</flux:text> @enderror

                        <div class="flex items-center gap-2">
                            <flux:button type="submit" variant="primary">Save banner</flux:button>
                            @if($banner_url)
                                <flux:button type="button" variant="ghost" wire:click="removeBanner">Remove</flux:button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Content + External URL --}}
        <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-neutral-900">
            <div class="p-4 border-b border-gray-200 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Info content</h2>
            </div>

            <div class="p-4 space-y-4">
                <flux:input wire:model.defer="title" :label="__('Title')" type="text" placeholder="Optional — defaults to league title" />

                {{-- Show remote registration URL if league is OPEN --}}
                @php
                    $isOpenLeague = ($league->type->value ?? $league->type) === 'open';
                @endphp
                @if($isOpenLeague)
                    <flux:input
                        wire:model.defer="registration_url"
                        :label="__('External registration URL')"
                        type="url"
                        placeholder="https://example.com/register"
                    />
                @endif

                {{-- Registration window --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model.defer="registration_start_date"
                        :label="__('Registration opens')"
                        type="date"
                    />
                    <flux:input
                        wire:model.defer="registration_end_date"
                        :label="__('Registration closes')"
                        type="date"
                    />
                </div>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                    These dates control when registration-related UI appears across ArcherDB.
                </p>

                {{-- Quill WYSIWYG (wire:ignore and hidden input sync) --}}
                <div>
                    <flux:label>Content</flux:label>

                    {{-- Initial content payload (always available on first render) --}}
                    <script id="league-info-initial" type="application/json">
                        {!! json_encode($content_html ?? '') !!}
                    </script>

                    <div wire:ignore>
                        <div id="quill-editor"
                            class="rounded-md border border-neutral-300 dark:border-white/10 min-h-[220px]"></div>
                    </div>

                    {{-- Hidden input Livewire binds to; we also seed its value for SSR/hydration --}}
                    <input id="content_html" type="hidden" wire:model="content_html" value="{{ $content_html }}">
                </div>


                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <flux:checkbox wire:model="is_published" />
                        <flux:label class="text-sm">Published</flux:label>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:button variant="primary" wire:click="save">Save</flux:button>
                        <flux:button as="a" href="{{ route('corporate.leagues.show', $league) }}" variant="ghost">Back to league</flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Quill assets (CDN) --}}
    <link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>

    <style>
    /* Rounded borders + subtle backgrounds (light) */
    #quill-editor .ql-toolbar { border-radius: .375rem .375rem 0 0; }
    #quill-editor .ql-container { border-radius: 0 0 .375rem .375rem; min-height: 220px; }
    .ql-snow .ql-toolbar { background-color: #ffffff; border-color: #e5e7eb; }      /* neutral-100/200 */
    .ql-snow .ql-container { background-color: #ffffff; border-color: #e5e7eb; }
    .ql-snow .ql-editor   { color: #0a0a0a; }                                        /* neutral-950-ish */
    .ql-editor::placeholder { color: #9ca3af; }                                      /* neutral-400 */
    .ql-snow a { color: #4f46e5; }                                                    /* indigo-600 */

    /* Dark mode */
    .dark .ql-snow .ql-toolbar { background-color: #0b0f19; border-color: rgba(255,255,255,0.08); }
    .dark .ql-snow .ql-container { background-color: #0b0f19; border-color: rgba(255,255,255,0.08); }
    .dark .ql-snow .ql-editor { color: #e5e7eb; }
    .dark .ql-editor::placeholder { color: #9ca3af; }
    .dark .ql-snow .ql-stroke { stroke: #e5e7eb; }
    .dark .ql-snow .ql-fill { fill: #e5e7eb; }
    .dark .ql-snow a { color: #8b93ff; } /* softer indigo in dark */
    </style>

    <script>
    (function () {
    function initialHTML() {
        // Prefer the JSON script (always present), fallback to hidden input
        const script = document.getElementById('league-info-initial');
        if (script) {
        try { return JSON.parse(script.textContent || ''); } catch (_) {}
        }
        const hidden = document.getElementById('content_html');
        return hidden ? hidden.value || '' : '';
    }

    function initQuillOnce() {
        if (!window.Quill) { setTimeout(initQuillOnce, 50); return; }

        const el = document.getElementById('quill-editor');
        const hidden = document.getElementById('content_html');
        if (!el || !hidden || el.__quillInited) return;

        const quill = new Quill(el, {
        theme: 'snow',
        placeholder: 'Write your league info...',
        modules: {
            toolbar: [
            [{ header: [2,3,false] }],
            ['bold','italic','underline','strike'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            [{ align: [] }],
            ['link','clean']
            ]
        }
        });

        el.__quillInited = true;
        window.__leagueQuill = quill;

        // Load existing HTML content into Quill
        const html = initialHTML();
        if (html && typeof quill.clipboard?.dangerouslyPasteHTML === 'function') {
        quill.clipboard.dangerouslyPasteHTML(html);
        } else if (html) {
        // minimal fallback
        quill.root.innerHTML = html;
        }

        // Keep hidden input in sync with editor changes
        quill.on('text-change', function () {
        const current = el.querySelector('.ql-editor')?.innerHTML ?? '';
        if (hidden.value !== current) {
            hidden.value = current;
            hidden.dispatchEvent(new Event('input'));
        }
        });
    }

    // Run when page is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initQuillOnce);
    } else {
        initQuillOnce();
    }

    // Re-run after Livewire SPA navigations or component refreshes
    document.addEventListener('livewire:load', () => setTimeout(initQuillOnce, 0));
    document.addEventListener('livewire:navigated', () => setTimeout(initQuillOnce, 0));
    })();
    </script>


</section>
