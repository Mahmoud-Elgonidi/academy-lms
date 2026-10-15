<?php

namespace Modules\Curriculum\Actions\Practice;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\TextSource;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Models\NodeItem;
use Modules\Curriculum\Practice\PracticePrompt;
use Modules\Curriculum\Practice\Suggestions;
use Modules\Curriculum\Texts\QuranText;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\LanguageModel;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Suggests practice items for a lesson from its title, objectives and text
 * (docs/architecture/02, section 22): up to AT_ONCE at a time, within the
 * lesson's room (SaveNodeItems::MAX), each quoting the lesson's words as
 * kept (Suggestions). Saves nothing: the builder shows them as suggestions,
 * and the person saves what they keep through SaveNodeItems. Only when the
 * academy turned the helper on; PER_HOUR a person.
 */
final class SuggestNodeItems
{
    public const AT_ONCE = 12;

    public const PER_HOUR = 20;

    public function __construct(
        private readonly AcademyAi $ai,
        private readonly LanguageModel $model,
        private readonly CurriculumApi $curriculum,
        private readonly QuranText $quran,
        private readonly TenantContext $tenants,
    ) {}

    /** @return array{items: list<array<string, mixed>>, dropped: int} */
    public function handle(Node $lesson, string $actorId): array
    {
        $l = 'curriculum::curriculum.practice.suggest.errors.';
        if ($lesson->kind !== 'lesson') {
            throw ValidationException::withMessages(['items' => __('curriculum::curriculum.practice.errors.not_lesson')]);
        }
        $source = TextSource::fromArray($lesson->text_source);
        $passage = $source ? $this->curriculum->passage($source) : null;
        if ($passage === null && trim((string) $lesson->objectives) === '') {
            throw ValidationException::withMessages(['items' => __($l.'no_text')]);
        }
        $existing = NodeItem::query()->where('node_id', $lesson->id)->orderBy('ordinal')->get()->map(fn (NodeItem $i) => $i->toData()->summary())->all();
        $room = min(self::AT_ONCE, SaveNodeItems::MAX - count($existing));
        if ($room <= 0) {
            throw ValidationException::withMessages(['items' => __($l.'full', ['max' => SaveNodeItems::MAX])]);
        }

        $this->ai->require(AiFeature::PracticeItems);
        $key = 'ai:practice:'.$this->tenants->id().':'.$actorId;
        if (RateLimiter::tooManyAttempts($key, self::PER_HOUR)) {
            throw new AiFailed('limit', __($l.'too_often', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]));
        }
        RateLimiter::hit($key, 3600);

        $answer = $this->model->json(AiFeature::PracticeItems, PracticePrompt::for($lesson, $passage, $existing, $room));

        return Suggestions::read($answer->data, $passage, $this->quran, $room, $existing);
    }
}
