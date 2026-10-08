<?php

namespace Modules\Learning\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Learning\Models\ReportTemplate;
use Modules\Learning\Templates\TemplateDefinition;
use Modules\Learning\Templates\TemplateField;

/**
 * The academy's own questions in a subject's lesson report
 * (docs/architecture/01, section 10, "قالب حسب المادة بإصدارات"). Saving
 * makes the next version, never an edit: reports already written keep the
 * questions they answered, the reports still open ask the new ones. Saving
 * the same questions again makes nothing. An empty list asks nothing more
 * than the report every lesson has.
 */
final class SaveReportTemplate
{
    public function __construct(private readonly CatalogApi $catalog) {}

    /** @param  list<array<string, mixed>>  $fields  as the editor sends them; see TemplateDefinition */
    public function handle(string $subjectId, array $fields, ?string $actorId): ReportTemplate
    {
        if (! array_key_exists($subjectId, $this->catalog->subjectNames())) {
            throw ValidationException::withMessages(['subject_id' => __('learning::templates.errors.subject')]);
        }

        return DB::transaction(function () use ($subjectId, $fields, $actorId) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['report_template:'.$subjectId]);
            $versions = ReportTemplate::query()->where('subject_id', $subjectId)->orderByDesc('version')->get()->all();
            $read = array_map(fn (TemplateField $f) => $f->toArray(), TemplateDefinition::read($fields, $versions));

            $current = $versions[0] ?? null;
            if ($current !== null ? $current->fields == $read : $read === []) {
                return $current ?? new ReportTemplate(['subject_id' => $subjectId, 'version' => 0, 'fields' => []]);
            }

            return ReportTemplate::query()->create([
                'subject_id' => $subjectId,
                'version' => ($current->version ?? 0) + 1,
                'fields' => $read,
                'created_by' => $actorId,
            ]);
        });
    }
}
