<?php

namespace Modules\Platform\Models\Site;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Modules\Platform\Site\SiteContent;

/**
 * What the platform's page says (one row; Site\SiteContent). Platform
 * connection only.
 *
 * @property int $id
 * @property array<string, mixed> $content
 * @property ?string $updated_by
 * @property CarbonImmutable $updated_at
 */
final class PlatformSite extends Model
{
    public const CREATED_AT = null;

    protected $connection = 'pgsql_platform';

    protected $table = 'platform_site';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['content' => 'array', 'updated_at' => 'immutable_datetime'];
    }

    /** @return array<string, mixed> what the page says now: the saved content, else the defaults */
    public static function content(): array
    {
        $saved = self::query()->find(1)?->content;

        return is_array($saved) ? $saved + SiteContent::defaults() : SiteContent::defaults();
    }
}
