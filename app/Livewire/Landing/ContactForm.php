<?php

namespace App\Livewire\Landing;

use App\Mail\ContactMessageMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class ContactForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $message = '';

    public string $website = ''; // honeypot

    public bool $sent = false;

    public ?string $sendError = null;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            'website' => ['prohibited'],
        ];
    }

    public function send(): void
    {
        $this->sendError = null;
        $this->sent = false;

        $this->validate();

        $key = 'contact:ip:'.request()->ip();
        if (Cache::has($key)) {
            $this->addError('message', 'Please wait a minute before sending another message.');

            return;
        }
        Cache::put($key, 1, now()->addSeconds(60));

        try {
            // quick trace so you can confirm it's firing
            Log::info('ContactForm@send firing', ['name' => $this->name, 'email' => $this->email]);

            Mail::to(config('mail.contact_to', env('CONTACT_TO_EMAIL', 'support@archerdb.cloud')))
                ->send(new ContactMessageMail($this->name, $this->email, $this->message));

            $this->reset(['name', 'email', 'message', 'website']);
            $this->sent = true;

        } catch (\Throwable $e) {
            Cache::forget($key);
            $this->sendError = 'We couldn’t send your message just now. Please try again.';
            Log::error('Contact form send failed', ['error' => $e->getMessage()]);
        }
    }

    public function render()
    {
        return view('livewire.landing.contact-form');
    }
}
