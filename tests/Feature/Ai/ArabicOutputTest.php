<?php

namespace Tests\Feature\Ai;

use App\Models\AiMessage;
use App\Models\User;
use App\Services\Ai\ArabicTextFixer;
use App\Services\Ai\BriefingService;
use App\Services\Ai\Prompts;
use Database\Seeders\RolesAndPermissionsSeeder;
use PHPUnit\Framework\Attributes\Test;

/**
 * Arabic style section in the prompts and the ArabicTextFixer on every AI
 * output. Faulty model text is written with tokens ({c} comma, {q} question
 * mark, {em} em dash, {_} space before punctuation) so the source stays
 * clean for docs/tools/arabic_lint.py.
 */
class ArabicOutputTest extends AiTestCase
{
    private static function raw(string $text): string
    {
        return strtr($text, ['{c}' => ',', '{q}' => '?', '{em}' => '—', '{_}' => ' ']);
    }

    private function answer(): string
    {
        return self::raw('مرحبًا{c} الحفل الليلة مجاني {em} يبدأ 16:00 في الساحة{_}.')
            ."\n<<<META {\"cards\":[],\"suggestions\":[\"".self::raw('هل يوجد موقف{q}').'"]}>>>';
    }

    #[Test]
    public function streamed_arabic_answers_are_fixed_per_delta_and_stored_fixed_in_full(): void
    {
        // Chunks of 6 characters: the first delta is exactly «مرحبًا», the second starts with the Latin comma.
        $this->fakeOpenAi([$this->sse($this->textResponse($this->answer()), 6)]);

        $res = $this->post('/api/v1/ai/chat', ['message' => 'ما فعاليات الليلة؟', 'locale' => 'ar'], $this->guestHeaders(null, 'guest-device-0001', ['Accept' => 'text/event-stream']))->assertOk();
        $events = $this->sseEvents($res->streamedContent());
        $deltas = array_values(array_map(fn ($e) => $e[1]['text'], array_filter($events, fn ($e) => $e[0] === 'delta')));
        $this->assertStringStartsWith(',', mb_substr(explode("\n", $this->answer())[0], 6, 1));
        $this->assertSame('مرحبًا', $deltas[0]);
        $this->assertStringStartsWith('،', $deltas[1]);

        // Streamed: single-character mappings only (dash and the space before «.» stay until the stored text).
        $this->assertSame(self::raw('مرحبًا، الحفل الليلة مجاني {em} يبدأ 16:00 في الساحة{_}.'), trim(implode('', $deltas)));

        // Stored: the full fixer on the raw visible text.
        $stored = AiMessage::where('role', 'assistant')->firstOrFail();
        $this->assertSame('مرحبًا، الحفل الليلة مجاني، يبدأ 16:00 في الساحة.', $stored->content);
        $this->assertSame(ArabicTextFixer::fix(trim(explode("\n<<<", $this->answer())[0])), $stored->content);
        $this->assertSame($stored->content, ArabicTextFixer::fix(trim(implode('', $deltas))));
        $this->assertSame(['هل يوجد موقف؟'], end($events)[1]['suggestions']);

        // The Arabic style section was sent with the concierge prompt.
        $this->assertStringContainsString(Prompts::ARABIC_STYLE_HEADING, $this->sent(0)['instructions']);
    }

    #[Test]
    public function json_chat_returns_the_fixed_text(): void
    {
        $this->fakeOpenAi([$this->textResponse($this->answer())]);
        $res = $this->postJson('/api/v1/ai/chat', ['message' => 'ما فعاليات الليلة؟', 'locale' => 'ar'], $this->guestHeaders())->assertOk();
        $this->assertSame('مرحبًا، الحفل الليلة مجاني، يبدأ 16:00 في الساحة.', $res->json('data.message.text'));
        $this->assertSame(['هل يوجد موقف؟'], $res->json('data.message.suggestions'));
    }

    #[Test]
    public function english_answers_are_untouched(): void
    {
        $text = 'Hello, the concert is free — it starts at 16:00 ; see you?';
        $this->fakeOpenAi([$this->textResponse($text)]);
        $this->postJson('/api/v1/ai/chat', ['message' => 'Hi', 'locale' => 'en'], $this->guestHeaders())->assertOk()
            ->assertJsonPath('data.message.text', $text);
        // Bilingual concierge: the Arabic section is sent for English sessions too.
        $this->assertStringContainsString(Prompts::ARABIC_STYLE_HEADING, $this->sent(0)['instructions']);
    }

