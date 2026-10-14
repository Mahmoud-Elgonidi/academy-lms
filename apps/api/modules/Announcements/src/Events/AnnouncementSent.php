<?php

namespace Modules\Announcements\Events;

/**
 * An announcement was written down and is to go out to these people (raised
 * after the commit). Announcements' own: its listener sends the messages.
 */
final readonly class AnnouncementSent
{
    /** @param  list<string>  $recipients  user ids, as resolved when it was sent */
    public function __construct(
        public string $academyId,
        public string $announcementId,
        public array $recipients,
    ) {}
}
