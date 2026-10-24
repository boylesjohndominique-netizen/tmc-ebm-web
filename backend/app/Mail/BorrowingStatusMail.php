<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BorrowingStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $status,
        public int $requestId,
        public string $equipmentList,
        public ?string $remarks = null,
    ) {}

    public function envelope(): Envelope
    {
        $statusText = $this->status === 'Approved' ? 'Approved' : 'Rejected';

        return new Envelope(
            subject: "Borrowing Request #{$this->requestId} {$statusText} — TMCEBMS",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.borrowing-status',
        );
    }
}
