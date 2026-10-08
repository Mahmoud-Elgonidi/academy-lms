<?php

namespace Modules\Support\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One line on a ticket: a message between the requester and the academy, an
 * internal note the requester never sees, or what changed (assigned,
 * priority, resolved, reopened, closed, the complaint's decision). Never
 * changed or deleted (the database refuses).
 *
 * @property string $id
 * @property string $ticket_id
 * @property ?string $author_id
 * @property string $side requester | staff | system
 * @property string $kind message | note | event
 * @property ?string $body
 * @property ?string $event
 * @property array<string, mixed> $data
 * @property ?string $file_path
 * @property ?string $file_name
 * @property ?string $mime
 * @property ?int $size
 * @property CarbonImmutable $created_at
 */
final class TicketMessage extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const UPDATED_AT = null;

    /** A screenshot, a photo or a PDF. */
    public const MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'application/pdf'];

    public const MAX_KB = 10 * 1024;

    public const DISK = 'local';

    /** What the requester sees of the changes: the rest (assignments, priority, the complaint's decision) is the academy's. */
    public const PUBLIC_EVENTS = ['resolved', 'reopened', 'closed'];

    protected $fillable = ['ticket_id', 'author_id', 'side', 'kind', 'body', 'event', 'data', 'file_path', 'file_name', 'mime', 'size'];

    protected $attributes = ['data' => '{}'];

    protected function casts(): array
    {
        return ['data' => 'array', 'size' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    /** Whether the requester sees it: messages, and the changes that are theirs to know. */
    public function isPublic(): bool
    {
        return $this->kind === 'message' || ($this->kind === 'event' && in_array($this->event, self::PUBLIC_EVENTS, true));
    }

    /** @param  array<string, mixed>  $data */
    public static function event(Ticket $ticket, string $event, ?string $authorId, array $data = [], string $side = 'staff'): self
    {
        return self::query()->create(['ticket_id' => $ticket->id, 'author_id' => $authorId, 'side' => $side, 'kind' => 'event', 'event' => $event, 'data' => $data]);
    }
}
