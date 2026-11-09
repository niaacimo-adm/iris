<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Emailed one-time code required after a successful login.
 * Codes are stored hashed in the cache (no migration needed).
 */
class LoginOtp
{
    public const SESSION_PENDING = 'login_otp_pending';
    public const TTL_MINUTES     = 10;
    public const MAX_ATTEMPTS    = 5;
    public const RESEND_SECONDS  = 60;

    public function send(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->key($user), [
            'hash'     => Hash::make($code),
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));

        Cache::put($this->key($user, 'sent'), true, now()->addSeconds(self::RESEND_SECONDS));

        $user->notify(new LoginOtpNotification($code, self::TTL_MINUTES));
    }

    public function canResend(User $user): bool
    {
        return ! Cache::has($this->key($user, 'sent'));
    }

    /** Returns true on success; false if wrong, expired or locked out. */
    public function verify(User $user, string $code): bool
    {
        $data = Cache::get($this->key($user));

        if (! $data || $data['attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($code, $data['hash'])) {
            $data['attempts']++;
            Cache::put($this->key($user), $data, now()->addMinutes(self::TTL_MINUTES));

            return false;
        }

        Cache::forget($this->key($user));

        return true;
    }

    public function attemptsLeft(User $user): int
    {
        $data = Cache::get($this->key($user));

        return $data ? max(0, self::MAX_ATTEMPTS - $data['attempts']) : 0;
    }

    private function key(User $user, string $suffix = 'code'): string
    {
        return "login_otp:{$user->getKey()}:{$suffix}";
    }
}
