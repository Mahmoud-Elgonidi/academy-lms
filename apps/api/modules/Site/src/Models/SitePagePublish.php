<?php

namespace Modules\Site\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One publish of the academy's page, kept so the page can be brought back
 * (site:sweep keeps the last 20). Never changed once written.
 *
 * @property string $id
 * @property int $revision
 * @property array<string, mixed> $page
 * @property ?string $published_by
 * @property CarbonImmutable $published_at
 */
final class SitePagePublish extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const KEEP = 20;

    public $timestamps = false;

    protected $table = 'site_page_publishes';

    protected $fillable = ['revision', 'page', 'published_by', 'published_at'];

    protected function casts(): array
    {
        return ['revision' => 'integer', 'page' => 'array', 'published_at' => 'immutable_datetime'];
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('A publish of the page is never changed.');
        }

        return parent::save($options);
    }
}
