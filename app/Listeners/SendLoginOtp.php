<?php

namespace App\Listeners;

use App\Services\LoginOtp;
use Illuminate\Auth\Events\Login;

/**
 * Fires after any successful login (password, 2FA, passkey).
 * Marks the session as "OTP pending" and emails the code.
 */
class SendLoginOtp
{
    public function __construct(private LoginOtp $otp) {}

    public function handle(Login $event): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        session([LoginOtp::SESSION_PENDING => true]);

        $this->otp->send($event->user);
    }
}
