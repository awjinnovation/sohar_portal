<?php

namespace Tests\Feature\Ai;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use PHPUnit\Framework\Attributes\Test;

/** Copilot on the place / item / page forms (docs/places_content_contract.md §4). */
class ContentCopilotTest extends AiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    #[Test]
    public function content_manager_gets_a_place_description_and_html_translation(): void
    {
        $this->fakeOpenAi([
            $this->textResponse(json_encode(['summary_ar' => 'حصن في ولاية لوى.', 'summary_en' => 'Fort in Liwa.', 'description_ar' => 'نص عربي', 'description_en' => 'English text'], JSON_UNESCAPED_UNICODE)),
            $this->textResponse(json_encode(['description' => '<p>A <b>fort</b></p>'])),
        ]);
        $this->actingAs($this->staff('content-manager'));

        $res = $this->postJson(route('admin.ai.copilot.content-description'), [
            'kind' => 'place', 'title_ar' => 'حصن لوى', 'category' => 'قلاع وحصون', 'wilayat' => 'لوى', 'notes' => '<p>notes <script>x</script></p>',
        ])->assertOk();
        $this->assertSame(['summary_ar' => 'حصن في ولاية لوى.', 'summary' => 'Fort in Liwa.', 'description_ar' => 'نص عربي', 'description' => 'English text'], $res->json('data'));
        $sent = $this->sent(0);
        $this->assertSame('gpt-test-smart', $sent['model']);
        $this->assertStringContainsString('Do not add history', $sent['instructions']);
        $this->assertStringContainsString('لوى', $sent['input'][0]['content']);
        $this->assertStringNotContainsString('<script>', $sent['input'][0]['content']);

        $this->postJson(route('admin.ai.copilot.translate'), ['from' => 'ar', 'to' => 'en', 'fields' => ['description' => '<p>حصن <b>كبير</b></p>']])
            ->assertOk()->assertJsonPath('data.fields.description', '<p>A <b>fort</b></p>');
        $this->assertStringContainsString('keep every tag', $this->sent(1)['instructions']);
    }

    #[Test]
    public function content_description_is_permission_gated_per_kind(): void
    {
        $this->fakeOpenAi([]);
        $this->actingAs($this->staff('event-manager'))
            ->postJson(route('admin.ai.copilot.content-description'), ['kind' => 'place', 'title' => 'X'])->assertForbidden();
        $this->actingAs($this->staff('finance-manager'))
            ->postJson(route('admin.ai.copilot.content-description'), ['kind' => 'page', 'title' => 'X'])->assertForbidden();
        $this->actingAs($this->staff('content-manager'))
            ->postJson(route('admin.ai.copilot.content-description'), ['kind' => 'item'])->assertStatus(422);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }
}
