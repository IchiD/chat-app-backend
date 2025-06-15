<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppConfigTest extends TestCase
{
  use RefreshDatabase;

  public function test_get_public_config_returns_expected_structure(): void
  {
    config(['webpush.vapid.public_key' => 'test_vapid_key']);
    config(['app.name' => 'ChatAppTest']);
    config(['app.url' => 'http://localhost']);
    config(['app.env' => 'testing']);

    $response = $this->getJson('/api/config');

    $response->assertOk()
      ->assertJson([
        'vapid' => ['publicKey' => 'test_vapid_key'],
        'env' => [
          'app_name' => 'ChatAppTest',
          'app_url' => 'http://localhost',
          'app_env' => 'testing',
        ],
      ]);
  }
}
