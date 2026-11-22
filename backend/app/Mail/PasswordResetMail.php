<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The e-mail with the one-time "set a new password" link (plain text, view: emails/password-reset). */
class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $userName, public string $url)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Восстановление пароля - Колонка');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.password-reset');
    }
}
