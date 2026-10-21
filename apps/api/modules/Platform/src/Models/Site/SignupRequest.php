<?php

namespace Modules\Platform\Models\Site;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * An academy that asked to join from the platform's page, and what the
 * platform team did about it. Platform connection only.
 *
 * @property string $id
 * @property string $academy_name
 * @property string $contact_name
 * @property string $email
 * @property ?string $phone
 * @property ?string $country
 * @property ?string $students
 * @property ?string $plan_code
 * @property ?string $message
 * @property string $locale
 * @property ?string $utm_source
 * @property ?string $utm_medium
 * @property ?string $utm_campaign
 * @property string $status
 * @property ?string $note
 * @property ?string $academy_id
 * @property ?string $handled_by
 * @property ?CarbonImmutable $handled_at
 * @property CarbonImmutable $created_at
 */
final class SignupRequest extends Model
{
    use HasUuids;

    public const STATUSES = ['new', 'contacted', 'created', 'declined'];

    /** About how many students the academy has. */
    public const STUDENTS = ['1-20', '21-50', '51-200', '201+'];

    protected $connection = 'pgsql_platform';

    protected $table = 'platform_signup_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['handled_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
