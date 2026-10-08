<?php

namespace Modules\Classroom\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Someone's consent to being recorded in lessons: a teacher or an adult
 * student for themself, a guardian for a child, or the office from a signed
 * form or the teacher's contract. Never deleted; withdrawing marks it once
 * (the database holds both), and agreeing again is a new consent.
 *
 * @property string $id
 * @property string $person_id who is recorded
 * @property string $role student | teacher
 * @property ?string $given_by who agreed (the person, or a child's guardian)
 * @property string $via self | guardian | staff
 * @property ?string $recorded_by the office: who recorded it
 * @property ?string $note the office: what it has
 * @property int $version the wording (classroom::recordings.consent) agreed to
 * @property ?string $ip
 * @property ?string $user_agent
 * @property CarbonImmutable $granted_at
 * @property ?CarbonImmutable $withdrawn_at
 * @property ?string $withdrawn_by
 * @property ?string $withdraw_note
 */
final class RecordingConsent extends Model
{
    use BelongsToTenant;
    use HasUuids;

    /** The wording now (lang classroom::recordings.consent): raise it when what people read changes in meaning. */
    public const VERSION = 1;

    public const UPDATED_AT = null;

    protected $fillable = ['person_id', 'role', 'given_by', 'via', 'recorded_by', 'note', 'version', 'ip', 'user_agent',
        'granted_at', 'withdrawn_at', 'withdrawn_by', 'withdraw_note'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'granted_at' => 'immutable_datetime', 'withdrawn_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @param  Builder<self>  $query */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('withdrawn_at');
    }

    public static function liveFor(string $personId): ?self
    {
        return self::query()->live()->where('person_id', $personId)->first();
    }
}
