<?php

namespace Modules\People\Queries;

use Illuminate\Support\Facades\DB;
use Modules\People\Checks\Clearances;
use Modules\People\Contracts\CheckKind;
use Modules\People\Contracts\DTOs\TeacherClearance;
use Modules\People\Models\PeopleSettings;
use Modules\People\Models\TeacherCheck;

/**
 * The academy's view of its teachers' vetting (the safeguarding leads):
 * documents waiting to be checked, verified ones that run out within a
 * month, and, for every active teacher, whether they may take new lessons
 * with students under 18 and why not.
 */
final class TeacherChecksBoard
{
    public function __construct(private readonly Clearances $clearances) {}

    /** @return array{required: bool, pending: list<array<string, mixed>>, expiring: list<array<string, mixed>>, teachers: list<array<string, mixed>>} */
    public function forAcademy(): array
    {
        $names = DB::table('users as u')->join('teacher_profiles as tp', fn ($j) => $j->on('tp.academy_id', '=', 'u.academy_id')->on('tp.user_id', '=', 'u.id'))
            ->where('u.status', 'active')->orderBy('u.name')->pluck('u.name', 'u.id');
        $clearances = $this->clearances->many($names->keys()->all());
        $soon = $this->clearances->today()->addDays(TeacherCheck::WARN_DAYS)->toDateString();
        $today = $this->clearances->today()->toDateString();
        $pauses = DB::table('teacher_profiles')->whereNotNull('minors_paused_at')->get(['user_id', 'minors_paused_at', 'minors_pause_reason'])->keyBy('user_id');

        $row = fn (TeacherCheck $c) => [
            'id' => $c->id, 'teacher_id' => $c->teacher_id, 'teacher' => (string) ($names[$c->teacher_id] ?? DB::table('users')->where('id', $c->teacher_id)->value('name')),
            'kind' => $c->kind, 'reference' => $c->reference, 'issued_on' => $c->issued_on, 'expires_on' => $c->expires_on,
            'status' => $c->status, 'note' => $c->decision_note, 'created_at' => $c->created_at, 'has_file' => $c->file_path !== null,
            'file_url' => $c->file_path !== null ? route('people.check.file', ['check' => $c->id]) : null,
        ];

        return [
            'required' => PeopleSettings::current()->require_minor_check,
            'pending' => TeacherCheck::query()->where('status', 'pending')->orderBy('created_at')->get()->map($row)->all(),
            'expiring' => TeacherCheck::query()->where('status', 'verified')->whereNotNull('expires_on')->where('expires_on', '<=', $soon)->where('expires_on', '>=', $today)
                ->orderBy('expires_on')->get()->map($row)->all(),
            'teachers' => $names->map(fn (string $name, string $id) => [
                'id' => $id, 'name' => $name, 'clearance' => $clearances[$id],
                'paused_reason' => $pauses[$id]->minors_pause_reason ?? null,
            ])->values()->sortBy(fn (array $t) => [$t['clearance']->cleared ? 1 : 0, $t['name']])->values()->all(),
        ];
    }

    /** @return list<array<string, mixed>> one teacher's documents, newest first */
    public function ofTeacher(string $teacherId): array
    {
        return TeacherCheck::query()->where('teacher_id', $teacherId)->orderByDesc('created_at')->get()->map(fn (TeacherCheck $c) => [
            'id' => $c->id, 'kind' => $c->kind, 'reference' => $c->reference, 'issued_on' => $c->issued_on, 'expires_on' => $c->expires_on,
            'status' => $c->status, 'note' => $c->decision_note, 'created_at' => $c->created_at,
            'file_url' => $c->file_path !== null ? route('people.check.file', ['check' => $c->id]) : null,
        ])->all();
    }

    /** The tone of a clearance on screen. */
    public static function tone(TeacherClearance $c): string
    {
        return match ($c->state) {
            TeacherClearance::VERIFIED, TeacherClearance::NOT_REQUIRED => 'good',
            TeacherClearance::PENDING => 'warn',
            default => 'bad',
        };
    }

    /** @return array<string, string> */
    public static function kinds(): array
    {
        return CheckKind::options();
    }
}
