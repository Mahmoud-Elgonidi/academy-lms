<?php

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Which optional areas the academy hid (Settings › Academy modules). None
 * until the academy hides one; one row, made on first save, written only
 * through SaveAcademyModules.
 *
 * @property string $academy_id
 * @property list<string> $hidden
 * @property ?string $updated_by
 */
final class AcademyModules extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $table = 'academy_modules';

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $guarded = [];

    protected $attributes = ['hidden' => '[]'];

    protected function casts(): array
    {
        return ['hidden' => 'array'];
    }

    public static function current(): self
    {
        return self::query()->first() ?? new self;
    }

    /** @return list<Feature> the optional areas hidden, in Feature's order */
    public function hiddenFeatures(): array
    {
        // getAttribute: inside the model, $this->hidden is Eloquent's own list of attributes left out of arrays.
        $hidden = (array) $this->getAttribute('hidden');

        return array_values(array_filter(Feature::optional(), fn (Feature $f) => in_array($f->value, $hidden, true)));
    }
}
