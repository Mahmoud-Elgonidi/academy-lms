<?php

namespace Modules\Privacy\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Who the academy is on its privacy notice and terms (it is the data's
 * controller; the platform processes the data for it): its legal name, the
 * address people write to about their data, and its own words added to
 * the platform's text. Without a row, the academy's name and nothing more.
 *
 * @property ?string $legal_name
 * @property ?string $contact_email
 * @property ?string $address
 * @property ?string $privacy_extra
 * @property ?string $terms_extra
 * @property ?string $updated_by
 */
final class PrivacySettings extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['legal_name', 'contact_email', 'address', 'privacy_extra', 'terms_extra', 'updated_by'];

    public static function current(): self
    {
        return self::query()->first() ?? new self;
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('academy_id', $this->getAttribute('academy_id'));
    }
}
