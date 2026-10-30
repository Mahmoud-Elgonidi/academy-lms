<?php

namespace Modules\Curriculum\Actions\Outline;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Curriculum\Domain\Outline\OutlineRules;
use Modules\Curriculum\Models\Program;
use Modules\Curriculum\Outline\OutlineAnswer;
use Modules\Curriculum\Outline\OutlinePrompt;
use Modules\Curriculum\Texts\QuranText;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\LanguageModel;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Drafts a program's outline with the helper (docs/architecture/02, section
 * 22): it is sent the subject and its levels (CatalogApi), the age group, the
 * number of lessons and the goal typed, and nothing about a person. The
 * answer goes through OutlineAnswer and OutlineRules (lengths, counts; for
 * the Quran, numbers the platform's copy has, never words), and comes back as
 * rows to review: nothing is saved until ApplyOutline. Only when the academy
 * turned the helper on; PER_HOUR a person.
 */
final class SuggestOutline
{
    public const PER_HOUR = 10;

    public const GOAL_MAX = 1000;

    public function __construct(
        private readonly AcademyAi $ai,
        private readonly LanguageModel $model,
        private readonly CatalogApi $catalog,
        private readonly QuranText $quran,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @param  array{age?: ?string, lessons?: int|string|null, goal?: ?string, quran?: bool|string|null}  $ask
     * @return array{rows: list<array<string, mixed>>, dropped: int}
     */
    public function handle(Program $program, array $ask, string $actorId): array
    {
        $l = 'curriculum::outline.ai.errors.';
        $goal = trim(str_replace("\r\n", "\n", (string) ($ask['goal'] ?? '')));
        $lessons = (int) ($ask['lessons'] ?? 0);
        $age = in_array($ask['age'] ?? null, OutlinePrompt::AGES, true) ? (string) $ask['age'] : 'any';
        $isQuran = (bool) ($ask['quran'] ?? false);
        if ($goal === '' || mb_strlen($goal) > self::GOAL_MAX) {
            throw ValidationException::withMessages(['goal' => __($l.'goal', ['max' => self::GOAL_MAX])]);
        }
        if ($lessons < 1 || $lessons > OutlineRules::LESSONS_MAX) {
            throw ValidationException::withMessages(['lessons' => __($l.'lessons', ['max' => OutlineRules::LESSONS_MAX])]);
        }
        if ($isQuran && ! $this->quran->available()) {
            throw ValidationException::withMessages(['quran' => __('curriculum::curriculum.text.errors.quran_missing')]);
        }

        $this->ai->require(AiFeature::CurriculumOutline);
        $key = 'ai:outline:'.$this->tenants->id().':'.$actorId;
        if (RateLimiter::tooManyAttempts($key, self::PER_HOUR)) {
            throw new AiFailed('limit', __($l.'too_often', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]));
        }
        RateLimiter::hit($key, 3600);

        $prompt = OutlinePrompt::for(
            (string) ($this->catalog->subjectNames()[$program->subject_id] ?? ''),
            array_values($this->catalog->levels($program->subject_id)),
            $age, $lessons, $goal, $isQuran,
            $isQuran ? $this->quran->surahs() : [],
        );
        $answer = $this->model->json(AiFeature::CurriculumOutline, $prompt);
        $result = OutlineAnswer::read($answer->data, $this->quran, $isQuran);
        if ($result['rows'] === []) {
            throw new AiFailed('unreadable');
        }

        return $result;
    }
}
