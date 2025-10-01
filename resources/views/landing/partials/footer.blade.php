{{-- resources/views/landing/partials/footer.blade.php --}}
<footer class="border-t border-neutral-200 dark:border-neutral-800">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
    <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-2">
        <x-archerdb-logo class="h-6 w-6" />
        <span class="font-semibold">ArcherDB</span>
      </div>
      <nav class="text-sm flex flex-wrap gap-4 text-neutral-600 dark:text-neutral-300">
        <a href="#features">Features</a>
        <a href="#pricing">Pricing</a>
        <a href="#faq">FAQ</a>
        <a href="/privacy">Privacy</a>
        <a href="/terms">Terms</a>
      </nav>
    </div>
    <p class="mt-6 text-xs text-neutral-500 dark:text-neutral-400">&copy; {{ date('Y') }} ArcherDB. All rights reserved.</p>
  </div>
</footer>
