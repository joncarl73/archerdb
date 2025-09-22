@props(['label', 'value', 'sub' => null])

<div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
  <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $label }}</div>
  <div class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-100 tabular-nums">
    {{ $value }}
  </div>
  @if($sub)
    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $sub }}</div>
  @endif
</div>
