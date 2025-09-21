{{-- resources/views/layouts/public.blade.php --}}
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <title>@yield('title','League')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Tailwind (assuming already built into app.css) --}}
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="h-full bg-white dark:bg-gray-900">
    {{-- Header block from your snippet --}}
    @include('public.partials.header')

    <main>
        <div class="relative isolate overflow-hidden pt-16">
            @yield('secondary-nav')
        </div>

        <div class="space-y-12 py-12">
            @yield('content')
        </div>
    </main>
</body>
</html>
