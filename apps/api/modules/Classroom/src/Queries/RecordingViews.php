<?php

namespace Modules\Classroom\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Models\RecordingConsent;
use Modules\Classroom\Recordings\RecordingRules;
use Modules\Platform\Contracts\BrandingApi;

/**
 * Recordings and consent as a family or a teacher sees them, the same in
 * /my, /teach and the apps: one person's consent (whether it stands, who
 * gave it, whether the viewer may change it, and the words to agree to), and
 * the ready recordings of a learner's or a teacher's lessons.
 */
final class RecordingViews
{
    public function __construct(
        private readonly RecordingRules $rules,
        private readonly BrandingApi $branding,
    ) {}

    /**
     * @return array{person: array{id: string, name: string}, self: bool, state: string, can_change: bool, by: ?string, decides: ?string,
     *     granted_at: ?CarbonImmutable, text: array{version: int, title: string, intro: string, sections: list<array{title: string, body: string}>, agree: string}}
     */
    public function consent(string $viewerId, string $personId, string $tz): array
    {
        $name = (string) DB::table('users')->where('id', $personId)->value('name');
        $self = $viewerId === $personId;
        $live = RecordingConsent::liveFor($personId);
        $way = $this->rules->wayFor($viewerId, $personId);
        $child = ! $self || $this->rules->roleOf($personId) === 'student' && $this->rules->isChild($personId);
        $date = $live?->granted_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F Y');
        $l = 'classroom::recordings.consent.';

        return [
            'person' => ['id' => $personId, 'name' => $name],
            'self' => $self,
            'state' => $live ? 'agreed' : 'none',
            'can_change' => $way !== null,
            'by' => match (true) {
                $live === null => null,
                $live->via === 'staff' => __($l.'recorded', ['date' => $date]),
                $live->given_by === $viewerId => __($l.'given', ['date' => $date]),
                default => __($l.'given_by', ['name' => (string) DB::table('users')->where('id', $live->given_by)->value('name'), 'date' => $date]),
            },
            // Who decides, when the viewer cannot: a child's guardian, or an adult student themself.
            'decides' => $way !== null ? null : ($self ? __($l.'decides_guardian') : __($l.'decides_self', ['name' => $name])),
            'granted_at' => $live?->granted_at,
            'text' => $this->text($name, $self && ! $child),
        ];
    }

    /** @return list<string> whose consent the viewer sees: themself (when lessons record them), then the students they look after */
    public function people(string $viewerId): array
    {
        $own = $this->rules->roleOf($viewerId) !== null ? [$viewerId] : [];

        return array_values(array_unique([...$own, ...DB::table('guardian_student')->where('guardian_id', $viewerId)->orderBy('created_at')->pluck('student_id')->all()]));
    }

    /**
     * The ready recordings of a learner's lessons (field student) or a teacher's (field teacher), newest first.
     *
     * @return list<array{id: string, at: CarbonImmutable, teacher: string, student: ?string, media: string, duration_sec: int, expires_at: ?CarbonImmutable}>
     */
    public function recordings(string $field, string $personId, int $limit = 30): array
    {
        $query = DB::table('recordings as r')
            ->join('sessions as s', fn ($j) => $j->on('s.id', '=', 'r.session_id')->on('s.academy_id', '=', 'r.academy_id'))
            ->join('users as t', fn ($j) => $j->on('t.id', '=', 's.teacher_id')->on('t.academy_id', '=', 's.academy_id'))
            ->where('r.status', 'ready')
            ->select('r.id', 's.starts_at', 't.name as teacher', 'r.media', 'r.duration_sec', 'r.expires_at')
            ->selectRaw("(select u.name from session_participants p join users u on u.academy_id = p.academy_id and u.id = p.user_id where p.academy_id = s.academy_id and p.session_id = s.id and p.role = 'student' limit 1) as student")
            ->orderByDesc('s.starts_at')->orderByDesc('r.created_at')->limit($limit);
        $field === 'teacher'
            ? $query->where('s.teacher_id', $personId)
            : $query->whereExists(fn ($q) => $q->from('session_participants as p')->whereColumn('p.academy_id', 's.academy_id')->whereColumn('p.session_id', 's.id')->where('p.role', 'student')->where('p.user_id', $personId));

        return $query->get()->map(fn ($r) => [
            'id' => $r->id,
            'at' => CarbonImmutable::parse($r->starts_at),
            'teacher' => (string) $r->teacher,
            'student' => $r->student,
            'media' => (string) $r->media,
            'duration_sec' => (int) $r->duration_sec,
            'expires_at' => $r->expires_at ? CarbonImmutable::parse($r->expires_at) : null,
        ])->all();
    }

    /** @return array{version: int, title: string, intro: string, sections: list<array{title: string, body: string}>, agree: string} */
    public function text(string $name, bool $forThemself): array
    {
        $l = 'classroom::recordings.consent.';
        $academy = $this->branding->current()->name();
        $days = ClassroomSettings::current()->recording_days;

        return [
            'version' => RecordingConsent::VERSION,
            'title' => __($l.'title'),
            'intro' => $forThemself ? __($l.'intro_self', ['academy' => $academy]) : __($l.'intro_child', ['academy' => $academy, 'name' => $name]),
            'sections' => array_map(fn (array $s) => ['title' => (string) $s['title'], 'body' => str_replace(':days', (string) $days, (string) $s['body'])], (array) __($l.'sections')),
            'agree' => __($l.'agree'),
        ];
    }
}
