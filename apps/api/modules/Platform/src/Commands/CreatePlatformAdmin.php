<?php

namespace Modules\Platform\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Actions\SetPlatformAdminActive;
use Modules\Platform\Actions\SetPlatformAdminOwner;
use Modules\Platform\Contracts\ConsoleApi;
use Modules\Platform\Models\PlatformAdmin;

/**
 * Adds someone to the platform team (they set up their authenticator app on
 * first sign-in), or locks one out and lets them back in (--deactivate,
 * --reactivate: a demo account such as admin@platform.test before real
 * academies arrive, or someone who left), or makes one an owner, who alone
 * changes the platform's settings (--owner, --no-owner). The first admin of
 * a team with no owner who can sign in becomes one.
 */
final class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:admin {email} {name? : required when adding someone}
        {--password= : asked for when omitted}
        {--timezone=UTC : IANA zone the console shows times in}
        {--locale=ar : ar or en}
        {--deactivate : lock this admin out of the console and end their sessions}
        {--reactivate : let a locked admin sign in again}
        {--owner : make this admin an owner (the platform settings are theirs to change)}
        {--no-owner : this admin is no longer an owner}';

    protected $description = 'Add a platform admin (console at /console, two-factor required), lock one out (--deactivate) and back in (--reactivate), or make one an owner (--owner, --no-owner)';

    public function handle(SetPlatformAdminActive $setActive, SetPlatformAdminOwner $setOwner): int
    {
        // --owner with a name adds someone as an owner; without one it is about someone already on the team.
        $existing = $this->argument('name') === null || PlatformAdmin::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim((string) $this->argument('email')))])->exists();
        if ($existing && ($this->option('owner') || $this->option('no-owner')) && ! $this->option('deactivate') && ! $this->option('reactivate')) {
            if ($this->option('owner') && $this->option('no-owner')) {
                $this->error('Choose one: --owner or --no-owner.');

                return self::FAILURE;
            }
            try {
                $admin = $setOwner->handle((string) $this->argument('email'), owner: (bool) $this->option('owner'));
            } catch (ValidationException $e) {
                return $this->refused($e);
            }
            $this->info($admin->is_owner
                ? "{$admin->email} is an owner: the platform settings are theirs to change."
                : "{$admin->email} is no longer an owner.");

            return self::SUCCESS;
        }

        if ($this->option('deactivate') || $this->option('reactivate')) {
            if ($this->option('deactivate') && $this->option('reactivate')) {
                $this->error('Choose one: --deactivate or --reactivate.');

                return self::FAILURE;
            }
            try {
                $admin = $setActive->handle((string) $this->argument('email'), active: (bool) $this->option('reactivate'));
            } catch (ValidationException $e) {
                return $this->refused($e);
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
        if ($this->option('no-owner') && $this->option('owner')) {
            $this->error('Choose one: --owner or --no-owner.');

            return self::FAILURE;
        }
        $owner = $this->option('owner') || (! $this->option('no-owner') && ! PlatformAdmin::query()->where('is_owner', true)->where('is_active', true)->exists());
        $admin = PlatformAdmin::query()->create($data);
        $admin->forceFill(['is_owner' => $owner])->save();
        $this->info("Added {$data['email']}".($owner ? ' as an owner' : '').'. Sign in at '.app(ConsoleApi::class)->consoleUrl().' and set up the authenticator app.');

        return self::SUCCESS;
    }

    private function refused(ValidationException $e): int
    {
        foreach ($e->errors() as $messages) {
            foreach ($messages as $message) {
                $this->error($message);
            }
        }

        return self::FAILURE;
    }
}
