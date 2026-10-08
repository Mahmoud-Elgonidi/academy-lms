<?php

namespace Modules\Curriculum\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Curriculum\Contracts\DTOs\LessonResource;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A file or a link on a lesson, for everyone who follows it or for its
 * teachers only.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $node_id
 * @property int $ordinal
 * @property string $kind file | link
 * @property string $title_ar
 * @property string $title_en
 * @property string $audience all | teachers
 * @property ?string $file_path on the private disk
 * @property ?string $file_name
 * @property ?string $mime
 * @property ?int $size bytes
 * @property ?string $url
 * @property ?string $created_by
 */
final class NodeResource extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const DISK = 'local';

    public const AUDIENCES = ['all', 'teachers'];

    protected $table = 'node_resources';

    protected $fillable = ['node_id', 'ordinal', 'kind', 'title_ar', 'title_en', 'audience', 'file_path', 'file_name', 'mime', 'size', 'url', 'created_by'];

    protected $attributes = ['audience' => 'all'];

    protected function casts(): array
    {
        return ['ordinal' => 'integer', 'size' => 'integer'];
    }

    /** What it is, for its icon and how it opens: pdf, audio, video, image, or link. */
    public function type(): string
    {
        if ($this->kind === 'link') {
            return 'link';
        }

        return match (true) {
            $this->mime === 'application/pdf' => 'pdf',
            str_starts_with((string) $this->mime, 'audio/') => 'audio',
            str_starts_with((string) $this->mime, 'video/') => 'video',
            default => 'image',
        };
    }

    public function toData(): LessonResource
    {
        return new LessonResource(
            id: $this->id,
            nodeId: $this->node_id,
            kind: $this->kind,
            type: $this->type(),
            titleAr: $this->title_ar,
            titleEn: $this->title_en,
            audience: $this->audience,
            url: $this->url,
            fileName: $this->file_name,
            mime: $this->mime,
            size: $this->size,
        );
    }
}
