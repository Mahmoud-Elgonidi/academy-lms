<?php

namespace Modules\Announcements\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * An announcement the academy sent (docs/architecture/02, section 20): its
 * words, who it was for and how many people it reached. Written once, by
 * SendAnnouncement; the model and the database refuse changes.
 *
 * @property string $id
 * @property string $title
 * @property string $body
 * @property string $audience families | guardians | students | subject | teachers
 * @property ?string $stage kids | middle | teen | adult (students only)
 * @property ?string $subject_id
 * @property list<string> $channels push, email (the inbox always)
 * @property int $recipients_count
 * @property string $sent_by
 * @property CarbonImmutable $sent_at
 */
final class Announcement extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['title', 'body', 'audience', 'stage', 'subject_id', 'channels', 'recipients_count', 'sent_by', 'sent_at'];

    protected $attributes = ['channels' => '[]'];

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('An announcement that was sent never changes.'));
        self::deleting(fn () => throw new LogicException('An announcement that was sent stays.'));
    }

    protected function casts(): array
    {
        return ['channels' => 'array', 'recipients_count' => 'integer', 'sent_at' => 'immutable_datetime'];
    }
}
