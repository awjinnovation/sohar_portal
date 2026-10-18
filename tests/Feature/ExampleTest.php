<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        // Root is the public product landing page (admin lives under /admin).
        $this->get('/')->assertOk()->assertSee('Mawsim');
    }
}
