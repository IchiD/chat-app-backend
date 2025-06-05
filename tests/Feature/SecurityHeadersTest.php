<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_hsts_header_exists(): void
    {
        $response = $this->get('/');
        $response->assertHeader('Strict-Transport-Security');
    }

    public function test_x_content_type_options_nosniff(): void
    {
        $response = $this->get('/');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_x_frame_options_header(): void
    {
        $response = $this->get('/');
        $this->assertContains(
            $response->headers->get('X-Frame-Options'),
            ['DENY', 'SAMEORIGIN']
        );
    }

    public function test_content_security_policy_header_exists(): void
    {
        $response = $this->get('/');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_referrer_policy_header_exists(): void
    {
        $response = $this->get('/');
        $response->assertHeader('Referrer-Policy');
    }

    public function test_x_xss_protection_header_exists(): void
    {
        $response = $this->get('/');
        $response->assertHeader('X-XSS-Protection');
    }

    public function test_server_header_is_hidden_in_api_response(): void
    {
        $response = $this->get('/api/config');
        $this->assertFalse($response->headers->has('Server'));
    }
}

