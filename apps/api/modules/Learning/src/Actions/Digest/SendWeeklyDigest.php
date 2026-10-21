<?php

namespace Modules\Learning\Actions\Digest;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Learning\Digest\DigestPrompt;
use Modules\Learning\Digest\FactsText;
use Modules\Learning\Digest\WeekFacts;
use Modules\Learning\Digest\WordingCheck;
use Modules\Learning\Models\LearningSettings;
use Modules\Learning\Models\WeeklyDigest;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\LanguageModel;

/**
 * Writes and sends a learner's weekly note to the guardians who receive
 * reports (docs/architecture/02, section 22): the week before it goes out,
 * from WeekFacts alone. The helper words it when the academy has it on and
 * what it wrote passes WordingCheck; else the facts' own words. Each guardian
 * reads it in their language, once (the week's dedupe key), with a link to
 * hear the recording of the week when there is one. A week with nothing in
 * it, or no guardian to read it, sends nothing.
 */
final class SendWeeklyDigest
{
    public function __construct(
        private readonly WeekFacts $week,
        private readonly AcademyAi $ai,
        private readonly LanguageModel $model,
        private readonly NotificationsApi $notifications,
        private readonly PeopleApi $people,
    ) {}

    public function handle(WeeklyDigest $digest, ?CarbonImmutable $now = null): WeeklyDigest
    {
        if ($digest->status !== 'pending') {
            return $digest;
        }
        $now ??= CarbonImmutable::now();
        if (! LearningSettings::current()->weekly_digest) {
            return $this->skip($digest, 'off');
        }
        $guardians = $this->people->guardiansOf($digest->student_id, reportsOnly: true);
        if ($guardians === []) {
            return $this->skip($digest, 'no_guardians');
        }
        $name = (string) DB::table('users')->where('id', $digest->student_id)->value('name');
        $facts = $this->week->for($digest->student_id, $name, $now->subDays(7), $now);
        if (WeekFacts::quiet($facts)) {
            return $this->skip($digest, 'quiet');
        }

        [$source, $ar, $en] = $this->words($facts);
        $first = strtok(trim($name), ' ') ?: $name;
        $named = fn (string $text) => str_replace('{child}', $first, $text);
        $voice = $facts['voice'];
        $digest->update([
            'source' => $source, 'text_ar' => $ar, 'text_en' => $en, 'facts' => $facts, 'voice_submission_id' => $voice,
        ]);
        $sent = $this->notifications->send(new Message(
            $voice === null ? 'learning.weekly_digest' : 'learning.weekly_digest_voice',
            $guardians,
            ['student' => $first, 'body' => new Localized($named($ar), $named($en))],
            ['screen' => 'journey', 'learner_id' => $digest->student_id] + ($voice === null ? [] : ['voice' => $voice]),
            "digest:{$digest->student_id}:{$digest->week_start->toDateString()}",
        ));
        $digest->update(['status' => 'sent', 'sent_to' => $sent, 'sent_at' => $now]);

        return $digest;
    }

    /**
     * The note's words in each language, and who wrote them.
     *
     * @param  array<string, mixed>  $facts
     * @return array{0: 'ai'|'facts', 1: string, 2: string}
     */
    private function words(array $facts): array
    {
        if ($this->ai->on(AiFeature::DigestWording)) {
            try {
                $this->ai->require(AiFeature::DigestWording);
                $answer = $this->model->json(AiFeature::DigestWording, DigestPrompt::for($facts));
                $numbers = WeekFacts::numbers($facts);
                $ar = WordingCheck::clean($answer->data['ar'] ?? null, $numbers);
                $en = WordingCheck::clean($answer->data['en'] ?? null, $numbers);
                if ($ar !== null && $en !== null) {
                    return ['ai', $ar, $en];
                }
            } catch (AiFailed) {
                // The facts' own words go out instead.
            }
        }

        return ['facts', FactsText::for($facts, 'ar'), FactsText::for($facts, 'en')];
    }

    private function skip(WeeklyDigest $digest, string $reason): WeeklyDigest
    {
        $digest->update(['status' => 'skipped', 'reason' => $reason]);

        return $digest;
    }
}
