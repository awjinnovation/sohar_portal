<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\VisitorBookingController;
use App\Models\Event;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * The event's booking QR (→ /e/{event}): a printable poster, or the bare QR
 * as SVG (?format=svg) for designers.
 */
class EventBookingQrController extends Controller
{
    public function __invoke(Request $request, Event $event, TenantContext $context)
    {
        $url = VisitorBookingController::publicUrl($event);

        if ($request->query('format') === 'svg') {
            return response(QrCode::format('svg')->size(1024)->margin(2)->errorCorrection('H')->generate($url), 200, [
                'Content-Type' => 'image/svg+xml',
                'Content-Disposition' => 'attachment; filename="event-'.$event->id.'-booking-qr.svg"',
            ]);
        }

        return view('admin.events.booking-qr', ['event' => $event, 'url' => $url, 'tenant' => $context->get()]);
    }
}
