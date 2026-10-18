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

    public function test_admin_is_still_reachable(): void
    {
        $this->get('/admin/login')->assertOk();
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }
}
