<?php

namespace Modules\People\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\People\Actions\Consent\GrantConsent;
use Modules\People\Consent\ConsentRules;
use Modules\People\Models\GuardianStudent;
use Modules\People\Models\PeopleSettings;
use Modules\Platform\Contracts\BrandingApi;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The page behind the link in a consent request: what the academy keeps
 * about the child and why, and "I agree". On the academy's host, in the
 * guardian's language, without signing in (the link is signed for that
 * guardian and that child, for two weeks).
 */
final class ConsentLinkController
{
    public const LINK_DAYS = 14;

    public function show(Request $request, string $student, ConsentRules $rules, BrandingApi $branding, TenantContext $tenants): View
    {
        $guardianId = (string) $request->query('g');
        $this->speak($guardianId, $tenants);
        $linked = $guardianId !== '' && GuardianStudent::query()->where('guardian_id', $guardianId)->where('student_id', $student)->exists();
        $state = $linked ? $rules->state($student) : null;
        $brand = $branding->current();
        $tz = DB::table('users')->where('id', $guardianId)->value('timezone') ?? $tenants->require()->defaultTimezone;

        return view('people::consent.link', [
            'linked' => $linked,
            'state' => $state,
            'student' => $linked ? (string) DB::table('users')->where('id', $student)->value('name') : '',
            'academy' => $brand->name(),
            'age' => PeopleSettings::current()->consent_age,
            'saved' => $request->boolean('saved'),
            'since' => $state?->grantedAt?->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F Y'),
            'action' => URL::temporarySignedRoute('people.consent.store', now()->addHours(12), ['student' => $student, 'g' => $guardianId], absolute: false),
            'branding' => $brand,
        ]);
    }

    public function store(Request $request, string $student, GrantConsent $grant, TenantContext $tenants): RedirectResponse
    {
        $guardianId = (string) $request->query('g');
        $this->speak($guardianId, $tenants);
        $request->validate(['agree' => ['accepted']], ['agree.accepted' => __('people::consent.link.tick')]);
        $grant->handle($student, $guardianId, 'link', $request->ip(), $request->userAgent());

        return redirect(URL::temporarySignedRoute('people.consent', CarbonImmutable::now()->addDays(self::LINK_DAYS), ['student' => $student, 'g' => $guardianId, 'saved' => 1], absolute: false));
    }

    private function speak(string $guardianId, TenantContext $tenants): void
    {
        // A link always names its guardian, someone of this academy.
        $user = Str::isUuid($guardianId) ? DB::table('users')->where('id', $guardianId)->first(['locale']) : null;
        abort_if($user === null, 404);
        app()->setLocale($user->locale ?: $tenants->require()->defaultLocale);
    }
}
