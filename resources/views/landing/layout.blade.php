{{-- resources/views/landing/layout.blade.php --}}
<!DOCTYPE html>
<html lang="en" class="h-full antialiased">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>@yield('title', 'ArcherDB — Train. Compete. Improve.')</title>
  <link rel="icon" type="image/x-icon" href="{{ asset('/img/favicons/favicon.ico') }}">
  <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('/img/favicons/favicon-16x16.png') }}">
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('/img/favicons/favicon-32x32.png') }}">
  <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('/img/favicons/favicon-48x48.png') }}">
  <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('/img/favicons/favicon-192x192.png') }}">
  <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('/img/favicons/apple-touch-icon.png') }}">

  <link rel="manifest" href="/manifest.webmanifest">
  <meta name="theme-color" content="#4f46e5">

  <link rel="apple-touch-icon" href="/icons/icon-192.png">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="ArcherDB">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  @vite(['resources/css/app.css','resources/js/app.js'])
  @stack('meta')
</head>
<body class="min-h-dvh bg-white text-gray-900 dark:bg-neutral-950 dark:text-neutral-100">
  <div class="flex min-h-dvh flex-col">
    @include('landing.partials.nav')
    <main class="flex-1">
      @yield('content')
    </main>
    @include('landing.partials.footer')
  </div>

  {{-- Optional: tiny theme script (can move to app.js) --}}
  <script>
    (function() {
      const root  = document.documentElement;
      const media = window.matchMedia('(prefers-color-scheme: dark)');

      function apply(theme) {
        const useDark = theme === 'dark' || (theme === 'system' && media.matches);
        root.classList.toggle('dark', useDark);
      }

      // Initial apply: if no saved setting, default to system without saving
      const saved = localStorage.getItem('theme') || 'system';
      apply(saved);

      // Make the toggle strictly two-state (light <-> dark)
      window.__setTheme = () => {
        // Determine what's currently applied to the DOM
        const appliedDark = root.classList.contains('dark');

        // Flip to the opposite explicit state (skip 'system')
        const next = appliedDark ? 'light' : 'dark';
        localStorage.setItem('theme', next);
        apply(next);
      };

      // If user changes OS theme and you're on 'system', re-apply
      media.addEventListener?.('change', () => {
        const cur = localStorage.getItem('theme') || 'system';
        if (cur === 'system') apply('system');
      });
    })();
  </script>

</body>
</html>
