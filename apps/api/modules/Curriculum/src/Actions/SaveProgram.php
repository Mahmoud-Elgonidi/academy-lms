<?php

namespace Modules\Curriculum\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Models\Program;

/** Creates or renames a program; a new one starts with an empty draft (version 1). */
final class SaveProgram
{
    /** @param  array{subject_id: string, name_ar: string, name_en: string, description_ar?: ?string, description_en?: ?string, status?: string}  $data */
    public function handle(?Program $program, array $data): Program
    {
        return DB::transaction(function () use ($program, $data) {
            $new = $program === null;
            $program ??= new Program;
            $program->fill([
                'subject_id' => $data['subject_id'],
                'name_ar' => trim($data['name_ar']),
                'name_en' => trim($data['name_en']),
                'description_ar' => $data['description_ar'] ?? null,
                'description_en' => $data['description_en'] ?? null,
                'status' => in_array($data['status'] ?? 'active', ['active', 'archived'], true) ? ($data['status'] ?? 'active') : 'active',
            ])->save();

            if ($new) {
                $program->versions()->create(['version' => 1, 'status' => 'draft']);
            }

            return $program;
        });
    }
}
