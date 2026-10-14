<?php

namespace Modules\Announcements\Privacy;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * The announcements a person sent for the academy (sent_by): the words,
 * who they were for, the channels, how many they reached and when. Nothing
 * is erased here: an announcement is the academy's record of what it told
 * its families and teachers, and the sender's name goes with their
 * account. What each recipient received is in their own inbox (Communication's
 * messages).
 */
final class AnnouncementsData implements PersonalDataSource
{
    public function key(): string
    {
        return 'announcements';
    }

    public function export(string $userId): PersonalDataPart
    {
        $sent = DB::table('announcements')->where('sent_by', $userId)->orderBy('sent_at')
            ->get(['title', 'body', 'audience', 'stage', 'subject_id', 'channels', 'recipients_count', 'sent_at']);

        return new PersonalDataPart([
            'sent' => $sent->map(fn (object $a) => [
                'title' => $a->title,
                'body' => $a->body,
                'audience' => $a->audience,
                'stage' => $a->stage,
                'subject_id' => $a->subject_id,
                'channels' => ['inbox', ...(json_decode((string) $a->channels, true) ?: [])],
                'recipients' => (int) $a->recipients_count,
                'sent_at' => $a->sent_at,
            ])->all(),
        ]);
    }

    public function blockers(string $userId): array
    {
        return [];
    }

    public function erase(string $userId): array
    {
        return [];
    }
}
