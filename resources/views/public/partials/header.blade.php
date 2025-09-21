  <!-- Header (from your snippet) -->
  <header class="absolute inset-x-0 top-0 z-50 flex h-16 border-b border-gray-900/10 dark:border-white/10">
    <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
      <div class="flex flex-1 items-center gap-x-6">
        <button type="button" command="show-modal" commandfor="mobile-menu" class="-m-3 p-3 md:hidden">
          <span class="sr-only">Open main menu</span>
          <svg viewBox="0 0 20 20" fill="currentColor" data-slot="icon" aria-hidden="true" class="size-5 text-gray-900 dark:text-white">
            <path d="M2 4.75A.75.75 0 0 1 2.75 4h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 4.75ZM2 10a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 10Zm0 5.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd" fill-rule="evenodd" />
          </svg>
        </button>
        <img src="https://tailwindcss.com/plus-assets/img/logos/mark.svg?color=indigo&shade=600" alt="ArcherDB" class="h-8 w-auto dark:hidden" />
        <img src="https://tailwindcss.com/plus-assets/img/logos/mark.svg?color=indigo&shade=500" alt="ArcherDB" class="h-8 w-auto not-dark:hidden" />
      </div>
      <nav class="hidden md:flex md:gap-x-11 md:text-sm/6 md:font-semibold md:text-gray-700 dark:md:text-gray-300">
        {{-- Public nav (optional) --}}
        <a href="{{ url('/') }}">Home</a>
      </nav>
      <div class="flex flex-1 items-center justify-end gap-x-8">
        {{-- Right-side icons (optional) --}}
        <a href="#" class="-m-1.5 p-1.5">
          <span class="sr-only">Your profile</span>
          <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=facearea&facepad=2&w=256&h=256&q=80"
               alt="" class="size-8 rounded-full bg-gray-100 outline -outline-offset-1 outline-black/5 dark:bg-gray-800 dark:outline-white/10" />
        </a>
      </div>
    </div>

    <el-dialog>
      <dialog id="mobile-menu" class="backdrop:bg-transparent lg:hidden">
        <div tabindex="0" class="fixed inset-0 focus:outline-none">
          <el-dialog-panel class="fixed inset-y-0 left-0 z-50 w-full overflow-y-auto bg-white px-4 pb-6 sm:max-w-sm sm:px-6 sm:ring-1 sm:ring-gray-900/10 dark:bg-gray-900 dark:sm:ring-white/10">
            <div class="-ml-0.5 flex h-16 items-center gap-x-6">
              <button type="button" command="close" commandfor="mobile-menu" class="-m-2.5 p-2.5 text-gray-700 dark:text-gray-400">
                <span class="sr-only">Close menu</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" data-slot="icon" aria-hidden="true" class="size-6">
                  <path d="M6 18 18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </button>
              <div class="-ml-0.5">
                <a href="{{ url('/') }}" class="-m-1.5 block p-1.5">
                  <span class="sr-only">ArcherDB</span>
                  <img src="https://tailwindcss.com/plus-assets/img/logos/mark.svg?color=indigo&shade=600" alt="" class="h-8 w-auto dark:hidden" />
                  <img src="https://tailwindcss.com/plus-assets/img/logos/mark.svg?color=indigo&shade=500" alt="" class="h-8 w-auto not-dark:hidden" />
                </a>
              </div>
            </div>
            <div class="mt-2 space-y-2">
              <a href="{{ url('/') }}" class="-mx-3 block rounded-lg px-3 py-2 text-base/7 font-semibold text-gray-900 hover:bg-gray-50 dark:text-white dark:hover:bg-white/5">Home</a>
            </div>
          </el-dialog-panel>
        </div>
      </dialog>
    </el-dialog>
  </header>