<?php

namespace Tests\Feature\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_in_english_by_default(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Mawsim')
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('name="csrf-token"', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('viewport-fit=cover', false)
            ->assertSee('action="'.route('landing.demo-request').'"', false)
            ->assertSee('name="website"', false)
            ->assertSee('name="rendered_at"', false)
            ->assertSee('value="festival"', false)
            ->assertSee('href="'.url('/admin/login').'"', false);
    }

    public function test_lang_query_sets_direction_and_cookie(): void
    {
        $this->get('/?lang=ar')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('مهرجان') // server-rendered select options in Arabic
            ->assertCookie('mawsim_lang', 'ar', false);

        $this->get('/?lang=en')->assertSee('<html lang="en" dir="ltr">', false);
    }

    public function test_language_cookie_and_accept_language_pick_first_paint(): void
    {
        $this->withUnencryptedCookie('mawsim_lang', 'ar')->get('/')
            ->assertSee('<html lang="ar" dir="rtl">', false);

        $this->withHeader('Accept-Language', 'ar-OM,ar;q=0.9')->get('/')
            ->assertSee('<html lang="ar" dir="rtl">', false);
    }

    public function test_share_meta_and_json_ld_use_absolute_urls_from_app_url(): void
    {
        config(['app.url' => 'https://mawsim.awj.om']);

        $html = $this->get('/')->assertOk()
            ->assertSee('<link rel="canonical" href="https://mawsim.awj.om/">', false)
            ->assertSee('<meta property="og:url" content="https://mawsim.awj.om/">', false)
            ->assertSee('<meta property="og:image" content="https://mawsim.awj.om/landing/img/og-image.jpg">', false)
            ->assertSee('<meta property="og:image:secure_url" content="https://mawsim.awj.om/landing/img/og-image.jpg">', false)
            ->assertSee('<meta property="og:image:width" content="1200">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<meta name="twitter:image" content="https://mawsim.awj.om/landing/img/og-image.jpg">', false)
            ->assertSee('rel="manifest"', false)
            ->assertSee('rel="apple-touch-icon"', false)
            // Field photos (Higgsfield scenes) and self-hosted fonts, no outside requests.
            ->assertSee('landing/img/hf_01_family_pay_at_stall.jpg', false)
            ->assertSee('data-i18n="fi_title"', false)
            ->assertSee('landing/fonts.css', false)
            ->assertDontSee('fonts.googleapis.com', false)
            ->getContent();

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $ld = json_decode($m[1] ?? '', true);
        $this->assertSame(['Organization', 'SoftwareApplication', 'WebSite'], array_column($ld, '@type'));
        $this->assertSame('https://mawsim.awj.om/landing/icon-512.png', $ld[0]['logo']);
        $this->assertSame('أوج الأنظمة', $ld[0]['alternateName']);
        $this->assertSame('https://mawsim.awj.om/', $ld[1]['url']);
        $this->assertSame('https://mawsim.awj.om/landing/img/og-image.jpg', $ld[1]['image']);

        // Every asset the page references exists on disk.
        preg_match_all('#https?://[^"\']+/(landing/[^"\'?]+)#', $html, $refs);
        foreach (array_unique($refs[1]) as $path) {
            $this->assertFileExists(public_path($path), $path);
        }
        $manifest = json_decode(file_get_contents(public_path('landing/site.webmanifest')), true);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path('landing/'.$icon['src']));
        }
    }

    public function test_robots_and_sitemap_are_built_from_app_url(): void
    {
        config(['app.url' => 'https://mawsim.awj.om']);

        // Not production (testing): nothing is indexed.
        $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee("User-agent: *\nDisallow: /\n", false);
        $this->get('/')->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->app['env'] = 'production';
        $this->get('/robots.txt')->assertOk()
            ->assertSee("Allow: /\nDisallow: /admin", false)
            ->assertSee('Sitemap: https://mawsim.awj.om/sitemap.xml', false);
        $this->get('/')->assertSee('<meta name="robots" content="index, follow">', false);

        $sitemap = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $xml = simplexml_load_string($sitemap);
        $this->assertSame('https://mawsim.awj.om/', (string) $xml->url->loc);
        $this->assertFileDoesNotExist(public_path('robots.txt')); // a static file would shadow the route
    }

    public function test_admin_is_still_reachable(): void
    {
        $this->get('/admin/login')->assertOk();
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }
}
