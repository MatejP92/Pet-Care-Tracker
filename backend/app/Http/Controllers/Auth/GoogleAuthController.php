<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->frontend('unavailable');
        }

        return Socialite::driver('google')
            ->setScopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->frontend('unavailable');
        }

        if ($request->has('error')) {
            $expectedState = $request->session()->pull('state');
            $state = $request->query('state');
            $validState = is_string($expectedState) && $expectedState !== ''
                && is_string($state) && hash_equals($expectedState, $state);

            return $this->frontend($validState && $request->query('error') === 'access_denied'
                ? 'cancelled' : 'failed');
        }

        try {
            // Socialite checks and consumes the session's OAuth state before exchanging the code.
            $identity = Socialite::driver('google')->user();
            $profile = $identity->getRaw();
            $googleId = $identity->getId();
            $email = Str::lower(trim((string) $identity->getEmail()));

            if (! is_string($googleId) || $googleId === '' || strlen($googleId) > 255
                || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255
                || ($profile['email_verified'] ?? $profile['verified_email'] ?? false) !== true) {
                return $this->frontend('failed');
            }

            $user = User::where('google_id', $googleId)->first();

            // Email is contact information, never proof that two identities belong to one person.
            if (User::where('email', $email)->when($user, fn ($query) => $query->whereKeyNot($user->id))->exists()) {
                return $this->frontend('account_conflict');
            }

            $name = Str::limit(trim((string) $identity->getName()) ?: $email, 255, '');

            $user ??= User::firstOrCreate(['google_id' => $googleId], [
                'name' => $name,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => null,
            ]);

            $user->forceFill(['name' => $name, 'email' => $email, 'email_verified_at' => now()])->save();

            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            return $this->frontend();
        } catch (InvalidStateException) {
            return $this->frontend('failed');
        } catch (UniqueConstraintViolationException) {
            return $this->frontend('account_conflict');
        } catch (Exception $exception) {
            // Provider error bodies may contain tokens; log only the exception type.
            Log::warning('Google sign-in failed.', ['exception_type' => $exception::class]);

            return $this->frontend('failed');
        }
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    private function frontend(?string $error = null): RedirectResponse
    {
        // Only a server-configured destination is allowed; never use a client-supplied return URL.
        $url = rtrim(config('app.frontend_url'), '/').'/';

        if ($error !== null) {
            $url .= '?auth_error='.$error;
        }

        return redirect()->away($url)->header('Cache-Control', 'no-store');
    }
}
