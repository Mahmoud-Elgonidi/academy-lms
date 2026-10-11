<?php

namespace Modules\Crm\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Crm\Enums\LeadSource;
use Modules\Crm\Enums\LeadStage;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Someone who asked about lessons (docs/architecture/01, section 14): the
 * contact (a parent, or the learner when an adult), what they want and
 * when, where they came from, who follows them, and how far they got.
 *
 * @property string $id
 * @property string $contact_name
 * @property ?string $phone_e164 null once the person's account was erased
 * @property ?string $email
 * @property ?string $country
 * @property string $timezone
 * @property string $locale
 * @property bool $for_child
 * @property ?string $learner_name
 * @property ?int $learner_birth_year
 * @property ?string $subject_id
 * @property list<int> $preferred_days
 * @property ?int $preferred_from
 * @property ?int $preferred_to
 * @property LeadSource $source
 * @property ?string $notes
 * @property ?string $owner_id
 * @property LeadStage $stage
 * @property CarbonImmutable $stage_changed_at
 * @property ?string $lost_reason
 * @property ?CarbonImmutable $last_contact_at
 * @property ?string $learner_id
 * @property ?string $contact_id
 * @property ?string $enrollment_id
 * @property CarbonImmutable $created_at
 */
final class Lead extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const LOST_REASONS = ['price', 'times', 'no_answer', 'chose_other', 'not_interested', 'not_now', 'other'];

    protected $fillable = [
        'contact_name', 'phone_e164', 'email', 'country', 'timezone', 'locale', 'for_child', 'learner_name', 'learner_birth_year',
        'subject_id', 'preferred_days', 'preferred_from', 'preferred_to', 'source', 'utm_source', 'utm_medium', 'utm_campaign',
        'notes', 'owner_id', 'stage', 'stage_changed_at', 'lost_reason', 'last_contact_at', 'learner_id', 'contact_id', 'enrollment_id', 'created_by',
    ];

    protected $attributes = ['stage' => 'new', 'preferred_days' => '[]', 'locale' => 'ar', 'for_child' => false];

    protected function casts(): array
    {
        return [
            'for_child' => 'boolean',
            'learner_birth_year' => 'integer',
            'preferred_days' => 'array',
            'preferred_from' => 'integer',
            'preferred_to' => 'integer',
            'source' => LeadSource::class,
            'stage' => LeadStage::class,
            'stage_changed_at' => 'immutable_datetime',
            'last_contact_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->orderByDesc('created_at');
    }

    public function trials(): HasMany
    {
        return $this->hasMany(LeadTrial::class)->orderByDesc('starts_at');
    }

    /** Who the lessons are for. */
    public function learnerName(): string
    {
        return $this->for_child && $this->learner_name ? $this->learner_name : $this->contact_name;
    }

    /**
     * Moves the lead. The move goes on its timeline unless what caused it is
     * already there in its own words (a trial booked, an assessment, ...).
     */
    public function moveTo(LeadStage $stage, ?string $actorId, bool $log = true): void
    {
        if ($this->stage === $stage) {
            return;
        }
        $from = $this->stage;
        $this->forceFill(['stage' => $stage, 'stage_changed_at' => CarbonImmutable::now()])->save();
        if ($log) {
            LeadActivity::record($this, 'stage', null, ['from' => $from->value, 'to' => $stage->value], $actorId);
        }
    }
}