    #[Test]
    public function the_arabic_style_section_is_sent_where_arabic_may_be_written(): void
    {
        $t = $this->tenant;
        $style = Prompts::arabicStyle($t);
        $this->assertStringStartsWith(Prompts::ARABIC_STYLE_HEADING, $style);
        $this->assertStringContainsString('OMR 12.450', $style);
        $this->assertStringContainsString('16:00', $style);
        $this->assertLessThanOrEqual(10, substr_count($style, "\n") + 1);
        // Arabic punctuation only next to Arabic text, no em dash, no exclamation mark.
        $this->assertDoesNotMatchRegularExpression('/[\x{0600}-\x{06FF}»]\h*[,;?]/u', $style);
        $this->assertDoesNotMatchRegularExpression('/[—!]/u', $style);
        $this->assertSame($style, ArabicTextFixer::fix($style));

        $this->assertStringContainsString($style, Prompts::concierge($t, 'ar', false));
        $this->assertStringContainsString($style, Prompts::concierge($t, 'en', false));
        $this->assertStringContainsString($style, Prompts::copilot($t));
        $this->assertStringContainsString($style, Prompts::plan($t, 'ar'));
        $this->assertStringContainsString($style, Prompts::briefing($t, 'ar'));
        $this->assertStringContainsString($style, Prompts::opsInsights($t, 'ar'));
        $this->assertStringNotContainsString(Prompts::ARABIC_STYLE_HEADING, Prompts::plan($t, 'en'));
        $this->assertStringNotContainsString(Prompts::ARABIC_STYLE_HEADING, Prompts::briefing($t, 'en'));
        $this->assertStringNotContainsString(Prompts::ARABIC_STYLE_HEADING, Prompts::opsInsights($t, 'en'));
    }

    #[Test]
    public function copilot_arabic_fields_are_fixed_and_english_fields_kept(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->fakeOpenAi([$this->textResponse(json_encode([
            'title_ar' => self::raw('إغلاق الموقف الشمالي {em} الليلة'),
            'title_en' => 'Parking B closed — tonight',
            'content_ar' => self::raw('موقف B مغلق الليلة{c} استخدموا الموقف الجنوبي{_}.'),
            'content_en' => 'Parking B is closed tonight, please use Parking C.',
        ], JSON_UNESCAPED_UNICODE))]);

        $this->actingAs($admin)->postJson(route('admin.ai.copilot.announcement'), ['brief' => 'Parking B closed tonight'])->assertOk()
            ->assertJsonPath('data.title_ar', 'إغلاق الموقف الشمالي، الليلة')
            ->assertJsonPath('data.content_ar', 'موقف B مغلق الليلة، استخدموا الموقف الجنوبي.')
            ->assertJsonPath('data.title', 'Parking B closed — tonight')
            ->assertJsonPath('data.content', 'Parking B is closed tonight, please use Parking C.');
    }

    #[Test]
    public function arabic_plans_and_briefings_are_fixed(): void
    {
        $event = $this->makeEvent(null, ['title' => 'Carnival', 'title_ar' => 'كرنفال', 'price' => '1.500',
            'start_time' => now()->subDay()->format('Y-m-d').' 16:00:00', 'end_time' => now()->addDays(5)->format('Y-m-d').' 22:00:00']);
        $this->fakeOpenAi([$this->textResponse(json_encode([
            'title' => self::raw('أمسية عائلية {em} كرنفال'),
            'summary' => self::raw('عروض{c} ثم عشاء{_}.'),
            'items' => [['start' => '16:00', 'end' => '17:00', 'kind' => 'event', 'event_id' => $event->id, 'location_id' => null, 'title' => 'x', 'note' => self::raw('احضر مبكرًا{c} فالمقاعد محدودة'), 'cost_minor' => null]],
            'tips' => [self::raw('احمل الماء{c} والقبعة')],
            'warnings' => [],
        ], JSON_UNESCAPED_UNICODE))]);

        $plan = $this->postJson('/api/v1/ai/plan', ['date' => now()->addDay()->toDateString(), 'party' => ['adults' => 1], 'locale' => 'ar'], $this->guestHeaders())
            ->assertOk()->json('data.plan');
        $this->assertSame('أمسية عائلية، كرنفال', $plan['title']);
        $this->assertSame('عروض، ثم عشاء.', $plan['summary']);
        $this->assertSame('احضر مبكرًا، فالمقاعد محدودة', $plan['items'][0]['note']);
        $this->assertSame('كرنفال', $plan['items'][0]['title']);
        $this->assertSame(['احمل الماء، والقبعة'], $plan['tips']);
        $this->assertStringContainsString(Prompts::ARABIC_STYLE_HEADING, $this->sent(0)['instructions']);
        $this->assertStringContainsString('OMR 1.500', $this->sent(0)['input'][0]['content']);   // tool data: code first

        $this->fakeOpenAi([$this->textResponse(json_encode([
            'headline' => self::raw('يوم هادئ{c} بلا مبيعات{_}.'),
            'bullets' => [self::raw('لا تذاكر مباعة {em} بعد')],
            'actions' => [self::raw('راجع طلبات الاسترداد{c} إن وُجدت')],
        ], JSON_UNESCAPED_UNICODE))]);
        $briefing = app(BriefingService::class)->get($this->tenant, $this->makeUser(), 'ar', true);
        $this->assertSame('يوم هادئ، بلا مبيعات.', $briefing['headline']);
        $this->assertSame(['لا تذاكر مباعة، بعد'], $briefing['bullets']);
        $this->assertSame(['راجع طلبات الاسترداد، إن وُجدت'], $briefing['actions']);
        $this->assertStringContainsString(Prompts::ARABIC_STYLE_HEADING, $this->sent(0)['instructions']);
    }
}
