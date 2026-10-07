<?php

namespace Modules\Platform\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Modules\Platform\Contracts\ConsoleApi;
use Modules\Platform\Models\PlatformAdmin;

/** Adds someone to the platform team; they set up their authenticator app on first sign-in. */
final class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:admin {email} {name} {--password= : asked for when omitted} {--timezone=UTC : IANA zone the console shows times in} {--locale=ar : ar or en}';

    protected $description = 'Add a platform admin (console at /console, two-factor required)';

    public function handle(): int
    {
        $password = $this->option('password') ?: (string) $this->secret('Password (12 characters or more)');
        $data = ['email' => $this->argument('email'), 'name' => $this->argument('name'), 'password' => $password, 'timezone' => $this->option('timezone'), 'locale' => $this->option('locale')];
        $check = Validator::make($data, [
            'email' => ['required', 'email', 'unique:pgsql_platform.platform_admins,email'],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:12'],
            'timezone' => ['required', 'timezone:all'],
            'locale' => ['required', 'in:ar,en'],
        ]);
        if ($check->fails()) {
            foreach ($check->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        PlatformAdmin::query()->create($data);
        $this->info("Added {$data['email']}. Sign in at ".app(ConsoleApi::class)->consoleUrl().' and set up the authenticator app.');

        return self::SUCCESS;
    }
}
