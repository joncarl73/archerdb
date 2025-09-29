@props(['league' => null, 'kiosk' => false, 'forceTheme' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    @include('partials.head')

    {{-- Theme bootstrap: honor forced theme first, then stored; default = light --}}
    <script>
      (function () {
        try {
          var forced = @json($forceTheme); // 'light' | 'dark' | null
          var stored = localStorage.getItem('theme');
          var theme  = forced || stored || 'light'; // default light
          document.documentElement.classList.toggle('dark', theme === 'dark');
          document.documentElement.dataset.theme = theme;
        } catch (_) {}
      })();
    </script>
  </head>
  <body class="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">
    @unless($kiosk)
      <flux:header
        container
        class="h-14 flex items-center gap-3 border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"
      >
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        <a href="{{ route('home') }}" class="ms-2 me-5 flex items-center gap-2 lg:ms-0">
          <x-app-logo class="shrink-0" />
          @isset($league)
            <span class="hidden sm:inline text-sm font-semibold">
              {{ $league->title }}
            </span>
          @endisset
        </a>
        <flux:navbar class="-mb-px max-lg:hidden" />
        <flux:spacer />

        <flux:navbar class="me-1.5 space-x-0.5 py-0!">
          <button
            id="themeToggle"
            type="button"
            class="h-10 w-10 inline-flex items-center justify-center rounded-md inset-ring inset-ring-zinc-300 hover:bg-zinc-50
                   dark:inset-ring-zinc-700 dark:hover:bg-zinc-800"
            aria-label="Toggle theme"
            title="Toggle theme"
          >
            {{-- Moon (light mode) --}}
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                 fill="currentColor" class="size-5 dark:hidden">
              <path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 1 0 9.79 9.79Z"/>
            </svg>
            {{-- Sun (dark mode) --}}
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
               fill="currentColor" class="size-5 hidden dark:inline">
            <path d="M12 18a6 6 0 1 0 0-12 6 6 0 0 0 0 12Zm0 4a1 1 0 0 1-1-1v-1a1 1 0 1 1 2 0v1a1 1 0 0 1-1 1Zm0-18a1 1 0 0 1-1-1V2a1 1 0 1 1 2 0v1a1 1 0 0 1-1 1Zm10 9a1 1 0 0 1-1-1h-1a1 1 0 1 1 0-2h1a1 1 0 1 1 2 0 1 1 0 0 1-1 1ZM4 12a1 1 0 0 1-1 1H2a1 1 0 1 1 0-2h1a1 1 0 0 1 1 1Zm13.66 7.66a1 1 0 0 1-1.41 0l-.71-.71a1 1 0 1 1 1.41-1.41l.71.71a1 1 0 0 1 0 1.41Zm-9.9-9.9a1 1 0 0 1-1.41 0l-.71-.71A1 1 0 1 1 6.76 8.63l.71.71a1 1 0 0 1 0 1.41Zm9.9-3.1a1 1 0 0 1 0-1.41l.71-.71A1 1 0 1 1 19.59 6l-.71.71a1 1 0 0 1-1.41 0ZM5.05 19.59a1 1 0 0 1 0-1.41l.71-.71a1 1 0 1 1 1.41 1.41l-.71.71a1 1 0 0 1-1.41 0Z"/>
          </svg>
          </button>
        </flux:navbar>
      </flux:header>

      <flux:sidebar stashable sticky class="lg:hidden border-e border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />
        <a href="{{ route('home') }}" class="ms-1 flex items-center gap-2">
          <x-app-logo />
          @isset($league)
            <span class="text-sm font-semibold">{{ $league->title }}</span>
          @endisset
        </a>
      </flux:sidebar>
    @endunless

    <main class="{{ $kiosk ? '' : 'pt-10' }}">
      {{ $slot }}
    </main>

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('themeToggle');
        if (!btn) return;
        btn.addEventListener('click', function () {
          var isDark = document.documentElement.classList.toggle('dark');
          var theme = isDark ? 'dark' : 'light';
          document.documentElement.dataset.theme = theme;
          try { localStorage.setItem('theme', theme); } catch (_) {}
        });
      });
    </script>

    @fluxScripts
  </body>
</html>
