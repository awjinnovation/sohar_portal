<?php

namespace App\Http\Controllers;

use App\Mail\DemoRequestConfirmation;
use App\Mail\DemoRequestReceived;
use App\Models\DemoRequest;
use App\Support\Landing\FormTimestamp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Public product homepage (Mawsim) and its "Book a demo" form.
 *
 * The page is bilingual: the server picks the first-paint language from
 * ?lang=ar|en, then the `mawsim_lang` cookie (also written by the page's JS
 * toggle), then Accept-Language; the JS dictionary re-renders client-side.
 */
class LandingController extends Controller
{
    public const LANG_COOKIE = 'mawsim_lang';

    public function index(Request $request)
    {
        $locale = self::pageLocale($request);
        App::setLocale($locale);

        $response = response()->view('landing.index', [
            'locale' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'formTs' => FormTimestamp::issue(),
            'eventTypes' => __('landing.event_types', [], $locale),
            'visitorRanges' => __('landing.visitor_ranges', [], $locale),
            'demoDone' => $request->session()->get('demo_success'),
        ]);

        if (in_array($request->query('lang'), DemoRequest::LOCALES, true)) {
            $response->withCookie(self::langCookie($locale));
        }

        return $response;
    }

    /**
     * robots.txt from APP_URL. Outside production (staging, a test domain)
     * nothing is indexed; in production the homepage is, private paths
     * (admin, payment links, API) are not.
     */
    public function robots(): Response
    {
        $base = rtrim((string) config('app.url'), '/');
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Allow: /', 'Disallow: /admin', 'Disallow: /api/', 'Disallow: /pay/', 'Disallow: /payment/', 'Disallow: /ticket/', '', 'Sitemap: '.$base.'/sitemap.xml']
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** sitemap.xml: the homepage (bilingual, ?lang=en|ar), absolute from APP_URL. */
    public function sitemap(): Response
    {
        $home = e(rtrim((string) config('app.url'), '/').'/');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n"
            ."  <url><loc>{$home}</loc><changefreq>monthly</changefreq><priority>1.0</priority>"
            ."<xhtml:link rel=\"alternate\" hreflang=\"en\" href=\"{$home}?lang=en\"/>"
            ."<xhtml:link rel=\"alternate\" hreflang=\"ar\" href=\"{$home}?lang=ar\"/></url>\n"
            .'</urlset>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $locale = in_array($request->input('locale'), DemoRequest::LOCALES, true)
            ? $request->input('locale')
            : self::pageLocale($request);
        App::setLocale($locale);

        // Honeypot: pretend it worked, store and send nothing.
        if (filled($request->input('website'))) {
            Log::info('Demo request honeypot triggered', ['ip_hash' => DemoRequest::hashIp($request->ip())]);

            return $this->succeeded($request, new DemoRequest($this->draft($request, $locale)), $locale);
        }

        // Minimum fill time against a signed render timestamp.
        $age = FormTimestamp::age($request->input('rendered_at'));
        if ($age === null || $age > config('landing.form_ttl_seconds')) {
            return $this->failed($request, ['form' => [__('landing.errors.expired')]]);
        }
        if ($age < config('landing.min_fill_seconds')) {
            return $this->failed($request, ['form' => [__('landing.errors.too_fast')]]);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'organization' => ['required', 'string', 'max:160'],
            'event_type' => ['nullable', 'string', Rule::in(DemoRequest::EVENT_TYPES)],
            'expected_visitors' => ['nullable', 'string', Rule::in(DemoRequest::VISITOR_RANGES)],
            'season' => ['nullable', 'string', 'max:120'],
            'contact' => ['required', 'string', 'max:190', function (string $attribute, mixed $value, \Closure $fail) {
                if (self::contactKind((string) $value) === null) {
                    $fail(__('landing.errors.contact'));
                }
            }],
            'notes' => ['nullable', 'string', 'max:2000'],
            'locale' => ['nullable', Rule::in(DemoRequest::LOCALES)],
        ], [
            'name.required' => __('landing.errors.required'),
            'organization.required' => __('landing.errors.required'),
            'contact.required' => __('landing.errors.required'),
            'name.max' => __('landing.errors.name_max'),
            'organization.max' => __('landing.errors.organization_max'),
            'season.max' => __('landing.errors.season_max'),
            'notes.max' => __('landing.errors.notes_max'),
            'contact.max' => __('landing.errors.contact'),
            'event_type.in' => __('landing.errors.event_type'),
            'expected_visitors.in' => __('landing.errors.expected_visitors'),
            '*.string' => __('landing.errors.required'),
        ]);

        if ($validator->fails()) {
            return $this->failed($request, $validator->errors()->toArray());
        }

        // Store first; mail failures never lose the lead.
        $demo = DemoRequest::create($this->draft($request, $locale) + [
            'ip_hash' => DemoRequest::hashIp($request->ip()),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
        ]);

        $this->notify($demo);

        return $this->succeeded($request, $demo, $locale);
    }

