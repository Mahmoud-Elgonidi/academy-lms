<?php

namespace Modules\Quran\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Quran\Contracts\DTOs\VerseRange;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * The passage of the Quran a curriculum lesson reads (its surah and verses),
 * kept by the Quran module beside the lesson (quran_lesson_passages): what a
 * report reviews on the hifz map, where the lesson's mushaf opens, and what
 * homework is suggested to recite. Written through SetLessonPassage.
 *
 * @property string $id
 * @property string $node_id
 * @property int $surah
 * @property int $from_verse
 * @property int $to_verse
 */
final class LessonPassage extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'quran_lesson_passages';

    protected $fillable = ['node_id', 'surah', 'from_verse', 'to_verse'];

    protected function casts(): array
    {
        return ['surah' => 'integer', 'from_verse' => 'integer', 'to_verse' => 'integer'];
    }

    public function range(): VerseRange
    {
        return VerseRange::of($this->surah, $this->from_verse, $this->to_verse);
    }
}
