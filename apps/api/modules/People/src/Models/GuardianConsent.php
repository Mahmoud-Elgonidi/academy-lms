<?php

namespace Modules\People\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A guardian's consent to a child's account: who agreed, when, how and to
 * which wording. Never deleted; withdrawing marks it once (the database holds
 * both), and agreeing again is a new consent.
 *
 * @property string $id
 * @property string $student_id
 * @property ?string $guardian_id null only when the office recorded a guardian it has no account for
 * @property string $purpose account
 * @property int $version the wording (people::consent) they agreed to
 * @property string $via family | link | staff
 * @property ?string $recorded_by the office: who recorded it
 * @property ?string $note the office: what it has
 * @property ?string $ip
 * @property ?string $user_agent
 * @property CarbonImmutable $granted_at
 * @property ?CarbonImmutable $withdrawn_at
 * @property ?string $withdrawn_by
 * @property ?string $withdraw_note
 */
final class GuardianConsent extends Model
{
    use BelongsToTenant;
    use HasUuids;

    /** The wording now (lang people::consent): raise it when what a guardian reads changes. */
    public const VERSION = 1;

    public const VIA = ['family', 'link', 'staff'];

    public const UPDATED_AT = null;

    protected $fillable = ['student_id', 'guardian_id', 'purpose', 'version', 'via', 'recorded_by', 'note', 'ip', 'user_agent',
        'granted_at', 'withdrawn_at', 'withdrawn_by', 'withdraw_note'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'granted_at' => 'immutable_datetime', 'withdrawn_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @param  Builder<self>  $query */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('withdrawn_at')->where('purpose', 'account');
    }

    public static function liveFor(string $studentId): ?self
    {
        return self::query()->live()->where('student_id', $studentId)->first();
    }
}
