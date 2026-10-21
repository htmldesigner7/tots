<?php

namespace App\Mail;

use App\Models\FormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewSubmission extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public FormSubmission $submission)
    {
    }

    public function envelope(): Envelope
    {
        $what = $this->submission->form === 'careers' ? 'job application' : 'enquiry';

        return new Envelope(
            subject: 'New website '.$what.' from '.$this->submission->name,
            replyTo: $this->submission->email ? [$this->submission->email] : [],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.submission');
    }
}
