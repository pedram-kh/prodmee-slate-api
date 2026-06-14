<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $roleLabel,
        public string $loginUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You have been invited to Prodmee Slate');
    }

    public function content(): Content
    {
        return new Content(
            htmlString: view('mail.invitation', [
                'name' => $this->name,
                'roleLabel' => $this->roleLabel,
                'loginUrl' => $this->loginUrl,
            ])->render()
        );
    }
}
