<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    public function test_admin_login_page_renders_successfully(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
        $response->assertSee('AQUA CLAYNE');
        $response->assertSee('Purified Drinking Water');
        $response->assertSee('Clean Water, Healthier Lives.');
        $response->assertSee('Unified Portal');
        $response->assertSee('Sign in');
        $response->assertSee('admin-login.css');
        $response->assertSee('admin-login.js');
        $response->assertSee(route('admin.login.submit'), false);
    }
}
