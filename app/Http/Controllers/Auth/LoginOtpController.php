<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\LoginOtp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginOtpController extends Controller
{
    public function __construct(private LoginOtp $otp) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get(LoginOtp::SESSION_PENDING)) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        return view('auth.login-otp', [
            'maskedEmail' => $this->mask($request->user()->email),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        $user = $request->user();

        if (! $this->otp->verify($user, $request->string('code')->toString())) {
            $left = $this->otp->attemptsLeft($user);

            return back()->withErrors([
                'code' => $left > 0
                    ? __('Invalid code. :n attempt(s) left.', ['n' => $left])
                    : __('Code expired or too many attempts. Request a new code.'),
            ]);
        }

        $request->session()->forget(LoginOtp::SESSION_PENDING);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function resend(Request $request): RedirectResponse
    {
        if (! $this->otp->canResend($request->user())) {
            return back()->withErrors([
                'code' => __('Please wait a minute before requesting another code.'),
            ]);
        }

        $this->otp->send($request->user());

        return back()->with('status', __('A new code was sent to your email.'));
    }

    private function mask(string $email): string
    {
        [$name, $domain] = explode('@', $email, 2);

        return Str::substr($name, 0, 2) . str_repeat('*', max(1, strlen($name) - 2)) . '@' . $domain;
    }
}
