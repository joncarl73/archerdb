<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <title>{{ $league->title }} – Scoring Sheet</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        .meta { color:#555; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; }
        th { background: #f5f5f5; text-align: left; white-space: nowrap; }
        td.num { text-align: right; }
        .name { width: 28%; }
        .total { background: #fafafa; font-weight: bold; }
        .muted { color:#777; font-size: 8px; }
    </style>
</head>
<body>
    <h1>{{ $league->title }} – Scoring Sheet</h1>
    <div class="meta">
        Location: {{ $league->location ?: '—' }} • Starts: {{ optional($league->start_date)->format('Y-m-d') ?: '—' }} • Weeks: {{ $league->length_weeks }}
    </div>

    <table>
        <thead>
            <tr>
                <th class="name">Archer</th>
                @foreach($league->weeks as $w)
                    <th>
                        W {{ $w->week_number }}<br>
                    </th>
                @endforeach
                <th class="total">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td>
                        {{ $r['name'] }}
                    </td>
                    @foreach($league->weeks as $w)
                        @php $v = $r['weeks'][$w->week_number] ?? 0; @endphp
                        <td class="num">{{ $v }}</td>
                    @endforeach
                    <td class="num total">{{ $r['seasonTotal'] }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $league->weeks->count() + 2 }}">No participants.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
