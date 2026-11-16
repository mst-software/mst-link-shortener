<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent from the admin Email settings page to check that the mail settings work.
 */
class SettingsTestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Test email from '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.settings-test',
        );
    }
}
