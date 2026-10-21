<?php

namespace Modules\Learning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * The mushaf page the teacher has open in a Quran lesson: the student's
 * screen follows it. Changed only through Actions\Mushaf\TurnMushafPage.
 *
 * @property string $id
 * @property string $session_id
 * @property int $page
 */
final class LessonMushaf extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'lesson_mushaf';

    protected $fillable = ['session_id', 'page'];

    protected function casts(): array
    {
        return ['page' => 'integer'];
    }
}
