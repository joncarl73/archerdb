<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public string $messageBody
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New contact message from '.$this->name,
            // Use your configured from address/name
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            // Set Reply-To to the user who filled the form
            replyTo: [new Address($this->email, $this->name)],
        );
    }

    public function content(): Content
    {
        // Use Markdown mail (nice default styling), or swap to ->view(...) if you prefer plain Blade
        return new Content(
            markdown: 'emails.contact-message',
            with: [
                'name' => $this->name,
                'email' => $this->email,
                'messageBody' => $this->messageBody,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
