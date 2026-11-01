<?php

namespace Modules\Quran\Actions\Programs;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Quran\Domain\Programs\Presets;
use Modules\Quran\Domain\Programs\ProgramRules;
use Modules\Quran\Models\QuranPlan;
use Modules\Quran\Models\QuranProgram;
use Modules\Quran\Programs\Mushaf;
use Modules\Quran\Support\QuranArea;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The academy saves a Quran program (docs/architecture/02, section 26),
 * new or changed, as ProgramRules allows it: its scope must be in the
 * platform's copy of the Quran (quarters of a hizb only when the copy has
 * them), and once a plan is on it, its shape (track, scope, direction,
 * unit) stays: another shape is another program. Its amounts, reviews,
 * pass grade, exams and name may change. Audited with its name and the
 * fields changed. Programs are archived, never deleted (plans point at them).
 */
final class SaveQuranProgram
{
    public function __construct(
        private readonly Mushaf $mushaf,
        private readonly AuditTrail $audit,
        private readonly QuranArea $area,
    ) {}

    /** @param  array<string, mixed>  $data  ProgramRules' fields (a missing one keeps the program's) */
    public function handle(?QuranProgram $program, array $data, ?string $actorId = null): QuranProgram
    {
        $this->area->require();
        $fields = array_merge(
            ['name', 'track', 'scope_from_surah', 'scope_from_verse', 'scope_to_surah', 'scope_to_verse', 'direction', 'unit', 'new_amount'],
            ['near_portions', 'far_pages', 'pass_grade', 'exam_stops', 'counts_for_certificate'],
        );
        $data = array_merge($program?->only($fields) ?? [], array_intersect_key($data, array_flip([...$fields, 'preset'])));
        $errors = ProgramRules::errors($data, $this->mushaf->surahVerses(), Presets::NOORANI_LESSONS);
        if (($data['track'] ?? null) !== 'noorani' && $this->mushaf->layout() === null) {
            $errors = ['scope_from_surah' => 'no_text'] + $errors;
        } elseif (($data['unit'] ?? null) === 'quarter_hizb' && ! $this->mushaf->layout()?->hasQuarters()) {
            $errors['unit'] ??= 'quarters';
        }
        $kept = $errors === [] ? ProgramRules::normalize($data) : [];
        if ($errors === [] && $program !== null && QuranPlan::query()->where('program_id', $program->id)->exists()) {
            foreach (ProgramRules::SHAPE as $field) {
                if ($kept[$field] != $program->getAttribute($field)) {
                    $errors[$field] = 'shape_locked';
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages(array_map(fn (string $why) => __('quran::programs.errors.'.$why), $errors));
        }

        return DB::transaction(function () use ($program, $kept, $data, $actorId): QuranProgram {
            $program ??= new QuranProgram(['created_by' => $actorId, 'preset' => in_array($data['preset'] ?? null, Presets::keys(), true) ? $data['preset'] : null]);
            $program->fill($kept);
            $changed = array_keys($program->getDirty());
            $new = ! $program->exists;
            $program->save();
            if ($new || $changed !== []) {
                $this->audit->record('quran.program_saved', 'quran_programs', $program->id, [
                    'name' => $program->name, 'quran_fields' => $new ? [] : array_values(array_diff($changed, ['name'])),
                ]);
            }

            return $program;
        });
    }

    /** A program from one of the ready tracks, under its own name (or the track's). */
    public function fromPreset(string $preset, ?string $name = null, ?string $actorId = null): QuranProgram
    {
        if (! in_array($preset, Presets::keys(), true)) {
            throw ValidationException::withMessages(['preset' => __('quran::programs.errors.preset')]);
        }

        return $this->handle(null, ['name' => $name ?? __('quran::programs.presets.'.$preset.'.name'), 'preset' => $preset] + Presets::get($preset), $actorId);
    }

    /** Archive a program (no new plans on it; those on it carry on), or bring it back. */
    public function archive(QuranProgram $program, bool $archived = true): QuranProgram
    {
        $this->area->require();
        if (($program->archived_at !== null) !== $archived) {
            $program->forceFill(['archived_at' => $archived ? CarbonImmutable::now() : null])->save();
            $this->audit->record($archived ? 'quran.program_archived' : 'quran.program_restored', 'quran_programs', $program->id, ['name' => $program->name]);
        }

        return $program;
    }
}
