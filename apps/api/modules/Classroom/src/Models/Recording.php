<?php

namespace Modules\Classroom\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A lesson's recording, made by the video provider (LiveKit Egress) into the
 * academy's folder: starting, recording, ending, then ready (or failed). The
 * file goes when its days are up unless it is held (a dispute, a complaint);
 * the row stays, marked deleted.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $session_id
 * @property string $room_id
 * @property string $provider
 * @property ?string $egress_id
 * @property string $media video | audio
 * @property string $status starting | recording | ending | ready | failed | deleted
 * @property ?string $started_by null: started by itself
 * @property ?string $stopped_by
 * @property ?CarbonImmutable $started_at
 * @property ?CarbonImmutable $ended_at
 * @property ?int $duration_sec
 * @property ?int $size bytes
 * @property string $disk
 * @property string $path
 * @property string $mime
 * @property ?CarbonImmutable $expires_at
 * @property ?CarbonImmutable $held_at
 * @property ?string $held_by
 * @property ?string $hold_reason
 * @property ?CarbonImmutable $deleted_at
 * @property ?string $deleted_by null: its days were up
 * @property ?string $delete_reason
 * @property ?string $error
 * @property CarbonImmutable $created_at
 */
final class Recording extends Model
{
    use BelongsToTenant;
    use HasUuids;

    /** Still being made: one at a time per lesson (the database holds it). */
    public const RUNNING = ['starting', 'recording', 'ending'];

    protected $fillable = ['session_id', 'room_id', 'provider', 'egress_id', 'media', 'status', 'started_by', 'stopped_by', 'started_at', 'ended_at',
        'duration_sec', 'size', 'disk', 'path', 'mime', 'expires_at', 'held_at', 'held_by', 'hold_reason', 'deleted_at', 'deleted_by', 'delete_reason', 'error'];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime',
            'held_at' => 'immutable_datetime', 'deleted_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime',
            'duration_sec' => 'integer', 'size' => 'integer',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeRunning(Builder $query): void
    {
        $query->whereIn('status', self::RUNNING);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, self::RUNNING, true);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    /** Where a lesson's recording goes in the academy's folder. */
    public static function pathFor(string $academyId, string $sessionId, string $id, string $media): string
    {
        return "academies/{$academyId}/recordings/{$sessionId}/{$id}.".($media === 'audio' ? 'ogg' : 'mp4');
    }
}
