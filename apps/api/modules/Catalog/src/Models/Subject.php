<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $id
 * @property string $academy_id
 * @property string $name_ar
 * @property string $name_en
 */
final class Subject extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['name_ar', 'name_en'];
}
