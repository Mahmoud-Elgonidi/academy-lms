<?php

namespace Modules\Classroom\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A teacher's own Zoom or Meet link, used when the academy runs lessons on outside links.
 *
 * @property string $teacher_id
 * @property string $url
 */
final class MeetingLink extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $primaryKey = 'teacher_id';

    protected $keyType = 'string';

    protected $fillable = ['teacher_id', 'url'];

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('teacher_id', $this->getAttribute('teacher_id'));
    }
}
