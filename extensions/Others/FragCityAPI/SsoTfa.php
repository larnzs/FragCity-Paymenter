<?php

namespace Paymenter\Extensions\Others\FragCityAPI;

use App\Actions\Auth\Login;
use App\Livewire\Component;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

class SsoTfa extends Component
{
    public string $code = '';

    public function mount()
    {
        $session = Session::get('2fa');
        if (!$session || ($session['expires'] ?? now()->subMinute()) < now()) {
            Session::forget(['2fa', 'fragcity_sso_2fa_return_to']);

            return $this->redirect(route('login'), true);
        }
    }

    public function verify(Login $loginAction)
    {
        $this->validate([
            'code' => 'required|numeric',
        ]);

        $session = Session::get('2fa');

        if (!$session || ($session['expires'] ?? now()->subMinute()) < now()) {
            Session::forget(['2fa', 'fragcity_sso_2fa_return_to']);
            return $this->redirect(route('login'), true);
        }

        $rateLimitKey = '2fa:' . $session['user_id'];
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $this->addError('code', 'Too many attempts.');

            return;
        }

        RateLimiter::increment($rateLimitKey);

        $user = User::findOrFail($session['user_id']);
        if (!$user->tfa_secret) {
            Session::forget(['2fa', 'fragcity_sso_2fa_return_to']);
            return $this->redirect(route('login'), true);
        }

        $tfa = new TwoFactorAuth(new EndroidQrCodeProvider, config('app.name'));
        if (!$tfa->verifyCode($user->tfa_secret, $this->code)) {
            $this->addError('code', 'Invalid code.');

            return;
        }

        // Match Paymenter's native one-time-code replay protection.
        $usedCodeKey = 'tfa_used_code_' . $user->id . '_' . $this->code;
        if (Cache::has($usedCodeKey)) {
            $this->addError('code', 'Invalid code.');

            return;
        }
        Cache::put($usedCodeKey, true, 60);

        // The native login action creates the same Paymenter UserSession and
        // remember-me cookie used by the normal login flow.
        $loginAction->execute($user, (bool) ($session['remember'] ?? false));

        Session::forget('2fa');
        RateLimiter::clear($rateLimitKey);

        $destination = $this->safeLocalDestination(
            Session::pull('fragcity_sso_2fa_return_to', route('home'))
        );

        return $this->redirect($destination, true);
    }

    private function safeLocalDestination(mixed $destination): string
    {
        if (!is_string($destination) || $destination === '' || strlen($destination) > 1024) {
            return route('home');
        }

        $parts = parse_url($destination);
        if (
            !str_starts_with($destination, '/')
            || str_starts_with($destination, '//')
            || str_contains($destination, chr(92))
            || preg_match('/[\r\n\x00-\x1F\x7F]/', $destination)
            || !is_array($parts)
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return route('home');
        }

        return $destination;
    }

    public function render()
    {
        return view('auth.fragcity-sso-tfa');
    }
}
