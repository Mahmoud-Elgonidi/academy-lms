<?php

namespace Modules\Learning\Actions\Assist;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Learning\Assist\ReportDraftPrompt;
use Modules\Learning\Domain\ReportDraftMerge;
use Modules\Learning\Models\ReportTemplate;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\ReportFormQuery;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\LanguageModel;
use Modules\SharedKernel\Ai\SpeechToText;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Text\ArabicText;

/**
 * A lesson report's draft from the teacher's own voice note (docs/
 * architecture/02, section 22): the recording is heard (speech to text,
 * never told what should be in it) and deleted at once, the student's name
 * becomes {child} before the words go to the language model, and its
 * reading fills only what the teacher's open form leaves empty
 * (ReportDraftMerge). Saves nothing and hands nothing in: the teacher
 * checks the draft and sends it as usual (SubmitSessionReport). Never a
 * lesson's recording: its consent is for follow-up and quality.
 */
final class DraftReportFromVoice
{
    /** Drafts a teacher may ask for in an hour. */
    public const PER_HOUR = 20;

    /** Fewer words than this were not a note about the lesson. */
    public const MIN_WORDS = 5;

    /** The course's lessons offered by key, today's first. */
    public const LESSONS_MAX = 80;

    public function __construct(
        private readonly AcademyAi $ai,
        private readonly SpeechToText $speech,
        private readonly LanguageModel $model,
        private readonly ReportFormQuery $form,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @param  array<string, mixed>  $current  the open form, as PUT teacher/reports/{report} takes it
     * @return array{heard: string, report: array<string, mixed>, filled: list<string>, unsure: list<string>, left: list<string>}
     */
    public function handle(SessionReport $report, string $actorId, AudioClip $note, array $current): array
    {
        try {
            if ($report->status === 'reviewed') {
                throw ValidationException::withMessages(['report' => __('learning::reports.errors.locked')]);
            }
            $this->ai->require(AiFeature::ReportDraft);
            $key = 'ai:report-draft:'.$this->tenants->id().':'.$actorId;
            if (RateLimiter::tooManyAttempts($key, self::PER_HOUR)) {
                throw new AiFailed('limit', __('learning::assist.too_often', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]));
            }
            RateLimiter::hit($key, 3600);

            $heard = $this->speech->transcribe(AiFeature::ReportDraft, $note, app()->getLocale() === 'en' ? 'eng' : 'ara');
        } finally {
            // The recording leaves now, whatever happened: it is never kept.
            if (is_file($note->path)) {
                @unlink($note->path);
            }
        }
        if (count(preg_split('/\s+/u', trim($heard->text), -1, PREG_SPLIT_NO_EMPTY) ?: []) < self::MIN_WORDS) {
            throw new AiFailed('unheard', __('learning::assist.unheard'));
        }

        $kind = $report->kind === 'absence' ? 'absence' : 'lesson';
        [$lessons, $labels, $planned] = $this->lessons($report, $current);
        $fields = $kind === 'lesson' ? (ReportTemplate::forReport($report)?->fieldList() ?? []) : [];
        $name = (string) DB::table('users')->where('id', $report->student_id)->value('name');

        $answer = $this->model->json(AiFeature::ReportDraft, ReportDraftPrompt::for(
            $kind,
            self::withoutName($heard->text, $name),
            array_map(fn (string $id) => $labels[$id], $lessons),
            $fields,
        ));
        $first = trim((string) strtok($name, ' ')) ?: $name;
        $draft = self::named($answer->data, $first);

        return ['heard' => $heard->text] + ReportDraftMerge::merge($kind, $current, $draft, $lessons, $labels, $planned, $fields);
    }

    /**
     * The lessons offered by key: those on the form first, then the course's in teaching order; and today's plan's
     * outcomes for the lessons the form still holds as the plan put them.
     *
     * @param  array<string, mixed>  $current
     * @return array{0: array<string, string>, 1: array<string, string>, 2: array<string, string>}
     */
    private function lessons(SessionReport $report, array $current): array
    {
        if ($report->kind === 'absence') {
            return [[], [], []];
        }
        $labels = $this->form->lessonOptions($report);
        $onForm = array_values(array_filter(array_map(fn ($i) => is_array($i) ? (string) ($i['node_id'] ?? '') : '', (array) ($current['items'] ?? [])), fn ($id) => isset($labels[$id])));
        $ids = array_slice(array_values(array_unique([...$onForm, ...array_keys($labels)])), 0, self::LESSONS_MAX);
        $keys = [];
        foreach ($ids as $i => $id) {
            $keys['L'.($i + 1)] = $id;
        }
        $planned = [];
        foreach ($this->form->items($report) as $item) {
            if ($item['reason'] !== null) {
                $planned[$item['node_id']] = $item['outcome'];
            }
        }

        return [$keys, $labels, $planned];
    }

    /** The teacher's words with the student's name, as heard, put as {child}. */
    public static function withoutName(string $said, string $name): string
    {
        $names = array_filter(array_map('mb_strtolower', ArabicText::words($name)), fn (string $w) => mb_strlen($w) > 1);
        if ($names === []) {
            return $said;
        }
        // "يوسف", and "ويوسف", "ليوسف", "بيوسف" as Arabic joins them.
        $is = fn (string $word) => in_array(mb_strtolower(ArabicText::normalize($word)), $names, true);

        return (string) preg_replace_callback('/[\p{L}\p{M}\p{N}]+/u', function (array $m) use ($is): string {
            if ($is($m[0])) {
                return '{child}';
            }
            $joined = preg_match('/^([ولبف])(\p{L}{2}.*)$/u', $m[0], $part) === 1 && $is($part[2]);

            return $joined ? $part[1].'{child}' : $m[0];
        }, $said);
    }

    /**
     * The model's answer with {child} as the student's first name again.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function named(array $data, string $first): array
    {
        array_walk_recursive($data, function (&$value) use ($first) {
            if (is_string($value)) {
                $value = str_replace('{child}', $first, $value);
            }
        });

        return $data;
    }
}
