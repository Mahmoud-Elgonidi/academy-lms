<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $id
 * @property string $subject_id
 * @property int $ordinal
 * @property string $name_ar
 * @property string $name_en
 */
final class SubjectLevel extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['subject_id', 'ordinal', 'name_ar', 'name_en'];

    protected function casts(): array
    {
        return ['ordinal' => 'integer'];
    }

    public function name(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
