<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $league->title }} — League</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-white dark:bg-zinc-900">
    <main class="mx-auto max-w-3xl p-6">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $league->title }}</h1>
        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
            {{ $league->location ?: '—' }} • {{ ucfirst($league->type->value ?? $league->type) }}
            • Starts {{ optional($league->start_date)->format('Y-m-d') ?: '—' }}
            • {{ $league->length_weeks }} weeks
        </p>

        <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-white/10">
            <table class="w-full text-left">
                <thead class="bg-white dark:bg-gray-900">
                    <tr>
                        <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Week</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Date</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Day</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse($league->weeks()->orderBy('week_number')->get() as $w)
                        <tr>
                            <td class="py-3.5 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white">{{ $w->week_number }}</td>
                            <td class="px-3 py-3.5 text-sm text-gray-600 dark:text-gray-300">{{ \Carbon\Carbon::parse($w->date)->format('Y-m-d') }}</td>
                            <td class="px-3 py-3.5 text-sm text-gray-600 dark:text-gray-300">{{ \Carbon\Carbon::parse($w->date)->format('l') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 px-4 text-sm text-gray-600 dark:text-gray-300">No weeks scheduled yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
