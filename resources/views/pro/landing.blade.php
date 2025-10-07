<x-layouts.app title="Upgrade to Pro">
    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-emerald-50 p-3 text-sm text-emerald-800 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-100 dark:ring-emerald-900/40">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-white/10 dark:bg-neutral-900">
            <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">Go Pro — ${{ $priceDisplay }}/year</h1>
            <p class="mt-2 text-neutral-600 dark:text-neutral-400">
                Unlock premium features:
            </p>
            <ul class="mt-3 list-disc pl-5 text-neutral-700 dark:text-neutral-300">
                <li>Advanced analytics & reports</li>
                <li>Priority scoring tools</li>
                <li>Early access to new features</li>
                <li>And more…</li>
            </ul>

            <div class="mt-6 flex items-center gap-3">
                @if ($isPro)
                    <flux:button as="a" href="{{ route('pro.manage') }}" variant="primary" icon="cog-6-tooth">
                        Manage subscription
                    </flux:button>
                    <span class="text-sm text-neutral-500 dark:text-neutral-400">You're Pro 🎯</span>
                @else
                    <form method="POST" action="{{ route('pro.checkout.start') }}">
                        @csrf
                        <flux:button type="submit" variant="primary" icon="sparkles">
                            Upgrade to Pro — ${{ $priceDisplay }}/year
                        </flux:button>
                    </form>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 text-sm text-neutral-600 dark:border-white/10 dark:bg-neutral-900 dark:text-neutral-300">
            Tip: You can cancel anytime in the Manage page. Your Pro access remains until the end of the paid period.
        </div>
    </div>
</x-layouts.app>
