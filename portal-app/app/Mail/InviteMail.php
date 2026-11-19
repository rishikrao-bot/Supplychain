<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $link, public ?string $invitedBy = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your invitation to the Compass MEP portal');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invite');
    }
}
