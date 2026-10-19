<?php

namespace Tests\Feature\Content;

use App\Models\Event;
use App\Models\Gate;
use App\Models\PrivacyPolicy;
use App\Models\Tenant;
use App\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoTenantSeeder;
use Database\Seeders\LegalTemplate;
use Database\Seeders\NorthBatinahEventsSeeder;
use Database\Seeders\PrivacyPolicySeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncArabicContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ob_start(); // seeders echo a summary line
        $this->seed(DatabaseSeeder::class);
        ob_end_clean();
    }

    private function sync(array $options = []): string
    {
        $this->assertSame(0, Artisan::call('content:sync-arabic', $options));

        return Artisan::output();
    }

    #[Test]
    public function it_restores_mutated_arabic_texts_and_is_idempotent(): void
    {
        $this->assertStringContainsString('0 changes in 0 rows', $this->sync(), 'freshly seeded data is already in sync');

        $nb = Tenant::where('slug', 'north-batinah')->first();
        $demo = Tenant::where('slug', 'demo')->first();
        $opening = NorthBatinahEventsSeeder::content()['events'][1];
        $gatesBefore = DB::table('gates')->orderBy('id')->get()->toJson();

        Event::where('title', $opening['t'])->update(['title_ar' => 'قديم', 'description_ar' => 'قديم', 'title' => $opening['t']]);
        Vendor::all()->first(fn ($v) => $v->name['en'] === 'Sohar Karak House')
            ->forceFill(['name' => ['en' => 'Sohar Karak House', 'ar' => 'قديم']])->save();
        $nb->putSetting('ai.welcome.ar', 'قديم')->putSetting('ai.suggestions.0.ar', 'قديم')->save();
        $demo->putSetting('branding.onboarding.0.title.ar', 'قديم')->save();
        PrivacyPolicy::withoutTenantScope()->where('tenant_id', $nb->id)->update(['content_ar' => '<p>قديم</p>']);
        $this->actingAsTenant($demo);
        Event::where('title', 'Expo Day Pass')->update(['title_ar' => 'قديم']);
        $this->actingAsTenant($nb);

        // Dry run lists the changes but writes nothing.
        $dry = $this->sync(['--dry-run' => true]);
        $this->assertStringContainsString('Dry run: 8 changes in 6 rows would be written.', $dry);
        $this->assertSame('قديم', Event::where('title', $opening['t'])->value('title_ar'));

        $this->assertStringContainsString('8 changes in 6 rows.', $this->sync());

        $this->assertSame($opening['t_ar'], Event::where('title', $opening['t'])->value('title_ar'));
        $this->assertSame($opening['d_ar'], Event::where('title', $opening['t'])->value('description_ar'));
        $this->assertSame('بيت كرك صحار', Vendor::all()->first(fn ($v) => $v->name['en'] === 'Sohar Karak House')->name['ar']);
        $nb->refresh();
        $this->assertSame(TenantSeeder::northBatinah()['settings']['ai']['welcome']['ar'], $nb->settings['ai']['welcome']['ar']);
        $this->assertSame(TenantSeeder::northBatinah()['settings']['ai']['suggestions'][0]['ar'], $nb->settings['ai']['suggestions'][0]['ar']);
        $this->assertSame(TenantSeeder::demo()['settings']['branding']['onboarding'][0]['title']['ar'], $demo->refresh()->settings['branding']['onboarding'][0]['title']['ar']);
        $this->assertSame(
            LegalTemplate::fill(PrivacyPolicySeeder::arabicTemplate(), $nb),
            PrivacyPolicy::withoutTenantScope()->where('tenant_id', $nb->id)->value('content_ar'),
        );
        $this->actingAsTenant($demo);
        $this->assertSame(DemoTenantSeeder::content()['events'][1][1], Event::where('title', 'Expo Day Pass')->value('title_ar'));

        // English fields and gate tables are untouched; a second run has nothing left to do.
        $this->assertSame('Welcome to North Batinah events! Ask me about the Sohar Festival, races, heritage events, tickets or parking.', $nb->settings['ai']['welcome']['en']);
        $this->assertSame($gatesBefore, DB::table('gates')->orderBy('id')->get()->toJson());
        $this->assertGreaterThan(0, Gate::withoutTenantScope()->count());
        $this->assertStringContainsString('0 changes in 0 rows.', $this->sync());
    }

    #[Test]
    public function it_rejects_an_unknown_tenant(): void
    {
        $this->assertSame(1, Artisan::call('content:sync-arabic', ['--tenant' => ['nope']]));
    }
}
