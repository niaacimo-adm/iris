<x-layouts::auth :title="__('Verify your email')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Check your email')"
            :description="__('We sent a 6-digit code to :email. Enter it to finish logging in.', ['email' => $maskedEmail])"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.otp.verify') }}" class="flex flex-col gap-6">
            @csrf

            <div class="flex justify-center">
                <flux:otp name="code" length="6" label="Verification code" label:sr-only autofocus />
            </div>

            @error('code')
                <flux:text class="text-center !text-red-600">{{ $message }}</flux:text>
            @enderror

            <flux:button variant="primary" type="submit" class="w-full" data-test="login-otp-button">
                {{ __('Verify and continue') }}
            </flux:button>
        </form>

        <div class="flex items-center justify-between text-sm">
            <form method="POST" action="{{ route('login.otp.resend') }}">
                @csrf
                <flux:button variant="ghost" size="sm" type="submit">{{ __('Resend code') }}</flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button variant="ghost" size="sm" type="submit">{{ __('Cancel and log out') }}</flux:button>
            </form>
        </div>
    </div>
</x-layouts::auth>
