<?php

namespace Modules\Messaging\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One message, as everyone reads it: a phone number, an email, a link or a
 * handle in it was hidden before it was saved. The words as typed are kept
 * (encrypted) only when something was hidden, for the academy's oversight.
 * Written once: the database refuses changes.
 *
 * @property string $id
 * @property string $conversation_id
 * @property string $author_id
 * @property string $side teacher | student | guardian
 * @property string $body
 * @property list<string> $hidden phone | email | link | handle
 * @property list<string> $flags outside_app
 * @property ?string $original
 * @property CarbonImmutable $created_at
 */
final class ConversationMessage extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['conversation_id', 'author_id', 'side', 'body', 'hidden', 'flags', 'original'];

    protected $attributes = ['hidden' => '[]', 'flags' => '[]'];

    protected function casts(): array
    {
        return ['hidden' => 'array', 'flags' => 'array', 'original' => 'encrypted', 'created_at' => 'immutable_datetime'];
    }
}
