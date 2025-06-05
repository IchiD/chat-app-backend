<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SSRFTest extends TestCase
{
  public function test_internal_ip_is_blocked(): void
  {
    $res = $this->postJson('/api/external/fetch', ['url' => 'http://127.0.0.1']);
    $res->assertStatus(422);

    $res = $this->postJson('/api/external/fetch', ['url' => 'http://localhost']);
    $res->assertStatus(422);
  }

  public function test_private_ip_is_blocked(): void
  {
    $res = $this->postJson('/api/external/fetch', ['url' => 'http://192.168.1.1']);
    $res->assertStatus(422);

    $res = $this->postJson('/api/external/fetch', ['url' => 'http://10.0.0.1']);
    $res->assertStatus(422);
  }

  public function test_metadata_api_is_blocked(): void
  {
    $res = $this->postJson('/api/external/fetch', ['url' => 'http://169.254.169.254/latest/meta-data']);
    $res->assertStatus(422);
  }

  public function test_file_scheme_is_blocked(): void
  {
    $res = $this->postJson('/api/external/fetch', ['url' => 'file:///etc/passwd']);
    $res->assertStatus(422);
  }

  public function test_proxy_bypass_url_is_blocked(): void
  {
    $res = $this->postJson('/api/external/fetch', ['url' => 'http://127.0.0.1@evil.com']);
    $res->assertStatus(422);
  }

  public function test_redirect_chain_to_internal_ip_is_blocked(): void
  {
    Http::fake([
      'http://example.com/redirect' => Http::response('', 302, ['Location' => 'http://127.0.0.1']),
    ]);

    $res = $this->postJson('/api/external/fetch', ['url' => 'http://example.com/redirect']);
    $res->assertStatus(422);
  }

  public function test_valid_external_url_is_allowed(): void
  {
    Http::fake([
      'http://example.com/data' => Http::response('ok', 200),
    ]);

    $res = $this->postJson('/api/external/fetch', ['url' => 'http://example.com/data']);
    $res->assertOk()->assertJson(['status' => 'ok', 'final_url' => 'http://example.com/data']);
  }
}
