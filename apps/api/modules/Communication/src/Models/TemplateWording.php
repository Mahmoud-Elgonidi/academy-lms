<?php

namespace Modules\Communication\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * The academy's own wording of a template in one language.
 *
 * @property string $template_key
 * @property string $locale
 * @property string $title
 * @property string $body
 */
final class TemplateWording extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $table = 'notification_templates';

    protected $primaryKey = 'template_key';

    protected $keyType = 'string';

    protected $fillable = ['template_key', 'locale', 'title', 'body', 'updated_by'];

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('template_key', $this->getAttribute('template_key'))->where('locale', $this->getAttribute('locale'));
    }
}
