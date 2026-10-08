<?php

namespace Modules\Classroom\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Which video provider the academy's lessons use, and whether they are
 * recorded; the platform default when it has no row.
 *
 * @property string $provider livekit | external
 * @property string $recording off | manual (the teacher starts it) | auto (every lesson whose people agreed)
 * @property string $recording_media by_age (video when a child is in the lesson, else sound) | video | audio
 * @property int $recording_days how long a recording is kept
 * @property bool $recording_family a family watches its own lessons' recordings
 * @property bool $recording_teacher a teacher watches their own lessons' recordings
 */
final class ClassroomSettings extends Model
{
    use BelongsToTenant;

    public const PROVIDERS = ['livekit', 'external'];

    public const RECORDING = ['off', 'manual', 'auto'];

    public const RECORDING_MEDIA = ['by_age', 'video', 'audio'];

    /** The days a recording may be kept: a week to a year (30 unless the academy chose). */
    public const RECORDING_DAYS = [7, 365];

    public $incrementing = false;

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['provider', 'recording', 'recording_media', 'recording_days', 'recording_family', 'recording_teacher'];

    protected $attributes = [
        'recording' => 'off',
        'recording_media' => 'by_age',
        'recording_days' => 30,
        'recording_family' => true,
        'recording_teacher' => true,
    ];

    protected function casts(): array
    {
        return ['recording_days' => 'integer', 'recording_family' => 'boolean', 'recording_teacher' => 'boolean'];
    }

    public static function current(): self
    {
        return self::query()->first() ?? new self(['provider' => config('classroom.provider', 'livekit')]);
    }

    public function records(): bool
    {
        return $this->recording !== 'off';
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('academy_id', $this->getAttribute('academy_id'));
    }
}
