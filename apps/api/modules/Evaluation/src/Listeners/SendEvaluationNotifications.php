<?php

namespace Modules\Evaluation\Listeners;

use Carbon\CarbonImmutable;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Evaluation\Contracts\Events\LessonRated;
use Modules\Evaluation\Contracts\Events\LessonRatingDue;
use Modules\Evaluation\Models\LessonRating;
use Modules\Evaluation\Queries\LessonFacts;
use Modules\Identity\Contracts\IdentityApi;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Evaluation's messages (wording in lang/{ar,en}/notifications.php): the
 * family's "how was the lesson?" with each person's own link to rate it,
 * and a low rating for whoever reviews the teachers' work. The teacher is
 * not told who rated what.
 */
final class SendEvaluationNotifications
{
    public const LOW = 3;

    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly LessonFacts $lessons,
        private readonly PeopleApi $people,
        private readonly IdentityApi $identity,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $a = 'evaluation::notifications.audience.';
        $lesson = ['student' => 'زياد', 'teacher' => 'أ. خالد', 'subject' => 'النحو', 'when' => 'الأحد 11 أكتوبر · 6:00 م'];
        foreach ([
            ['rate_lesson', 'lessons', $a.'family', ['whatsapp', 'push'], $lesson + ['link' => 'https://demo.lms.test/rate/…']],
            ['low_rating', 'staff', $a.'reviewers', ['push', 'email'], $lesson + ['stars' => '2', 'comment' => 'النت كان بيقطع والمدرس اتأخر.']],
        ] as [$name, $category, $audience, $channels, $sample]) {
            $notifications->define(new TemplateDefinition(
                key: "evaluation.{$name}", category: $category,
                title: "evaluation::notifications.{$name}.title", body: "evaluation::notifications.{$name}.body",
                audience: $audience, channels: $channels, sample: $sample,
            ));
        }
    }

    public function ratingDue(LessonRatingDue $event): void
    {
        $lesson = $this->lessons->find($event->sessionId);
        if ($lesson === null) {
            return;
        }
        $this->notifications->send(new Message('evaluation.rate_lesson', [$event->studentId, ...$this->people->guardiansOf($event->studentId)], $this->data($lesson),
            ['screen' => 'lesson_rating', 'id' => $lesson->id], 'rate-week:'.$event->studentId.':'.CarbonImmutable::now()->format('o-W')));
    }

    /** A rating of 3 or less, new or lowered to it, reaches the reviewers with its words. */
    public function rated(LessonRated $event): void
    {
        if ($event->stars > self::LOW || ($event->previousStars !== null && $event->previousStars <= self::LOW)) {
            return;
        }
        $lesson = $this->lessons->find($event->sessionId);
        $rating = LessonRating::query()->find($event->ratingId);
        if ($lesson === null || $rating === null) {
            return;
        }
        $this->notifications->send(new Message('evaluation.low_rating', $this->identity->usersWithAbility(Ability::ReviewPerformance), $this->data($lesson) + [
            'stars' => (string) $event->stars,
            'comment' => (string) $rating->comment,
        ], ['screen' => 'teacher_performance', 'id' => $event->teacherId], "low-rating:{$event->ratingId}:{$event->stars}"));
    }

    /** @return array<string, mixed> */
    private function data(object $lesson): array
    {
        return [
            'student' => (string) $lesson->student,
            'teacher' => (string) $lesson->teacher,
            'subject' => new Localized((string) $lesson->subject_ar, (string) $lesson->subject_en),
            'when' => CarbonImmutable::parse($lesson->starts_at),
        ];
    }
}
