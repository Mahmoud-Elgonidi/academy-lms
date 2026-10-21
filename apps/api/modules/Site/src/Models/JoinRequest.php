<?php

namespace Modules\Site\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A request from the academy's page: a free trial lesson (for the visitor or
 * their child) or a teacher asking to teach, and what the academy did about
 * it. The CRM makes a lead of a trial request (lead_id).
 *
 * @property string $id
 * @property string $kind trial | teach
 * @property string $name
 * @property string $phone E.164
 * @property ?string $email
 * @property ?string $country
 * @property ?string $timezone
 * @property string $locale
 * @property ?string $subject_id
 * @property bool $for_child
 * @property ?string $child_name
 * @property ?int $child_birth_year
 * @property list<string> $subject_ids
 * @property ?int $years_teaching
 * @property ?string $message
 * @property ?string $utm_source
 * @property ?string $utm_medium
 * @property ?string $utm_campaign
 * @property string $status new | contacted | accepted | declined
 * @property ?string $note
 * @property ?string $lead_id
 * @property ?string $handled_by
 * @property ?CarbonImmutable $handled_at
 * @property CarbonImmutable $created_at
 */
final class JoinRequest extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const KINDS = ['trial', 'teach'];

    public const STATUSES = ['new', 'contacted', 'accepted', 'declined'];

    protected $table = 'site_requests';

    protected $fillable = [
        'kind', 'name', 'phone', 'email', 'country', 'timezone', 'locale', 'subject_id', 'for_child', 'child_name', 'child_birth_year',
        'subject_ids', 'years_teaching', 'message', 'utm_source', 'utm_medium', 'utm_campaign', 'status', 'note', 'lead_id', 'handled_by', 'handled_at',
    ];

    protected $attributes = ['status' => 'new', 'subject_ids' => '[]', 'for_child' => false];

    protected function casts(): array
    {
        return [
            'for_child' => 'boolean', 'child_birth_year' => 'integer', 'subject_ids' => 'array', 'years_teaching' => 'integer',
            'handled_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }
}
