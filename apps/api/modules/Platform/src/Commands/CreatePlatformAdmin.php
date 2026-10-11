<?php

namespace Modules\Platform\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Actions\SetPlatformAdminActive;
use Modules\Platform\Contracts\ConsoleApi;
use Modules\Platform\Models\PlatformAdmin;

/**
 * Adds someone to the platform team (they set up their authenticator app on
 * first sign-in), or locks one out and lets them back in (--deactivate,
 * --reactivate: a demo account such as admin@platform.test before real
 * academies arrive, or someone who left).
 */
final class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:admin {email} {name? : required when adding someone}
        {--password= : asked for when omitted}
        {--timezone=UTC : IANA zone the console shows times in}
        {--locale=ar : ar or en}
        {--deactivate : lock this admin out of the console and end their sessions}
        {--reactivate : let a locked admin sign in again}';

    protected $description = 'Add a platform admin (console at /console, two-factor required), or lock one out (--deactivate) and back in (--reactivate)';

    public function handle(SetPlatformAdminActive $setActive): int
    {
        if ($this->option('deactivate') || $this->option('reactivate')) {
            if ($this->option('deactivate') && $this->option('reactivate')) {
                $this->error('Choose one: --deactivate or --reactivate.');

                return self::FAILURE;
            }
            try {
                $admin = $setActive->handle((string) $this->argument('email'), active: (bool) $this->option('reactivate'));
            } catch (ValidationException $e) {
                foreach ($e->errors() as $messages) {
                    foreach ($messages as $message) {
                        $this->error($message);
                    }
                }

                return self::FAILURE;
            }
            $this->info($admin->is_active
                ? "{$admin->email} can sign in to the console again."
                : "{$admin->email} is locked out of the console; their sessions end on their next request.");

            return self::SUCCESS;
        }

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
