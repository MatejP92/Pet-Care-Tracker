<?php

namespace Tests\Feature;

use Tests\TestCase;

class BackendFoundationTest extends TestCase
{
    public function test_the_public_api_health_response_is_minimal_and_not_cached(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok'])
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_the_framework_health_route_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_the_api_requires_authentication_for_the_current_user(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }
}
