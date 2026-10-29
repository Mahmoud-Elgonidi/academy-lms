<?php

namespace Modules\Announcements\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Announcements\Domain\AnnouncementRules;
use Modules\Announcements\Domain\Audience;
use Modules\Announcements\Events\AnnouncementSent;
use Modules\Announcements\Models\Announcement;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\NotificationsApi;

/**
 * An announcement reaches each person in the notifications they already
 * have: their inbox (the bell in every panel, the portal, the apps), and
 * push or email when the academy chose them and the person wants them. The
 * academy's own words fill the template, which frames them in each
 * person's language ("Announcement: …"); families hear it under lessons,
 * teachers under work. In the background (it may be everyone), in batches,
 * once per person whatever happens (the dedupe key).
 */
final class SendAnnouncementNotifications implements ShouldQueue
{
    public const BATCH = 200;

    public function __construct(private readonly NotificationsApi $notifications) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $n = 'announcements::notifications.';
        foreach (['families' => 'lessons', 'teachers' => 'staff'] as $who => $category) {
            $notifications->define(new TemplateDefinition(
                key: "announcements.{$who}", category: $category,
                title: $n.'announcement.title', body: $n.'announcement.body',
                audience: $n.'audience.'.$who, channels: AnnouncementRules::CHANNELS,
                sample: ['title' => 'إجازة عيد الفطر', 'body' => "الأكاديمية في إجازة من الثلاثاء إلى الخميس.\nتعود الحصص يوم الجمعة في مواعيدها."],
            ));
        }
    }

    public static function template(string $audience): string
    {
        return $audience === Audience::TEACHERS ? 'announcements.teachers' : 'announcements.families';
    }

    public function handle(AnnouncementSent $event): void
    {
        $announcement = Announcement::query()->find($event->announcementId);
        if ($announcement === null) {
            return;
        }
        foreach (array_chunk($event->recipients, self::BATCH) as $batch) {
            $this->notifications->send(new Message(
                self::template($announcement->audience), $batch,
                ['title' => $announcement->title, 'body' => $announcement->body],
                ['screen' => 'announcement', 'id' => $announcement->id],
                'announcement:'.$announcement->id,
                channels: $announcement->channels,
            ));
        }
    }
}
