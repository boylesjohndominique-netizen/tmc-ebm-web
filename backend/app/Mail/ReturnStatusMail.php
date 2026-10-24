<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReturnStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $status,
        public int $borrowingRequestId,
        public ?string $remarks = null,
    ) {}

    public function envelope(): Envelope
    {
        $statusText = $this->status;

        return new Envelope(
            subject: "Return Request {$statusText} — TMCEBMS",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.return-status',
        );
    }
}
