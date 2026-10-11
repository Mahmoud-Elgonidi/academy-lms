<?php

namespace Modules\Learning\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Learning\Contracts\Events\HomeworkAssigned;
use Modules\Learning\Models\Homework;

/** Sets homework for a student, usually from the lesson report. */
final class AssignHomework
{
    /** @param  array{teacher_id: string, student_id: string, enrollment_id?: ?string, session_id?: ?string, report_id?: ?string, title: string, instructions?: ?string, accepts?: ?string, due_at: CarbonImmutable, practice_nodes?: ?list<string>, practice_game?: ?string}  $data */
    public function handle(array $data): Homework
    {
        return DB::transaction(function () use ($data) {
            $homework = Homework::query()->create([
                'teacher_id' => $data['teacher_id'],
                'student_id' => $data['student_id'],
                'enrollment_id' => $data['enrollment_id'] ?? null,
                'session_id' => $data['session_id'] ?? null,
                'report_id' => $data['report_id'] ?? null,
                'title' => trim($data['title']),
                'instructions' => ($data['instructions'] ?? null) ?: null,
                'accepts' => in_array($data['accepts'] ?? null, Homework::ACCEPTS, true) ? $data['accepts'] : 'any',
                'due_at' => $data['due_at'],
                'practice_nodes' => ($data['practice_nodes'] ?? null) ?: null,
                'practice_game' => ($data['practice_game'] ?? null) ?: null,
            ]);
            DB::afterCommit(fn () => event(new HomeworkAssigned($homework->academy_id, $homework->id, $homework->student_id, $homework->teacher_id, $homework->due_at->utc()->toIso8601ZuluString())));

            return $homework;
        });
    }
}
