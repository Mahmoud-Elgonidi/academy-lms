<?php

namespace Modules\Identity\Http\V1\Requests;

use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Str;
use Modules\SharedKernel\Tenancy\TenantContext;

final class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }

    /** Five attempts per minute for each academy + login + IP. */
    public function ensureIsNotRateLimited(): void
    {
        $limiter = app(RateLimiter::class);

        if (! $limiter->tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = $limiter->availableIn($this->throttleKey());

        throw new ThrottleRequestsException(__('identity::auth.throttle', ['seconds' => $seconds]), null, ['Retry-After' => $seconds]);
    }

    public function hitRateLimiter(): void
    {
        app(RateLimiter::class)->hit($this->throttleKey(), 60);
    }

    public function clearRateLimiter(): void
    {
        app(RateLimiter::class)->clear($this->throttleKey());
    }

    private function throttleKey(): string
    {
        return 'login:'.app(TenantContext::class)->id().':'.Str::lower($this->string('login')).'|'.$this->ip();
    }
}
