<?php

namespace Modules\Platform\Database\Seeders;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Actions\Modules\SaveAcademyModules;
use Modules\Platform\Models\AcademyModules;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Plans\Feature;
use RuntimeException;

/**
 * The portal's end-to-end tests hide and show the demo's optional areas as its
 * owner would (Settings › Academy modules, SaveAcademyModules, audited);
 * E2eResetSeeder calls these inside the demo academy.
 */
final class E2eModules
{
    /**
     * `games=0,messaging=0` hides those, `games=1` shows one again; areas not named stay as they are.
     *
     * @return list<Feature> the areas hidden now
     */
    public static function set(string $spec): array
    {
        $on = [];
        foreach (array_filter(explode(',', $spec)) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            if (Feature::tryFrom($name) === null || ! in_array($value, ['0', '1'], true)) {
                throw new RuntimeException("Not an area=0|1: {$pair}.");
            }
            $on[$name] = $value === '1';
        }

        return self::asOwner(fn () => app(SaveAcademyModules::class)->handle($on, self::ownerId()));
    }

    /** Every optional area the demo hid shows again. */
    public static function showAll(): void
    {
        $hidden = AcademyModules::current()->hiddenFeatures();
        if ($hidden !== []) {
            self::asOwner(fn () => app(SaveAcademyModules::class)->handle(array_fill_keys(array_map(fn (Feature $f) => $f->value, $hidden), true), self::ownerId()));
        }
    }

    private static function ownerId(): string
    {
        return (string) DB::table('users')->where('email', 'owner@demo.test')->value('id');
    }

    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private static function asOwner(callable $work): mixed
    {
        // The guard's own model, never Identity's internals.
        $accounts = (string) config('auth.providers.users.model');
        $owner = $accounts::query()->findOrFail(self::ownerId());
        $before = Auth::user();
        Auth::setUser($owner);
        $outside = AuditActor::current()->id === null;
        if ($outside) {
            AuditActor::use(new AuditActor('user', (string) $owner->getKey(), (string) $owner->name));
        }
        try {
            return $work();
        } finally {
            if ($outside) {
                AuditActor::forget();
            }
            $before !== null ? Auth::setUser($before) : Auth::forgetUser();
        }
    }
}
