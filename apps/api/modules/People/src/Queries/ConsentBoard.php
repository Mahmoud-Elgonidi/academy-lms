<?php

namespace Modules\People\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\People\Models\ConsentSubject;
use Modules\People\Models\PeopleSettings;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The office's list of students and their guardians' consent: waiting (never
 * given), withdrawn (taken back, waiting again), and agreed. "Waiting" follows
 * the same rule as ConsentRules, in SQL: younger than the consent age on the
 * academy's calendar, or of unknown age and managed for a guardian or with one.
 */
final class ConsentBoard
{
    public const TABS = ['waiting', 'withdrawn', 'granted'];

    public function __construct(private readonly TenantContext $tenants) {}

    /** @return Builder<ConsentSubject> */
    public function query(string $tab, ?CarbonImmutable $now = null): Builder
    {
        $live = 'select 1 from guardian_consents c where c.academy_id = users.academy_id and c.student_id = users.id and c.withdrawn_at is null';
        $withdrawn = 'select 1 from guardian_consents c where c.academy_id = users.academy_id and c.student_id = users.id and c.withdrawn_at is not null';
        $query = $this->base($now);

        return match ($tab) {
            'granted' => $query->whereRaw("exists ({$live})"),
            'withdrawn' => $query->whereRaw("not exists ({$live})")->whereRaw("exists ({$withdrawn})")->where(fn ($q) => $this->needed($q, $now)),
            default => $query->whereRaw("not exists ({$live})")->whereRaw("not exists ({$withdrawn})")->where(fn ($q) => $this->needed($q, $now)),
        };
    }

    /** @return array<string, int> */
    public function counts(?CarbonImmutable $now = null): array
    {
        return collect(self::TABS)->mapWithKeys(fn (string $tab) => [$tab => $this->query($tab, $now)->count()])->all();
    }

    /** @return Builder<ConsentSubject> */
    private function base(?CarbonImmutable $now): Builder
    {
        return ConsentSubject::query()
            ->leftJoin('student_profiles as sp', fn ($j) => $j->on('sp.user_id', '=', 'users.id')->on('sp.academy_id', '=', 'users.academy_id'))
            ->whereRaw("exists (select 1 from user_roles r where r.academy_id = users.academy_id and r.user_id = users.id and r.role = 'student')")
            ->where('users.status', 'active')
            ->select('users.id', 'users.name', 'users.academy_id', 'sp.birth_date')
            ->selectRaw("(select string_agg(gu.name, ', ' order by gu.name) from guardian_student g join users gu on gu.academy_id = g.academy_id and gu.id = g.guardian_id where g.academy_id = users.academy_id and g.student_id = users.id) as guardians")
            ->selectRaw('(select count(*) from guardian_student g where g.academy_id = users.academy_id and g.student_id = users.id) as guardian_count')
            ->selectRaw('(select c.granted_at from guardian_consents c where c.academy_id = users.academy_id and c.student_id = users.id and c.withdrawn_at is null) as granted_at')
            ->selectRaw('(select c.via from guardian_consents c where c.academy_id = users.academy_id and c.student_id = users.id and c.withdrawn_at is null) as via')
            ->selectRaw('(select max(c.withdrawn_at) from guardian_consents c where c.academy_id = users.academy_id and c.student_id = users.id) as withdrawn_at');
    }

    /** @param  Builder<ConsentSubject>  $query */
    private function needed(Builder $query, ?CarbonImmutable $now): void
    {
        $cutoff = ($now ?? CarbonImmutable::now())->setTimezone($this->tenants->require()->defaultTimezone)->startOfDay()->subYears(PeopleSettings::current()->consent_age)->toDateString();
        $query->where(fn ($q) => $q->where(fn ($q) => $q->whereNotNull('sp.birth_date')->where('sp.birth_date', '>', $cutoff))
            ->orWhere(fn ($q) => $q->whereNull('sp.birth_date')->where(fn ($q) => $q->where('users.is_managed_minor', true)
                ->orWhereRaw('exists (select 1 from guardian_student g where g.academy_id = users.academy_id and g.student_id = users.id)'))));
    }
}
