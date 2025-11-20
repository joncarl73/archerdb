<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
    {{-- Impersonation Top Bar --}}
    @php
        use App\Models\Seller;

        /** Cache the current user and seller once to avoid repeated calls */
        $user   = auth()->user();
        $seller = $user ? Seller::firstWhere('owner_id', $user->id) : null;
    @endphp

    @if (session('impersonator_id'))
        <div class="sticky top-0 z-[1000] bg-amber-500 text-black">
            <div class="mx-auto max-w-7xl px-4 py-2 flex items-center justify-between">
                <div class="text-sm font-medium">
                    You are impersonating <span class="font-semibold">{{ $user->name }}</span>.
                </div>
                <form method="POST" action="{{ route('impersonate.stop') }}">
                    @csrf
                    <button class="rounded-md bg-black/10 px-3 py-1 text-sm font-semibold hover:bg-black/20" type="submit">
                        Stop impersonating
                    </button>
                </form>
            </div>
        </div>
    @endif

    {{-- 
        Sidebar with subtle tri-color gradient:
        - We keep your existing light/dark background colors for contrast.
        - The gradient sits on a ::before layer (below content, above base bg).
        - Border colors remain unchanged.
    --}}
    <flux:sidebar
        sticky
        stashable
        class="sidebar-gradient border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900"
    >
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <a href="{{ route('dashboard') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
            <x-app-logo />
        </a>

        <flux:navlist variant="outline">
            <flux:navlist.group :heading="__('Platform')" class="grid">
                <flux:navlist.item
                    icon="home"
                    :href="route('dashboard')"
                    :current="request()->routeIs('dashboard')"
                    wire:navigate
                >{{ __('Dashboard') }}</flux:navlist.item>

                <flux:navlist.item
                    icon="folder"
                    :href="route('gear.loadouts')"
                    :current="request()->routeIs('gear.loadouts')"
                    wire:navigate
                >{{ __('Loadouts') }}</flux:navlist.item>

                <flux:navlist.item
                    icon="arrow-right-start-on-rectangle"
                    :href="route('training.index')"
                    :current="request()->routeIs('training.index')"
                    wire:navigate
                >{{ __('Training') }}</flux:navlist.item>

                @pro
                    <flux:navlist.item
                        icon="identification"
                        :href="route('pro.landing')"
                        :current="request()->routeIs('pro.landing')"
                        wire:navigate
                    >{{ __('Manage Pro') }}</flux:navlist.item>
                @else
                    <flux:navlist.item
                        icon="bolt"
                        :href="route('pro.landing')"
                        :current="request()->routeIs('pro.landing')"
                        wire:navigate
                    >{{ __('Upgrade To Pro') }}</flux:navlist.item>
                @endpro
            </flux:navlist.group>

            @corporate
                <flux:navlist.group heading="Corporate" class="grid mt-2">
                    <flux:navlist.item
                        icon="numbered-list"
                        :href="route('corporate.leagues.index')"
                        :current="request()->routeIs('corporate.leagues.index')"
                        wire:navigate
                    >{{ __('Leagues') }}</flux:navlist.item>

                    <flux:navlist.item
                        icon="flag"
                        :href="route('corporate.events.index')"
                        :current="request()->routeIs('corporate.events.index')"
                        wire:navigate
                    >{{ __('Events') }}</flux:navlist.item>

                    <flux:navlist.item
                        icon="adjustments-horizontal"
                        :href="route('corporate.rulesets.index')"
                        :current="request()->routeIs('corporate.rulesets.index')"
                        wire:navigate
                    >{{ __('Rulesets') }}</flux:navlist.item>

                    @php
                        /** Company owner shortcut for conditional links */
                        $isOwner = $user && $user->company_id && $user->isCompanyOwner($user->company_id);
                    @endphp

                    @if ($isOwner)
                        <flux:navlist.item
                            icon="users"
                            :href="route('corporate.companies.members', ['company' => $user->company_id])"
                            :current="request()->routeIs('corporate.companies.members')"
                            wire:navigate
                        >Company Members</flux:navlist.item>
                    @endif

                    @if (!$seller || !$seller->stripe_account_id)
                        <flux:navlist.item icon="credit-card" :href="route('payments.connect.start')">
                            {{ __('Connect Stripe') }}
                        </flux:navlist.item>
                    @endif
                </flux:navlist.group>
            @endcorporate

            @admin
                <flux:navlist.group heading="Admin" class="grid mt-2">
                    <flux:navlist.item
                        icon="users"
                        :href="route('admin.users')"
                        :current="request()->routeIs('admin.users')"
                        wire:navigate
                    >Users</flux:navlist.item>

                    <flux:navlist.item
                        icon="currency-dollar"
                        :href="route('admin.pricing.tiers.index')"
                        :current="request()->routeIs('admin.pricing.tiers.index')"
                        wire:navigate
                    >Price Tiers</flux:navlist.item>

                    <flux:navlist.item
                        icon="building-storefront"
                        :href="route('admin.companies.pricing.index')"
                        :current="request()->routeIs('admin.companies.pricing.index')"
                        wire:navigate
                    >Companies</flux:navlist.item>

                    <flux:navlist.item
                        icon="wrench"
                        :href="route('admin.manufacturers')"
                        :current="request()->routeIs('admin.manufacturers')"
                        wire:navigate
                    >Manufacturers</flux:navlist.item>
                </flux:navlist.group>
            @endadmin
        </flux:navlist>

        <flux:spacer />

        <flux:navlist variant="outline">
            <flux:navlist.item><x-install-button /></flux:navlist.item>
        </flux:navlist>

        {{-- Desktop User Menu --}}
        <flux:dropdown class="hidden lg:block" position="bottom" align="start">
            <flux:profile
                :name="$user->name"
                :initials="$user->initials()"
                icon:trailing="chevrons-up-down"
            />
            <flux:menu class="w-[220px]">
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                <span class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                    {{ $user->initials() }}
                                </span>
                            </span>
                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <span class="truncate font-semibold">{{ $user->name }}</span>
                                <span class="truncate text-xs">{{ $user->email }}</span>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:sidebar>

    {{-- Mobile User Menu --}}
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        <flux:spacer />
        <flux:dropdown position="top" align="end">
            <flux:profile :initials="$user->initials()" icon-trailing="chevron-down" />
            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                <span class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                    {{ $user->initials() }}
                                </span>
                            </span>
                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <span class="truncate font-semibold">{{ $user->name }}</span>
                                <span class="truncate text-xs">{{ $user->email }}</span>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @once
        {{-- Hide [x-cloak] & Toasts --}}
        <style>[x-cloak]{display:none!important}</style>

        {{-- 
            Sidebar gradient layer:
            - ::before paints a very light tri-color wash so text/icons stay legible.
            - Opacities are lower in dark mode to prevent muddying the base bg.
            - Change angle/stops as desired; values kept conservative for “subtle”.
        --}}
        <style>
            .sidebar-gradient {
                position: relative;
                overflow: hidden; /* ensure gradient stays within the sidebar */
            }
            .sidebar-gradient::before {
                content: "";
                position: absolute;
                inset: 0;
                pointer-events: none;    /* gradient is decorative only */
                z-index: 0;              /* sits under the content */
                background: linear-gradient(
                    160deg,
                    rgba(37, 99, 235, 0.08) 0%,    /* blue-600 @ 8% */
                    rgba(220, 38, 38, 0.08) 45%,   /* red-600  @ 8% */
                    rgba(234, 179, 8, 0.08) 100%   /* yellow-400 @ 8% */
                );
            }
            .dark .sidebar-gradient::before {
                background: linear-gradient(
                    160deg,
                    rgba(59, 130, 246, 0.06) 0%,   /* blue-500 @ 6% */
                    rgba(239, 68, 68, 0.06) 45%,   /* red-500  @ 6% */
                    rgba(250, 204, 21, 0.06) 100%  /* yellow-400 @ 6% */
                );
            }
            /* Ensure anything inside the Flux sidebar renders above the overlay */
            .sidebar-gradient > * { position: relative; z-index: 1; }
        </style>

        {{-- Simple toast host (unchanged) --}}
        <div
            x-data
            x-on:toast.window="Alpine.store('toasts').add($event.detail)"
            class="fixed bottom-4 right-4 z-[9999] space-y-3 pointer-events-none"
            role="status" aria-live="polite" aria-atomic="true"
        >
            <template x-for="t in ($store.toasts?.items || [])" :key="t.id">
                <div
                    x-transition.opacity.scale
                    class="pointer-events-auto rounded-xl px-4 py-3 shadow-xl ring-1 ring-black/10"
                    :class="{
                        'bg-emerald-600 text-white': t.type === 'success',
                        'bg-red-600 text-white': t.type === 'error',
                        'bg-amber-500 text-black': t.type === 'warning',
                        'bg-zinc-900 text-white': !['success','error','warning'].includes(t.type)
                    }"
                >
                    <div class="flex items-center gap-3">
                        <div class="text-sm grow" x-text="t.message || 'Done'"></div>

                        <template x-if="t.action && t.action.label && t.action.event">
                            <button
                                class="rounded-lg px-2 py-1 text-xs font-semibold ring-1 ring-white/20 hover:bg-white/10"
                                @click="
                                    window.dispatchEvent(new CustomEvent(t.action.event, { detail: t.action.payload || {} }));
                                    Alpine.store('toasts').remove(t.id);
                                "
                                x-text="t.action.label"
                            ></button>
                        </template>

                        <button
                            class="ml-1 text-xs opacity-75 hover:opacity-100"
                            @click="Alpine.store('toasts').remove(t.id)"
                        >✕</button>
                    </div>
                </div>
            </template>
        </div>
    @endonce

    @fluxScripts
    <script>
        // Keep PWA service worker registration as-is
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js');
            });
        }
    </script>
</body>
</html>
