<?php

namespace Modules\Site\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;
use Modules\Site\Domain\Page\LegacyImport;
use Modules\Site\Domain\Page\PageDoc;

/**
 * The academy's page as its builder keeps it (docs/architecture/site-builder.md,
 * 1.1): the draft its owner or admins edit, and the published page that
 * GET v1/site shows (each a Domain\Page\PageDoc). An academy with no row yet
 * reads its old page (current()): the words it saved in site_settings, as
 * blocks. The first draft save makes the row.
 *
 * @property array<string, mixed> $draft
 * @property ?array<string, mixed> $published
 * @property int $draft_revision
 * @property ?int $published_revision
 * @property ?string $template
 * @property ?CarbonImmutable $draft_saved_at
 * @property ?string $draft_saved_by
 * @property ?CarbonImmutable $published_at
 * @property ?string $published_by
 */
final class SitePage extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $table = 'site_pages';

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['draft', 'published', 'draft_revision', 'published_revision', 'template', 'draft_saved_at', 'draft_saved_by', 'published_at', 'published_by'];

    protected $attributes = ['draft_revision' => 1];

    protected function casts(): array
    {
        return [
            'draft' => 'array', 'published' => 'array', 'draft_revision' => 'integer', 'published_revision' => 'integer',
            'draft_saved_at' => 'immutable_datetime', 'published_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * The current academy's page: its row, or (none yet) an unsaved one whose
     * draft and published page are its old page, read now from site_settings.
     */
    public static function current(): self
    {
        return self::query()->first() ?? self::legacy();
    }

    /** The current academy's page, locked for a change; made from its old page when it has none. */
    public static function lockedForUpdate(): self
    {
        return self::query()->lockForUpdate()->first() ?? self::legacy();
    }

    /** The page built from what the academy saved before the builder. */
    public static function legacy(): self
    {
        $doc = LegacyImport::fromContent(SiteSettings::current()->said());
        $page = new self;
        $page->forceFill(['draft' => $doc, 'published' => $doc, 'draft_revision' => 1, 'published_revision' => 1, 'template' => 'kids_families']);

        return $page;
    }

    /** Whether the draft holds changes the page does not show yet. */
    public function unpublished(): bool
    {
        return $this->published === null || $this->published_revision !== $this->draft_revision;
    }

    /** @return array{v: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>} the draft, as this version reads it */
    public function draftDoc(): array
    {
        return self::read($this->draft);
    }

    /** @return array{v: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}|null */
    public function publishedDoc(): ?array
    {
        return $this->published === null ? null : self::read($this->published);
    }

    /** A kept document, read as this version's (an unreadable one: a blank page, never an error on the public page). */
    public static function read(mixed $doc): array
    {
        try {
            return PageDoc::normalize(is_array($doc) ? $doc : []);
        } catch (\Throwable) {
            return PageDoc::upgrade(is_array($doc) ? $doc : []) + ['settings' => [], 'blocks' => []];
        }
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('academy_id', $this->getAttribute('academy_id'));
    }
}