    /** Email the team (and optionally the requester). Never throws. */
    private function notify(DemoRequest $demo): void
    {
        $to = config('landing.demo_request_to');
        if ($to) {
            try {
                $mail = Mail::to($to);
                if ($cc = config('landing.demo_request_cc')) {
                    $mail->cc($cc);
                }
                $mail->send(new DemoRequestReceived($demo));
                $demo->forceFill(['mailed_at' => now()])->save();
            } catch (\Throwable $e) {
                Log::error('Demo request email failed', ['demo_request_id' => $demo->id, 'error' => $e->getMessage()]);
            }
        }

        if ($demo->contact_email && config('landing.demo_request_confirmation')) {
            try {
                Mail::to($demo->contact_email)->send(new DemoRequestConfirmation($demo));
            } catch (\Throwable $e) {
                Log::warning('Demo request confirmation email failed', ['demo_request_id' => $demo->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /** Normalized, validated-shape attributes from the request. */
    private function draft(Request $request, string $locale): array
    {
        $str = fn (string $key) => ($v = trim((string) $request->input($key, ''))) === '' ? null : $v;
        $contact = (string) $str('contact');

        return [
            'name' => (string) $str('name'),
            'organization' => (string) $str('organization'),
            'event_type' => in_array($request->input('event_type'), DemoRequest::EVENT_TYPES, true) ? $request->input('event_type') : null,
            'expected_visitors' => in_array($request->input('expected_visitors'), DemoRequest::VISITOR_RANGES, true) ? $request->input('expected_visitors') : null,
            'season' => $str('season'),
            'contact' => $contact,
            'contact_email' => self::contactKind($contact) === 'email' ? mb_strtolower($contact) : null,
            'notes' => $str('notes'),
            'locale' => $locale,
        ];
    }

    private function succeeded(Request $request, DemoRequest $demo, string $locale): JsonResponse|RedirectResponse
    {
        $message = __('landing.success', ['contact' => $demo->contact], $locale);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'summary' => $demo->summary($locale)]);
        }

        return redirect()->to('/#demo')
            ->with('demo_success', ['message' => $message, 'summary' => $demo->summary($locale), 'contact' => $demo->contact])
            ->withCookie(self::langCookie($locale));
    }

    private function failed(Request $request, array $errors, int $status = 422): JsonResponse|RedirectResponse
    {
        $first = collect($errors)->flatten()->first();

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $first, 'errors' => $errors, 'code' => 'VALIDATION_ERROR'], $status);
        }

        return redirect()->to('/#demo')->withErrors($errors)->withInput($request->except(['website', 'rendered_at', '_token']));
    }

    /** Response for the `demo-requests` rate limiter (see AppServiceProvider). */
    public static function throttled(Request $request, array $headers)
    {
        $locale = in_array($request->input('locale'), DemoRequest::LOCALES, true) ? $request->input('locale') : self::pageLocale($request);
        $message = __('landing.errors.throttled', ['email' => config('landing.demo_request_to')], $locale);

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'code' => 'TOO_MANY_REQUESTS'], 429, $headers);
        }

        return redirect()->to('/#demo')->withErrors(['form' => $message])
            ->withInput($request->except(['website', 'rendered_at', '_token']));
    }

    /** 'email' | 'phone' | null (not a plausible contact). */
    public static function contactKind(string $contact): ?string
    {
        $contact = trim($contact);
        if ($contact === '') {
            return null;
        }
        if (str_contains($contact, '@')) {
            return filter_var($contact, FILTER_VALIDATE_EMAIL) ? 'email' : null;
        }
        if (! preg_match('/^\+?[0-9 \-]+$/', $contact)) {
            return null;
        }
        $digits = strlen(preg_replace('/\D/', '', $contact));

        return $digits >= 7 && $digits <= 15 ? 'phone' : null;
    }

    public static function pageLocale(Request $request): string
    {
        $query = $request->query('lang');
        if (in_array($query, DemoRequest::LOCALES, true)) {
            return $query;
        }
        $cookie = $request->cookie(self::LANG_COOKIE);
        if (in_array($cookie, DemoRequest::LOCALES, true)) {
            return $cookie;
        }

        return $request->getPreferredLanguage(['en', 'ar']) === 'ar' ? 'ar' : 'en';
    }

    private static function langCookie(string $locale)
    {
        // Readable by the page's JS toggle, which writes the same cookie.
        return cookie()->forever(self::LANG_COOKIE, $locale, null, null, null, false, false, 'lax');
    }
}
