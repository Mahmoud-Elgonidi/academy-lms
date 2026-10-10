<?php

namespace Modules\Messaging;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Messaging\Domain\ContactMasker;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\MessagingSettings;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Who talks to whom (docs/architecture/02, section 17). A teacher and a
 * student they teach (a running course, or a lesson within a month either
 * way: a trial, a substitute's), with the student's guardians always in it.
 * They write while the academy has messages on, has not locked the
 * conversation, they still teach together, and the teacher may teach the
 * student (a teacher kept from minors writes to none). Otherwise the
 * conversation stays readable.
 */
final class MessagingRules
{
    /** A lesson this close (before or after) makes a teacher and a student talk. */
    public const LESSON_DAYS = 30;

    public const MAX_LENGTH = 2000;

    /** One person sends at most this many a minute. */
    public const PER_MINUTE = 20;

    /** @var array<string, list<string>> */
    private array $holders = [];

    /** @var array<string, list<string>> */
    private array $guardians = [];

    public function __construct(
        private readonly IdentityApi $identity,
        private readonly FamilyView $family,
        private readonly PeopleApi $people,
        private readonly TenantContext $tenant,
    ) {}

    public function enabled(): bool
    {
        return MessagingSettings::enabled();
    }

    public function isTeacher(string $userId): bool
    {
        return in_array($userId, $this->holders[Ability::WriteReports->value] ??= $this->identity->usersWithAbility(Ability::WriteReports), true);
    }

    /** @return list<string> who reads every conversation (conversations.oversee) */
    public function overseers(): array
    {
        return $this->holders[Ability::OverseeConversations->value] ??= $this->identity->usersWithAbility(Ability::OverseeConversations);
    }

    /** @return list<string> */
    public function guardiansOf(string $studentId): array
    {
        return $this->guardians[$studentId] ??= $this->people->guardiansOf($studentId);
    }

    /** teacher | student | guardian: the person's place in it; null when they are not in it. */
    public function sideOf(Conversation $c, string $userId): ?string
    {
        return match (true) {
            $userId === $c->teacher_id => 'teacher',
            $userId === $c->student_id => 'student',
            in_array($userId, $this->guardiansOf($c->student_id), true) => 'guardian',
            default => null,
        };
    }

    /** @return list<string> everyone in it: the teacher, the student, each guardian */
    public function members(Conversation $c): array
    {
        return array_values(array_unique([$c->teacher_id, $c->student_id, ...$this->guardiansOf($c->student_id)]));
    }

    /** Why this member cannot write now (off | locked | ended | paused), or null when they can. */
    public function whyNot(Conversation $c, ?CarbonImmutable $now = null): ?string
    {
        return match (true) {
            ! $this->enabled() => 'off',
            $c->isLocked() => 'locked',
            ! $this->teaches($c->teacher_id, $c->student_id, $now) => 'ended',
            ! $this->people->mayTeach($c->teacher_id, $c->student_id) => 'paused',
            default => null,
        };
    }

    public function teaches(string $teacherId, string $studentId, ?CarbonImmutable $now = null): bool
    {
        return $this->pairs($now)->where('teacher_id', $teacherId)->where('student_id', $studentId)->exists();
    }

    /**
     * The students a teacher teaches now, by name.
     *
     * @return array<string, string> id => name
     */
    public function studentsOf(string $teacherId, ?CarbonImmutable $now = null): array
    {
        return $this->named($this->pairs($now)->where('teacher_id', $teacherId), 'student_id');
    }

    /**
     * A student's teachers now, by name.
     *
     * @return array<string, string> id => name
     */
    public function teachersOf(string $studentId, ?CarbonImmutable $now = null): array
    {
        return $this->named($this->pairs($now)->where('student_id', $studentId), 'teacher_id');
    }

    /** The words a family member's screens speak in: a child's (kids), a student's about themselves (middle, teen), else the classic ones. */
    public function look(string $userId): string
    {
        return match ($this->family->stage($userId)) {
            LearnerStage::Kids => 'kids',
            LearnerStage::Middle, LearnerStage::Teen => 'student',
            default => 'classic',
        };
    }

    /** Hides contacts from a message; links to the academy's own pages stay. */
    public function masker(): ContactMasker
    {
        $hosts = [parse_url((string) config('app.url'), PHP_URL_HOST)];
        if (($academy = $this->tenant->current()) !== null) {
            $hosts[] = parse_url(app(TenantDirectory::class)->baseUrl($academy), PHP_URL_HOST);
        }
        if (app()->bound('request') && ! app()->runningInConsole()) {
            $hosts[] = request()->getHost();
        }

        return new ContactMasker(array_values(array_unique(array_filter(array_map(fn ($h) => is_string($h) ? strtolower($h) : null, $hosts)))));
    }

    /** Teacher and student pairs who teach together now: a running course, or a lesson within LESSON_DAYS either way. */
    private function pairs(?CarbonImmutable $now): Builder
    {
        $now ??= CarbonImmutable::now();
        $courses = DB::table('enrollments')->whereIn('status', ['active', 'pending_payment', 'paused'])
            ->select(['teacher_id', 'student_id']);
        $lessons = DB::table('sessions as s')
            ->join('session_participants as p', fn ($j) => $j->on('p.academy_id', '=', 's.academy_id')->on('p.session_id', '=', 's.id')->where('p.role', '=', 'student'))
            ->whereBetween('s.starts_at', [$now->subDays(self::LESSON_DAYS), $now->addDays(self::LESSON_DAYS)])
            ->where('s.status', '<>', 'cancelled')
            ->select(['s.teacher_id', 'p.user_id as student_id']);

        return DB::query()->fromSub($courses->union($lessons), 'pairs');
    }

    /** @return array<string, string> */
    private function named(Builder $pairs, string $column): array
    {
        return DB::query()->fromSub($pairs->select($column)->distinct(), 'x')
            ->join('users as u', 'u.id', '=', 'x.'.$column)
            ->where('u.status', 'active')
            ->orderBy('u.name')->pluck('u.name', 'u.id')->all();
    }
}
