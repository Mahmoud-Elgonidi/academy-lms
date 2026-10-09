<?php

namespace Modules\Safeguarding\Listeners;

use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Safeguarding\ConcernRules;
use Modules\Safeguarding\Events\ConcernClosed;
use Modules\Safeguarding\Events\ConcernEscalated;
use Modules\Safeguarding\Events\ConcernReported;
use Modules\Safeguarding\Models\Concern;

/**
 * Safeguarding's messages (wording in lang/{ar,en}/notifications.php). The
 * leads hear of a new concern at once and again if nobody took it within the
 * hour, by its number and kind only: no names and nothing of what was said
 * leaves the platform. The reporter reads the reply a lead wrote on closing.
 */
final class SendConcernNotifications
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly ConcernRules $rules,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $a = 'safeguarding::notifications.audience.';
        foreach ([
            ['concern_reported', 'staff', $a.'leads', ['push', 'email'], ['concern' => '#7', 'category' => 'حد طلب يكلّمه برّه المنصة', 'urgent' => '']],
            ['concern_waiting', 'staff', $a.'leads', ['push', 'email'], ['concern' => '#7', 'category' => 'حد طلب يكلّمه برّه المنصة', 'urgent' => '']],
            ['concern_answered', 'lessons', $a.'reporter', ['push'], ['concern' => '#7', 'reply' => 'شكرًا إنك قلت لنا. اتكلمنا مع المدرس واتغير ميعاد الحصة.']],
        ] as [$name, $category, $audience, $channels, $sample]) {
            $notifications->define(new TemplateDefinition(
                key: "safeguarding.{$name}", category: $category,
                title: "safeguarding::notifications.{$name}.title", body: "safeguarding::notifications.{$name}.body",
                audience: $audience, channels: $channels, sample: $sample,
            ));
        }
    }

    public function reported(ConcernReported $event): void
    {
        $this->toLeads($event->concernId, 'concern_reported', 'concern-reported');
    }

    public function escalated(ConcernEscalated $event): void
    {
        $this->toLeads($event->concernId, 'concern_waiting', 'concern-waiting');
    }

    public function closed(ConcernClosed $event): void
    {
        $concern = Concern::query()->find($event->concernId);
        if ($concern === null || $concern->reporter_reply === null || $concern->reporter_side === 'staff') {
            return;
        }
        $this->notifications->send(new Message('safeguarding.concern_answered', [$concern->reporter_id], [
            'concern' => $concern->reference(), 'reply' => $concern->reporter_reply,
        ], ['screen' => 'my_concerns', 'id' => $concern->id], "concern-answered:{$concern->id}:".$concern->closed_at?->getTimestamp()));
    }

    private function toLeads(string $concernId, string $key, string $dedupe): void
    {
        $concern = Concern::query()->find($concernId);
        if ($concern === null) {
            return;
        }
        $leads = array_values(array_diff($this->rules->leads(), [$concern->reporter_id]));
        $this->notifications->send(new Message("safeguarding.{$key}", $leads, [
            'concern' => $concern->reference(),
            'category' => new Translatable('safeguarding::concerns.categories.classic.'.$concern->category->value),
            'urgent' => $concern->urgent ? new Translatable('safeguarding::notifications.urgent') : '',
        ], ['screen' => 'safeguarding_concern', 'id' => $concern->id], "{$dedupe}:{$concern->id}"));
    }
}
