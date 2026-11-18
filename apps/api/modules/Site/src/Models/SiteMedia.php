<?php

namespace Modules\Site\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * An image or a video the academy uploaded for its page (site-builder.md, 2):
 * a JPEG, PNG or WebP, or an MP4 or WebM, by its bytes, on the public disk
 * under site/{academy}/media/ with a name that never changes (so it is
 * cached for good). Blocks point at it by id.
 *
 * @property string $id
 * @property string $kind image | video
 * @property string $mime
 * @property string $path
 * @property int $bytes
 * @property ?int $width
 * @property ?int $height
 * @property string $name
 * @property array{ar: string, en: string} $alt
 * @property ?string $uploaded_by
 * @property ?CarbonImmutable $unused_since
 * @property CarbonImmutable $created_at
 */
final class SiteMedia extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const DISK = 'public';

    /** Types by their bytes, and the extension each is kept with. */
    public const IMAGES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public const VIDEOS = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];

    public const MAX_IMAGE_KB = 5120;

    public const MAX_VIDEO_KB = 51200;

    /** Images smaller than this on either side are refused. */
    public const MIN_IMAGE_SIDE = 200;

    public const MAX_FILES = 300;

    public const MAX_BYTES = 1073741824;

    /** Files nothing uses leave after this many days (site:sweep). */
    public const UNUSED_DAYS = 7;

    protected $table = 'site_media';

    protected $fillable = ['kind', 'mime', 'path', 'bytes', 'width', 'height', 'name', 'alt', 'uploaded_by', 'unused_since'];

    protected $attributes = ['alt' => '{}'];

    protected function casts(): array
    {
        return [
            'bytes' => 'integer', 'width' => 'integer', 'height' => 'integer',
            'unused_since' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    /** The alt text, always {ar, en} (an object in the database, never a list). */
    protected function alt(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): array {
                $alt = is_string($value) ? json_decode($value, true) : $value;

                return ['ar' => (string) ($alt['ar'] ?? ''), 'en' => (string) ($alt['en'] ?? '')];
            },
            set: fn (mixed $value): string => (string) json_encode(['ar' => (string) (is_array($value) ? ($value['ar'] ?? '') : ''), 'en' => (string) (is_array($value) ? ($value['en'] ?? '') : '')], JSON_UNESCAPED_UNICODE),
        );
    }

    public static function directory(string $academyId): string
    {
        return "site/{$academyId}/media";
    }

    /** Its address as a path on this host: the panels show it from their own host. */
    public function localUrl(): string
    {
        $url = Storage::disk(self::DISK)->url($this->path);
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $url;
    }

    /**
     * Where the browser reads it: a path on the academy's own host (/storage/…),
     * or the whole address when the API is asked for them (SITE_MEDIA_ABSOLUTE: a
     * laptop, where the portal and the files are on different ports).
     */
    public function url(): string
    {
        $url = Storage::disk(self::DISK)->url($this->path);

        return config('site.media_absolute') ? $url : $this->localUrl();
    }
}
