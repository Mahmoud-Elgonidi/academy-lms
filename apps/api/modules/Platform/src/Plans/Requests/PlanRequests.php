<?php

namespace Modules\Platform\Plans\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Health\AlertRecipients;
use Modules\Platform\Models\SaasSubscription;
use Modules\Platform\Plans\PlanWarning;
use Modules\Platform\Plans\PlatformAcademyPlan;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Plans\UsagePeriod;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantRef;

/**
 * What the academy's requests about its plan (an extra pack, a bigger plan)
 * share: who may ask (whoever sees the plan: owner and admins, never while
 * the platform team is signed in as someone), the academy's month, the mail
 * to the platform team and the answer back to whoever asked.
 */
final class PlanRequests
{
    public function __construct(private readonly TenantContext $tenants) {}

    /** @throws AuthorizationException|ValidationException */
    public function authorizeAcademy(): TenantRef
    {
        if (! $this->tenants->check() || ! Gate::allows(Ability::ViewPlan->value)) {
            throw new AuthorizationException;
        }
        if (AuditActor::current()->onBehalfOf !== null) {
            throw ValidationException::withMessages(['plan_request' => __('platform::topups.errors.impersonating')]);
        }
        $academy = $this->tenants->require();
        if (SaasSubscription::currentFor($academy->id) === null) {
            throw ValidationException::withMessages(['plan_request' => __('platform::topups.errors.no_plan')]);
        }

        return $academy;
    }

    /** The academy's calendar month now (its first day: the key a count and a pack are kept under). */
    public static function monthKey(string $timezone, ?CarbonImmutable $at = null): string
    {
        return UsagePeriod::monthOf($at ?? CarbonImmutable::now(), $timezone)->key($timezone);
    }

    /** The day the month after it starts, on the academy's clock, as people read it. */
    public static function renews(string $timezone, ?string $locale = null): string
    {
        return UsagePeriod::monthOf(CarbonImmutable::now(), $timezone)->to->setTimezone($timezone)
            ->locale($locale ?? app()->getLocale())->translatedFormat('j F');
    }

    /** To the platform team, once the request is kept (call it after the transaction). */
    public static function tellTeam(PlanRequestMail $mail): void
    {
        $to = AlertRecipients::all();
        if ($to !== []) {
            Mail::to($to)->queue($mail);
        }
    }

    /** To whoever asked, in their language, once the platform team answered (after the transaction; nothing for an account with no email). */
    public static function tellRequester(string $academyId, ?string $userId, callable $mail): void
    {
        if ($userId === null) {
            return;
        }
        $user = DB::connection('pgsql_platform')->table('users')->where('academy_id', $academyId)->where('id', $userId)->first(['email', 'locale']);
        if ($user === null || ! filled($user->email)) {
            return;
        }
        Mail::to($user->email)->queue($mail(in_array($user->locale, ['ar', 'en'], true) ? $user->locale : 'ar'));
    }

    /** The plan and its warning are read again after a change. */
    public static function forget(string $academyId): void
    {
        app(PlatformAcademyPlan::class)->forget();
        app(PlanWarning::class)->forget($academyId);
    }
}
