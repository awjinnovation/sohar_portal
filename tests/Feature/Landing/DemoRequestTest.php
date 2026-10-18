<?php

namespace Tests\Feature\Landing;

use App\Mail\DemoRequestConfirmation;
use App\Mail\DemoRequestReceived;
use App\Models\DemoRequest;
use App\Support\Landing\FormTimestamp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DemoRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['landing.demo_request_to' => 'systems@awj.om', 'landing.demo_request_cc' => [], 'landing.demo_request_confirmation' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Salim Al Harthy',
            'organization' => 'Sohar Winter Festival',
            'event_type' => 'festival',
            'expected_visitors' => '25k-100k',
            'season' => 'Dec 2026 – Jan 2027',
            'contact' => 'Salim@Example.om',
            'notes' => "Two venues.\nArabic first.",
            'locale' => 'en',
            'website' => '',
            'rendered_at' => FormTimestamp::issue(time() - 10),
        ], $overrides);
    }

    public function test_json_submission_stores_and_emails_the_team(): void
    {
        $this->postJson(route('landing.demo-request'), $this->payload(), ['User-Agent' => 'PHPUnit Browser'])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('message', "Thanks, we've received your request. We'll contact you at Salim@Example.om.");

        $demo = DemoRequest::sole();
        $this->assertSame('Sohar Winter Festival', $demo->organization);
        $this->assertSame('festival', $demo->event_type);
        $this->assertSame('25k-100k', $demo->expected_visitors);
        $this->assertSame('salim@example.om', $demo->contact_email);
        $this->assertSame('new', $demo->status);
        $this->assertSame('en', $demo->locale);
        $this->assertSame('PHPUnit Browser', $demo->user_agent);
        $this->assertSame(hash('sha256', '127.0.0.1|'.config('app.key')), $demo->ip_hash);
        $this->assertNotNull($demo->mailed_at);

        Mail::assertSent(DemoRequestReceived::class, function (DemoRequestReceived $mail) use ($demo) {

            return $mail->hasTo('systems@awj.om')
                && $mail->hasReplyTo('salim@example.om')
                && $mail->demoRequest->is($demo)
                && $mail->envelope()->subject === 'New demo request — Sohar Winter Festival (Festival)';
        });
        Mail::assertSent(DemoRequestConfirmation::class, fn ($mail) => $mail->hasTo('salim@example.om'));
    }

    public function test_emails_render(): void
    {
        $this->postJson(route('landing.demo-request'), $this->payload(['locale' => 'ar', 'organization' => 'مهرجان صحار']))->assertOk();
        $demo = DemoRequest::sole();

        $html = (new DemoRequestReceived($demo))->render();
        $this->assertStringContainsString('Mawsim', $html);
        $this->assertStringContainsString('مهرجان صحار', $html);
        $this->assertStringContainsString('mailto:salim@example.om', $html);
        $this->assertStringContainsString(route('admin.demo-requests.show', $demo), $html);

        $confirmation = (new DemoRequestConfirmation($demo))->render();
        $this->assertStringContainsString('dir="rtl"', $confirmation);
        $this->assertStringContainsString('شكراً لك', $confirmation);
    }

    public function test_confirmation_only_when_an_email_is_given(): void
    {
        $this->postJson(route('landing.demo-request'), $this->payload(['contact' => '+968 9123-4567']))->assertOk();

        $demo = DemoRequest::sole();
        $this->assertNull($demo->contact_email);
        Mail::assertSent(DemoRequestReceived::class, fn ($mail) => $mail->hasTo('systems@awj.om') && ! $mail->hasReplyTo('+968 9123-4567'));
        Mail::assertNotSent(DemoRequestConfirmation::class);
    }

    public function test_cc_recipients_from_config(): void
    {
        config(['landing.demo_request_cc' => ['sales@awj.om']]);
        $this->postJson(route('landing.demo-request'), $this->payload())->assertOk();

        Mail::assertSent(DemoRequestReceived::class, fn ($mail) => $mail->hasCc('sales@awj.om'));
    }

    public function test_validation_errors_are_localized(): void
    {
        $this->postJson(route('landing.demo-request'), $this->payload(['name' => '', 'contact' => 'nope']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'contact'])
            ->assertJsonPath('message', 'Add your name, organization and a phone number or email so we can reach you.');

        $this->postJson(route('landing.demo-request'), $this->payload(['locale' => 'ar', 'contact' => '12345', 'event_type' => 'rave']))
            ->assertStatus(422)
            ->assertJsonPath('errors.contact.0', 'أدخل بريداً إلكترونياً صحيحاً أو رقم هاتف من 7 إلى 15 رقماً.')
            ->assertJsonPath('errors.event_type.0', 'اختر نوع الفعالية من القائمة.');

        $this->postJson(route('landing.demo-request'), $this->payload(['notes' => str_repeat('x', 2001)]))
            ->assertStatus(422)->assertJsonValidationErrors(['notes']);

        $this->assertSame(0, DemoRequest::count());
        Mail::assertNothingSent();
    }

    public function test_contact_formats(): void
    {
        foreach (['+968 9123 4567', '96891234567', '9123-4567', 'a.b@awj.om'] as $ok) {
            $this->assertNotNull(\App\Http\Controllers\LandingController::contactKind($ok), $ok);
        }
        foreach (['123456', '1234567890123456', 'call me', 'a@b', '+968 (9123) 4567'] as $bad) {
            $this->assertNull(\App\Http\Controllers\LandingController::contactKind($bad), $bad);
        }
    }

    public function test_honeypot_returns_success_but_stores_nothing(): void
    {
        $this->postJson(route('landing.demo-request'), $this->payload(['website' => 'http://spam.example']))
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame(0, DemoRequest::count());
        Mail::assertNothingSent();
    }

    public function test_too_fast_or_forged_timestamps_are_rejected(): void
    {
        $this->postJson(route('landing.demo-request'), $this->payload(['rendered_at' => FormTimestamp::issue()]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'That was quick. Please wait a few seconds and send again.');

        $forged = (time() - 60).'.'.str_repeat('a', 64);
        $this->postJson(route('landing.demo-request'), $this->payload(['rendered_at' => $forged]))
            ->assertStatus(422)->assertJsonValidationErrors(['form']);

        $this->postJson(route('landing.demo-request'), $this->payload(['rendered_at' => null]))->assertStatus(422);

        $this->assertSame(0, DemoRequest::count());
        Mail::assertNothingSent();
    }

    public function test_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('landing.demo-request'), $this->payload(['contact' => '+968 9000 000'.$i]))->assertOk();
        }

        $this->postJson(route('landing.demo-request'), $this->payload(['locale' => 'ar']))
            ->assertStatus(429)
            ->assertJsonPath('code', 'TOO_MANY_REQUESTS')
            ->assertJsonPath('message', 'طلبات كثيرة من شبكتك. حاول لاحقاً أو راسلنا على systems@awj.om.');

        $this->assertSame(5, DemoRequest::count());
    }

    public function test_non_js_post_redirects_back_with_flash(): void
    {
        $response = $this->post(route('landing.demo-request'), $this->payload(['locale' => 'ar']));

        $response->assertRedirect('/#demo')->assertSessionHas('demo_success');
        $this->assertSame(1, DemoRequest::count());
        $this->assertStringContainsString('شكراً، استلمنا طلبك', session('demo_success')['message']);

        $this->get('/?lang=ar')
            ->assertOk()
            ->assertSee('شكراً، استلمنا طلبك. سنتواصل معك على <bdi dir="ltr">Salim@Example.om</bdi>.', false)
            ->assertSee('طلب عرض موسم');
    }

    public function test_non_js_validation_redirects_back_with_errors_and_input(): void
    {
        $this->post(route('landing.demo-request'), $this->payload(['organization' => '']))
            ->assertRedirect('/#demo')
            ->assertSessionHasErrors('organization')
            ->assertSessionHasInput('name', 'Salim Al Harthy');

        $this->get('/')->assertSee('Add your name, organization and a phone number or email so we can reach you.')
            ->assertSee('value="Salim Al Harthy"', false);
    }

    public function test_mail_failure_does_not_break_submission(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $this->postJson(route('landing.demo-request'), $this->payload())->assertOk()->assertJson(['success' => true]);

        $demo = DemoRequest::sole();
        $this->assertNull($demo->mailed_at);
    }
}
