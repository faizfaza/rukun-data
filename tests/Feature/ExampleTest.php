<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // routes/web.php mengarahkan "/" ke panel "/admin" (302), bukan 200.
    public function test_the_application_redirects_to_panel(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/admin');
    }
}
