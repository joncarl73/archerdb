@extends('landing.page')

@section('title', 'Contact Us')

@section('page')
  <h2>Contact Us</h2>
  <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
    We’d love to hear from you! Whether you have questions, feedback, or partnership inquiries, 
    you can reach us using the details below.
  </p>

  <h3>Email</h3>
  <p>
    <a href="mailto:support@archerdb.cloud">support@archerdb.cloud</a>
  </p>

  <h3>Address</h3>
  <p>
    ArcherDB<br>
    [Your Company Street Address]<br>
    [City, State, Zip]<br>
    [Country]
  </p>

  <h3>Social</h3>
  <ul>
    <li><a href="https://twitter.com/yourhandle" target="_blank" rel="noopener">Twitter</a></li>
    <li><a href="https://facebook.com/yourpage" target="_blank" rel="noopener">Facebook</a></li>
    <li><a href="https://instagram.com/yourhandle" target="_blank" rel="noopener">Instagram</a></li>
  </ul>

  {{-- Optional: Contact Form Stub --}}
  <h3 class="mt-8">Send us a message</h3>
  <form action="#" method="POST" class="space-y-4">
    @csrf
    <div>
      <label for="name" class="block text-sm font-medium text-neutral-700 dark:text-neutral-200">Name</label>
      <input type="text" id="name" name="name"
             class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 shadow-sm
                    focus:border-primary-500 focus:ring-primary-500 sm:text-sm
                    dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100">
    </div>

    <div>
      <label for="email" class="block text-sm font-medium text-neutral-700 dark:text-neutral-200">Email</label>
      <input type="email" id="email" name="email"
             class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 shadow-sm
                    focus:border-primary-500 focus:ring-primary-500 sm:text-sm
                    dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100">
    </div>

    <div>
      <label for="message" class="block text-sm font-medium text-neutral-700 dark:text-neutral-200">Message</label>
      <textarea id="message" name="message" rows="4"
                class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 shadow-sm
                       focus:border-primary-500 focus:ring-primary-500 sm:text-sm
                       dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"></textarea>
    </div>

    <x-flux::button type="submit" variant="primary" size="sm">
      Send message
    </x-flux::button>
  </form>
@endsection
