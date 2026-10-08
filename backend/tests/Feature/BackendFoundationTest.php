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

    public function test_the_public_health_check_stays_stateless_for_the_spa_without_database_access(): void
    {
        config([
            'sanctum.stateful' => ['pet-care-tracker.ddev.site:5173'],
            'session.driver' => 'database',
            'database.connections.mariadb.host' => 'unavailable.invalid',
        ]);

        $this->withHeader('Origin', 'https://pet-care-tracker.ddev.site:5173')
            ->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok'])
            ->assertCookieMissing(config('session.cookie'));
    }

    public function test_the_api_requires_authentication_for_the_current_user(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }
}
