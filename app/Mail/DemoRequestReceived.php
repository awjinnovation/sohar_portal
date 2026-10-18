<?php

namespace App\Mail;

use App\Models\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Internal notification of a new landing-page demo request (to
 * config('landing.demo_request_to'), English). Reply-To = the requester
 * when they gave an email.
 */
class DemoRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DemoRequest $demoRequest)
    {
    }

    public function envelope(): Envelope
    {
        $r = $this->demoRequest;
        $type = $r->event_type ? $r->eventTypeLabel('en') : 'event type not given';

        return new Envelope(
            subject: "New demo request — {$r->organization} ({$type})",
            replyTo: $r->contact_email ? [new Address($r->contact_email, $r->name)] : [],
            tags: ['demo-request'],
        );
    }

    public function content(): Content
    {
        $r = $this->demoRequest;
        $submitted = $r->submittedAtLocal() ?? now(config('landing.timezone'));

        return new Content(
            view: 'emails.demo-request',
            text: 'emails.demo-request-text',
            with: [
                'r' => $r,
                'eventType' => $r->eventTypeLabel('en'),
                'visitors' => $r->visitorsLabel('en'),
                'submittedAt' => $submitted->format('D j M Y, H:i').' (Muscat, GMT+4)',
                'localeLabel' => $r->locale === 'ar' ? 'Arabic (ar)' : 'English (en)',
                'adminUrl' => route('admin.demo-requests.show', $r),
                'adminListUrl' => route('admin.demo-requests.index'),
                'replySubject' => 'Your Mawsim demo request',
            ],
        );
    }
}
