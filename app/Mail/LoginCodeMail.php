<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public int $ttlMinutes)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Sicala sign-in code: ' . $this->code);
    }

    public function content(): Content
    {
        return new Content(
            htmlString: view('mail.login-code', [
                'code' => $this->code,
                'ttl' => $this->ttlMinutes,
            ])->render()
        );
    }
}
