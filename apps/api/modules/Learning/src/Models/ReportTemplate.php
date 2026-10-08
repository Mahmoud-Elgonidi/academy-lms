<?php

namespace Modules\Learning\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Learning\Templates\TemplateField;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One version of a subject's report template: the academy's own questions
 * after a lesson. Never changed (the table refuses it); a change is the next
 * version, and a report keeps the one it was written with.
 *
 * @property string $id
 * @property string $subject_id
 * @property int $version
 * @property list<array<string, mixed>> $fields
 * @property ?string $created_by
 * @property CarbonImmutable $created_at
 */
final class ReportTemplate extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['subject_id', 'version', 'fields', 'created_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'fields' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** The subject's template now: its latest version, if it has one. */
    public static function currentFor(string $subjectId): ?self
    {
        return self::query()->where('subject_id', $subjectId)->orderByDesc('version')->first();
    }

    /** The questions a lesson report asks: the version it was written with, else the subject's now. */
    public static function forReport(SessionReport $report): ?self
    {
        if ($report->kind !== 'lesson') {
            return null;
        }
        if ($report->template_id !== null) {
            return self::query()->find($report->template_id);
        }
        $subject = DB::table('sessions')->where('id', $report->session_id)->value('subject_id');

        return $subject === null ? null : self::currentFor((string) $subject);
    }

    /** @return list<TemplateField> */
    public function fieldList(): array
    {
        return array_map(fn (array $f) => TemplateField::fromArray($f), $this->fields);
    }
}
