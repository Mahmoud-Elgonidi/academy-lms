<?php

namespace Modules\Communication\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Communication\Models\PushDevice;

/** The app registers its push token after sign-in; a token moves to whoever signed in last on that device. */
final class RegisterPushDevice
{
    public function handle(string $userId, string $platform, string $token, ?string $appVersion): PushDevice
    {
        Validator::make(['platform' => $platform, 'token' => $token], [
            'platform' => ['required', Rule::in(PushDevice::PLATFORMS)],
            'token' => ['required', 'string', 'max:500'],
        ])->validate();

        return PushDevice::query()->updateOrCreate(['token' => $token], [
            'user_id' => $userId, 'platform' => $platform, 'app_version' => $appVersion, 'last_seen_at' => CarbonImmutable::now(),
        ]);
    }
}
