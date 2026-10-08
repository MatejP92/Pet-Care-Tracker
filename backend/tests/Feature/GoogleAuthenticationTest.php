<?php

namespace Tests\Feature;

use App\Models\User;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private MockHandler $google;

    private array $requests = [];

    private const FRONTEND = 'https://pet-care-tracker.ddev.site:5173';

    private const CALLBACK = 'https://pet-care-tracker.ddev.site/auth/google/callback';

    protected function setUp(): void
    {
        parent::setUp();

        $this->google = new MockHandler;
        $handler = HandlerStack::create($this->google);
        $handler->push(Middleware::history($this->requests));

        config([
            'app.frontend_url' => self::FRONTEND,
            'services.google' => [
                'client_id' => 'synthetic-client-id',
                'client_secret' => 'synthetic-client-secret',
                'redirect' => self::CALLBACK,
                'guzzle' => ['handler' => $handler],
            ],
            'sanctum.stateful' => ['pet-care-tracker.ddev.site:5173'],
            'session.secure' => true,
        ]);
    }

    public function test_redirect_uses_google_with_minimal_scopes_and_a_session_bound_state(): void
    {
        $response = $this->get('/auth/google/redirect?return_url=https://untrusted.example');
        $response->assertRedirect();

        $url = parse_url($response->headers->get('Location'));
        parse_str($url['query'], $parameters);

        $this->assertSame('https', $url['scheme']);
        $this->assertSame('accounts.google.com', $url['host']);
        $this->assertSame(self::CALLBACK, $parameters['redirect_uri']);
        $this->assertSame('openid profile email', $parameters['scope']);
        $this->assertSame('code', $parameters['response_type']);
        $this->assertGreaterThanOrEqual(40, strlen($parameters['state']));
        $response->assertSessionHas('state', $parameters['state']);
        $this->assertCount(0, $this->requests);
    }

    public function test_missing_configuration_returns_a_safe_error_without_contacting_google(): void
    {
        config(['services.google.client_secret' => null]);

        $this->get('/auth/google/redirect')->assertRedirect(self::FRONTEND.'/?auth_error=unavailable');
        $this->get('/auth/google/callback')->assertRedirect(self::FRONTEND.'/?auth_error=unavailable');
        $this->assertCount(0, $this->requests);
        $this->assertGuest();
    }

    public function test_verified_google_identity_creates_a_passwordless_user_and_authenticates_a_cookie_session(): void
    {
        config(['session.driver' => 'database']);
        $this->queueProfile();

        $response = $this->completeLogin();
        $response->assertRedirect(self::FRONTEND.'/')->assertHeader('Cache-Control', 'no-store, private');

        $user = User::sole();
        $this->assertSame('google-subject-123', $user->google_id);
        $this->assertSame('care@example.test', $user->email);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertCount(2, $this->requests);
        $this->assertSame('www.googleapis.com', $this->requests[0]['request']->getUri()->getHost());
        $this->assertSame('Bearer synthetic-access-token', $this->requests[1]['request']->getHeaderLine('Authorization'));
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $cookie = $response->getCookie(config('session.cookie'));
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertNull($cookie->getDomain() ?: null);

        $this->resetSessionServices();
        $this->withCredentials()->withCookie($cookie->getName(), $cookie->getValue())
            ->withHeader('Origin', self::FRONTEND)
            ->getJson('/api/user')
            ->assertOk()
            ->assertExactJson(['id' => $user->id, 'name' => 'Synthetic Owner', 'email' => 'care@example.test'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_returning_identity_keeps_the_same_user_even_when_its_email_changes(): void
    {
        $user = User::factory()->create(['google_id' => 'google-subject-123', 'email' => 'old@example.test']);
        $this->queueProfile(['email' => 'new@example.test', 'name' => 'Updated Owner']);

        $this->completeLogin()->assertRedirect(self::FRONTEND.'/');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('new@example.test', $user->fresh()->email);
        $this->assertSame('Updated Owner', $user->fresh()->name);
    }

    public function test_an_untrusted_origin_cannot_authenticate_to_the_api_using_a_session_cookie(): void
    {
        config(['session.driver' => 'database']);
        $this->queueProfile();
        $login = $this->completeLogin();
        $cookie = $login->getCookie(config('session.cookie'));

        $this->resetSessionServices();
        $this->withCredentials()->withCookie($cookie->getName(), $cookie->getValue())
            ->withHeader('Origin', 'https://untrusted.example')
            ->getJson('/api/user')->assertUnauthorized();
    }

    public function test_an_existing_email_never_links_a_new_google_identity_to_an_account(): void
    {
        $user = User::factory()->create(['email' => 'care@example.test']);
        $this->queueProfile();

        $this->completeLogin()->assertRedirect(self::FRONTEND.'/?auth_error=account_conflict');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_an_email_collision_cannot_switch_or_overwrite_another_google_account(): void
    {
        $owner = User::factory()->create(['google_id' => 'google-subject-123', 'email' => 'old@example.test']);
        $other = User::factory()->create(['google_id' => 'different-subject', 'email' => 'care@example.test']);
        $this->queueProfile();

        $this->completeLogin()->assertRedirect(self::FRONTEND.'/?auth_error=account_conflict');

        $this->assertGuest();
        $this->assertSame('old@example.test', $owner->fresh()->email);
        $this->assertSame('different-subject', $other->fresh()->google_id);
    }

    public function test_unverified_or_missing_email_and_missing_subject_are_rejected(): void
    {
        foreach ([['email_verified' => false], ['email' => null], ['sub' => null]] as $profile) {
            $this->queueProfile($profile);
            $this->completeLogin()->assertRedirect(self::FRONTEND.'/?auth_error=failed');
            $this->assertGuest();
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_or_missing_state_is_rejected_before_any_google_request(): void
    {
        $this->withSession(['state' => 'expected-state'])
            ->get('/auth/google/callback?state=wrong-state&code=synthetic-code')
            ->assertRedirect(self::FRONTEND.'/?auth_error=failed');
        Socialite::forgetDrivers();
        $this->get('/auth/google/callback?code=synthetic-code')
            ->assertRedirect(self::FRONTEND.'/?auth_error=failed');

        $this->assertCount(0, $this->requests);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_oauth_state_is_single_use(): void
    {
        $this->queueProfile();
        $this->completeLogin()->assertRedirect(self::FRONTEND.'/')->assertSessionMissing('state');
        Socialite::forgetDrivers();
        $this->get('/auth/google/callback?state=synthetic-state&code=synthetic-code')
            ->assertRedirect(self::FRONTEND.'/?auth_error=failed');

        $this->assertCount(2, $this->requests);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_cancellation_consumes_valid_state_without_creating_a_user(): void
    {
        $this->withSession(['state' => 'synthetic-state'])
            ->get('/auth/google/callback?state=synthetic-state&error=access_denied')
            ->assertRedirect(self::FRONTEND.'/?auth_error=cancelled')
            ->assertSessionMissing('state');

        $this->get('/auth/google/callback?state=synthetic-state&error=access_denied')
            ->assertRedirect(self::FRONTEND.'/?auth_error=failed');
        $this->assertGuest();
        $this->assertCount(0, $this->requests);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_provider_failures_do_not_disclose_the_provider_response_or_credentials(): void
    {
        $this->google->append(new Response(400, [], 'sensitive-provider-response-with-a-token'));

        $response = $this->completeLogin();
        $response->assertRedirect(self::FRONTEND.'/?auth_error=failed');
        $this->assertStringNotContainsString('sensitive-provider', $response->getContent());
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_logout_invalidates_the_cookie_session_and_regenerates_csrf(): void
    {
        config(['session.driver' => 'database']);
        $this->queueProfile();
        $login = $this->completeLogin();
        $cookie = $login->getCookie(config('session.cookie'));
        $token = session()->token();

        $this->resetSessionServices();
        $this->withCredentials()->withCookie($cookie->getName(), $cookie->getValue())
            ->postJson('/auth/logout')
            ->assertNoContent();

        $this->assertGuest();
        $this->assertNotSame($cookie->getValue(), session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->assertDatabaseMissing('sessions', ['id' => $cookie->getValue()]);
        $this->resetSessionServices();
        $this->withHeader('Origin', self::FRONTEND)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_logout_requires_post_and_an_authenticated_user(): void
    {
        $this->getJson('/auth/logout')->assertMethodNotAllowed();
        $this->postJson('/auth/logout')->assertUnauthorized();
    }

    public function test_logout_rejects_missing_csrf_and_accepts_a_valid_token(): void
    {
        $this->enableCsrfChecks();

        $this->actingAs(User::factory()->create())
            ->withSession(['_token' => 'synthetic-csrf-token'])
            ->withHeader('Sec-Fetch-Site', 'cross-site')
            ->postJson('/auth/logout')->assertStatus(419);
        $this->assertAuthenticated();

        $this->withHeader('X-CSRF-TOKEN', 'synthetic-csrf-token')
            ->postJson('/auth/logout')->assertNoContent();
        $this->assertGuest();
    }

    public function test_logout_accepts_the_encrypted_xsrf_cookie_header_used_by_the_frontend(): void
    {
        config(['session.driver' => 'database']);
        $this->enableCsrfChecks();
        $this->queueProfile();
        $cookie = $this->completeLogin()->getCookie(config('session.cookie'));

        $this->resetSessionServices();
        $csrf = $this->withCredentials()->withCookie($cookie->getName(), $cookie->getValue())
            ->getJson('/sanctum/csrf-cookie')->assertNoContent();

        $this->resetSessionServices();
        $this->withHeader('X-XSRF-TOKEN', $csrf->getCookie('XSRF-TOKEN', false)->getValue())
            ->postJson('/auth/logout')->assertNoContent();
        $this->assertGuest();
        $this->assertDatabaseMissing('sessions', ['id' => $cookie->getValue()]);
    }

    private function queueProfile(array $overrides = []): void
    {
        $this->google->append(
            new Response(200, [], json_encode(['access_token' => 'synthetic-access-token', 'expires_in' => 3600])),
            new Response(200, [], json_encode(array_replace([
                'sub' => 'google-subject-123',
                'name' => 'Synthetic Owner',
                'email' => 'care@example.test',
                'email_verified' => true,
            ], $overrides))),
        );
    }

    private function completeLogin()
    {
        Socialite::forgetDrivers();

        return $this->withSession(['state' => 'synthetic-state'])
            ->get('/auth/google/callback?state=synthetic-state&code=synthetic-code');
    }

    private function resetSessionServices(): void
    {
        Auth::forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }

    private function enableCsrfChecks(): void
    {
        // Laravel normally bypasses CSRF in tests. Exercise the real middleware decision here.
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }
}
