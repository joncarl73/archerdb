<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="icon" type="image/x-icon" href="{{ asset('/img/favicons/favicon.ico') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('/img/favicons/favicon-16x16.png') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('/img/favicons/favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="48x48" href="{{ asset('/img/favicons/favicon-48x48.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('/img/favicons/favicon-192x192.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('/img/favicons/apple-touch-icon.png') }}">


<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>


<style>[x-cloak]{display:none !important}</style>

<script>
  document.addEventListener('alpine:init', () => {
    Alpine.store('toasts', {
      items: [],
      add(t) {
        const def = { type: 'success', message: '', duration: 3000 };
        const toast = { id: Date.now() + Math.random(), ...def, ...(t || {}) };
        this.items.push(toast);
        setTimeout(() => this.remove(toast.id), toast.duration);
      },
      remove(id) { this.items = this.items.filter(x => x.id !== id); },
      clear() { this.items = []; }
    });
  });
</script>


@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
