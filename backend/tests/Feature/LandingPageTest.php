<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    /**
     * Test that the landing page loads successfully with 200 OK.
     */
    public function test_landing_page_loads_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('AQUA CLAYNE');
        $response->assertSee('PURIFIED DRINKING WATER');
        $response->assertSee('EXPECT THE PUREST');
        $response->assertSee('Clean Water, Healthier Lives.');
    }

    /**
     * Test that only required navigation items and the logo are present.
     */
    public function test_landing_page_has_exact_required_navigation(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('images/aqua_clayne_logo_splash.png');
        $response->assertSee('images/aqua_clayne_logo.png');
        $response->assertSee('href="#home">HOME</a>', false);
        $response->assertSee('href="#about">ABOUT</a>', false);
        $response->assertSee('href="#services">SERVICES</a>', false);
        $response->assertSee('href="#download">DOWNLOAD</a>', false);
        $response->assertSee('href="#contact">CONTACT</a>', false);
        $response->assertSee(route('admin.login'));

        // Important navigation removals:
        // "ORDER WATER" removed from top navigation
        $response->assertDontSee('class="nav-item">ORDER WATER</a>', false);
        // "ORDER ONLINE" button removed from upper-right
        $response->assertDontSee('ORDER ONLINE');
        // No Staff Portal button in hero or header
        $response->assertDontSee('STAFF PORTAL');
    }

    /**
     * Test that the download section is properly displayed with required features.
     */
    public function test_download_section_is_present_with_features(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('id="download"', false);
        $response->assertSee('Take Aqua Clayne Anywhere');
        $response->assertSee('Convenient Water Ordering');
        $response->assertSee('Real-Time Delivery Tracking');
        $response->assertSee('Order History');
        $response->assertSee('Account Management');
        $response->assertSee('Delivery Notifications');
        $response->assertSee(route('app.download'));
        $response->assertSee('Coming Soon');
    }

    /**
     * Test that both LOGIN buttons link to /admin/login.
     */
    public function test_login_buttons_link_to_admin_login(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $adminLoginUrl = route('admin.login');

        // Nav pill login button
        $response->assertSee('id="desktopLoginBtn"', false);
        // Hero login button
        $response->assertSee('id="heroLoginBtn"', false);
        $response->assertSee($adminLoginUrl);
    }

    /**
     * Test that the About section does not display technical system capabilities per user requirement.
     */
    public function test_about_section_does_not_display_documented_capabilities(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('About Aqua Clayne');
        $response->assertDontSee('Documented System Capabilities');
        $response->assertDontSee('Flutter Mobile Application');
        $response->assertDontSee('Centralized Laravel REST API');
        $response->assertDontSee('Supabase PostgreSQL Database');
    }

    /**
     * Test that the Services section displays all 8 documented services.
     */
    public function test_services_section_displays_eight_services(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Our Services and System Features');
        $response->assertSee('1. Purified Water Refilling');
        $response->assertSee('2. Customer Ordering');
        $response->assertSee('3. Delivery Management');
        $response->assertSee('4. Refill Queue Management');
        $response->assertSee('5. Order and Delivery Updates');
        $response->assertSee('6. Payments &amp; Credit Management', false);
        $response->assertSee('7. Gallon and QR Tracking');
        $response->assertSee('8. Notifications &amp; Support', false);
    }

    /**
     * Test that the Contact section displays verified station details.
     */
    public function test_contact_section_displays_verified_information(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Contact Aqua Clayne');
        $response->assertSee('007 Batisan St., Brgy. Gatid, Sta Cruz Laguna');
        $response->assertSee('+63 917 555 0199');
        $response->assertSee('support@aquaclayne.ph');
        $response->assertSee('Mon - Sat: 7:00 AM - 7:00 PM | Sun: 8:00 AM - 5:00 PM');
    }

    /**
     * Test contact form validation failure for empty payload.
     */
    public function test_contact_form_validates_required_fields(): void
    {
        $response = $this->postJson('/contact', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);
    }

    /**
     * Test contact form successful submission.
     */
    public function test_contact_form_submission_succeeds(): void
    {
        $response = $this->postJson('/contact', [
            'name' => 'Juan dela Cruz',
            'email' => 'juan@example.com',
            'subject' => 'Water delivery query',
            'message' => 'Please inform me of the delivery schedule in Santa Cruz.',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    /**
     * Test that the About section displays the short introduction and three pillar cards.
     */
    public function test_about_section_displays_short_introduction_and_three_pillars(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('About Aqua Clayne');
        $response->assertSee('Aqua Clayne is a local purified drinking water refilling station committed to providing accessible and convenient drinking water services.');
        $response->assertSee('Our Mission');
        $response->assertSee('To provide clean, safe, and affordable drinking water with convenient customer service.');
        $response->assertSee('Our Vision');
        $response->assertSee('To become a trusted local water refilling station recognized for quality and reliable service.');
        $response->assertSee('Our Commitment');
        $response->assertSee('To deliver a convenient, transparent, and customer-focused ordering experience.');
    }

    /**
     * Test that the Services section displays dynamic product catalog, delivery, and pickup options.
     */
    public function test_services_section_displays_product_catalog_and_fulfillment(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Our Services');
        $response->assertSee('Discover our water products and convenient services designed to meet your everyday drinking water needs.');
        
        // Product Catalog
        $response->assertSee('Available Water Products');
        $response->assertSee('New Gallon with Water');
        $response->assertSee('200.00');
        $response->assertSee('Purified Water Refill');
        $response->assertSee('35.00');
        $response->assertSee('Mineral Water Refill');
        $response->assertSee('40.00');
        $response->assertSee('Alkaline Water Refill');
        $response->assertSee('Unavailable');
        $response->assertSee('View Details');
        $response->assertSee('View All Products');

        // Fulfillment Card B: Home Delivery
        $response->assertSee('Home Delivery');
        $response->assertSee('Enjoy the convenience of having your drinking water delivered directly to your doorstep within our supported delivery areas.');
        $response->assertSee('₱5.00');
        $response->assertSee('Gatid, Duhat, and Bubukal');
        $response->assertSee('Complete delivery address required');
        $response->assertSee('Online order tracking available');
        $response->assertSee('Order for Delivery');

        // Fulfillment Card C: Station Pickup
        $response->assertSee('Station Pickup');
        $response->assertSee('Prefer to collect your order personally? Place your order online and pick it up directly at our station.');
        $response->assertSee('No delivery fee');
        $response->assertSee('Online order confirmation');
        $response->assertSee('Convenient order preparation and pickup');
        $response->assertSee('Order for Pickup');
    }

    /**
     * Test that the 4-step How to Order visual guide is rendered.
     */
    public function test_how_to_order_guide_displays_four_steps(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('How to Order');
        $response->assertSee('Create an Account');
        $response->assertSee('Register or log in to your customer account.');
        $response->assertSee('Choose Your Products');
        $response->assertSee('Browse available products and select quantities.');
        $response->assertSee('Place Your Order');
        $response->assertSee('Enter your information and select delivery or pickup.');
        $response->assertSee('Receive Your Water');
        $response->assertSee('Track your order and receive your water.');
    }

    /**
     * Test that the Why Choose Aqua Clayne section displays all 4 feature cards.
     */
    public function test_why_choose_section_displays_four_features(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Why Choose Aqua Clayne?');
        $response->assertSee('Quality Water');
        $response->assertSee('Drinking water processed through the station\'s purification procedures.', false);
        $response->assertSee('Easy Ordering');
        $response->assertSee('Convenient online product selection and checkout.');
        $response->assertSee('Reliable Delivery');
        $response->assertSee('Home delivery within supported locations.');
        $response->assertSee('Order Tracking');
        $response->assertSee('Monitor order progress through your customer account.');
    }

    /**
     * Test that the interactive Product Details modal elements exist on page.
     */
    public function test_product_modal_dialog_is_present(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('id="productModalBackdrop"', false);
        $response->assertSee('id="productModalDialog"', false);
        $response->assertSee('id="modalProductTitle"', false);
        $response->assertSee('id="modalProductPrice"', false);
        $response->assertSee('id="modalProductDesc"', false);
        $response->assertSee('id="btnModalClose"', false);
        $response->assertSee('id="modalOrderBtn"', false);
    }

    /**
     * Test that HTTPS proxy headers and CSP upgrade-insecure-requests are honored.
     */
    public function test_landing_page_enforces_https_and_csp_headers_behind_proxy(): void
    {
        $response = $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
        ])->get('/');

        $response->assertStatus(200);
        $response->assertSee('<meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">', false);
        $response->assertSee('https://', false);
        $this->assertEquals('upgrade-insecure-requests', $response->headers->get('Content-Security-Policy'));
    }
}

