<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Test that the public landing page renders successfully.
     */
    public function test_public_landing_page_renders_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('AQUA CLAYNE');
        $response->assertSee('EXPECT THE PUREST');
        $response->assertSee('Clean Water, Healthier Lives.');
        $response->assertSee('About Aqua Clayne');
        $response->assertSee('Our Services and System Features');
        $response->assertSee('Contact Aqua Clayne');
        $response->assertSee(route('admin.login'));
    }
}
