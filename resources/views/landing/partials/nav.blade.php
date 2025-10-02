<header class="sticky top-0 z-40 bg-white/80 backdrop-blur border-b border-neutral-200 dark:bg-neutral-950/80 dark:border-neutral-800">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
    
    {{-- Left: Logo + Nav links --}}
    <div class="flex items-center">
      <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold pr-8">
        <x-archerdb-logo class="h-6 w-6" />
        <span>ArcherDB</span>
      </a>

      <nav class="hidden md:flex items-center gap-6 text-sm">
        <a href="#features" class="hover:opacity-80">Features</a>
        <a href="#partners" class="hover:opacity-80">Partners</a>
        <a href="#pricing" class="hover:opacity-80">Pricing</a>
        <a href="#events" class="hover:opacity-80">Upcoming Events</a>
      </nav>
    </div>


    {{-- Right: Theme toggle + Auth buttons --}}
    <div class="flex items-center gap-3">
      {{-- Theme toggle --}}
      <button
        id="themeToggle"
        type="button"
        onclick="window.__setTheme?.()"
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

      @if (Route::has('login'))
          <nav class="flex items-center justify-end gap-4">
              @auth
                  <a
                      href="{{ url('/dashboard') }}"
                      class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal"
                  >
                      Dashboard
                  </a>
              @else
                  <a
                      href="{{ route('login') }}"
                      class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] text-[#1b1b18] border border-transparent hover:border-[#19140035] dark:hover:border-[#3E3E3A] rounded-sm text-sm leading-normal"
                  >
                      Log in
                  </a>

                  @if (Route::has('register'))
                      <a
                          href="{{ route('register') }}"
                          class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal">
                          Register
                      </a>
                  @endif
              @endauth
          </nav>
      @endif
    </div>
  </div>
</header>
