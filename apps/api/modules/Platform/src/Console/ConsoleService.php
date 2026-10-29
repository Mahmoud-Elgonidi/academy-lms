<?php

namespace Modules\Platform\Console;

use Modules\Platform\Actions\Site\HandleSignupRequest;
use Modules\Platform\Contracts\ConsoleApi;
use Modules\Platform\Contracts\DTOs\PlatformAdminRef;
use Modules\Platform\Contracts\DTOs\SignupRequestRef;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\AcademyDomain;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Models\Site\SignupRequest;
use Modules\SharedKernel\Plans\Limit;

final class ConsoleService implements ConsoleApi
{
    public function currentAdmin(): ?PlatformAdminRef
    {
        $admin = auth()->guard('platform')->user();

        return $admin instanceof PlatformAdmin && $admin->is_active ? new PlatformAdminRef($admin->id, $admin->name, $admin->email) : null;
    }

    public function adminActive(string $adminId): bool
    {
        return PlatformAdmin::query()->whereKey($adminId)->where('is_active', true)->exists();
    }

    public function consoleUrl(string $path = ''): string
    {
        $base = config('platform.console_url') ?: rtrim((string) config('app.url'), '/').'/console';

        return rtrim($base, '/').($path === '' ? '' : '/'.ltrim($path, '/'));
    }

    public function offeredPlans(): array
    {
        return SaasPlan::query()->offered()->orderByDesc('is_default')->orderBy('sort')->orderBy('code')->get()
            ->mapWithKeys(fn (SaasPlan $plan) => [$plan->code => $plan->name()])->all();
    }

    public function offeredPlanLimits(): array
    {
        return SaasPlan::query()->offered()->orderByDesc('is_default')->orderBy('sort')->orderBy('code')->get()
            ->mapWithKeys(fn (SaasPlan $plan) => [$plan->code => [
                'teachers' => $plan->limit(Limit::Teachers),
                'active_students' => $plan->limit(Limit::ActiveStudents),
                'video_minutes' => $plan->limit(Limit::VideoMinutes),
                'storage_mb' => $plan->limit(Limit::StorageMb),
            ]])->all();
    }

    public function slugTaken(string $slug): bool
    {
        $slug = strtolower(trim($slug));

        return in_array($slug, (array) config('platform.reserved_slugs'), true)
            || Academy::query()->where('slug', $slug)->exists()
            || AcademyDomain::query()->where('host', $slug.'.'.config('platform.domain'))->exists();
    }

    public function signupRequest(string $id): ?SignupRequestRef
    {
        $request = SignupRequest::query()->find($id);

        return $request === null ? null : new SignupRequestRef(
            $request->id, $request->academy_name, $request->contact_name, $request->email, $request->phone,
            $request->country, $request->plan_code, $request->locale, $request->status,
        );
    }

    public function signupRequestCreated(string $id, string $academyId): void
    {
        $admin = auth()->guard('platform')->user();
        if ($admin instanceof PlatformAdmin && SignupRequest::query()->whereKey($id)->exists()) {
            app(HandleSignupRequest::class)->handle($admin, $id, 'created', null, $academyId);
        }
    }
}
