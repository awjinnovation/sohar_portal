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
 * Short thank-you to the requester, in their language (Arabic = RTL).
 * Sent from MAIL_FROM_ADDRESS; replies go to the demo-request inbox.
 */
class DemoRequestConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DemoRequest $demoRequest)
    {
        $this->locale($demoRequest->locale === 'ar' ? 'ar' : 'en');
    }

    public function envelope(): Envelope
    {
        $team = config('landing.demo_request_to');

        return new Envelope(
            subject: __('landing.mail.confirm_subject', [], $this->locale),
            replyTo: $team ? [new Address($team, 'AWJ Systems')] : [],
            tags: ['demo-request-confirmation'],
        );
    }

    public function content(): Content
    {
        $r = $this->demoRequest;
        $lc = $this->locale;

        $rows = [
            __('landing.fields.name', [], $lc) => $r->name,
            __('landing.fields.organization', [], $lc) => $r->organization,
            __('landing.fields.event_type', [], $lc) => $r->eventTypeLabel($lc),
            __('landing.fields.expected_visitors', [], $lc) => $r->visitorsLabel($lc),
        ];
        if ($r->season) {
            $rows[__('landing.fields.season', [], $lc)] = $r->season;
        }
        $rows[__('landing.fields.contact', [], $lc)] = $r->contact;

        return new Content(
            view: 'emails.demo-request-confirmation',
            text: 'emails.demo-request-confirmation-text',
            with: [
                'r' => $r,
                'lc' => $lc,
                'rtl' => $lc === 'ar',
                'rows' => $rows,
                'homeUrl' => url('/').'?lang='.$lc,
            ],
        );
    }
}
