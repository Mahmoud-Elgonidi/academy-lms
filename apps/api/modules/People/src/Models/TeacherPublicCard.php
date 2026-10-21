<?php

namespace Modules\People\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A teacher's card on the academy's public page, in their own words
 * (Actions\Cards\SaveTeacherCard): shown while they switch it on and the
 * academy has not taken it off (HideTeacherCard).
 *
 * @property string $id
 * @property string $teacher_id
 * @property string $display_name
 * @property ?string $bio
 * @property ?string $photo_path on the public disk
 * @property bool $shown the teacher's own switch
 * @property bool $hidden_by_academy
 * @property CarbonImmutable $updated_at
 */
final class TeacherPublicCard extends Model
{
    use BelongsToTenant;
    use HasUuids;

    /** Photos are seen by anyone who opens the page, so they live on the public disk (like the academy's logo). */
    public const DISK = 'public';

    public const MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    public const MAX_KB = 1024;

    protected $fillable = ['teacher_id', 'display_name', 'bio', 'photo_path', 'shown', 'hidden_by_academy'];

    protected $attributes = ['shown' => false, 'hidden_by_academy' => false];

    protected function casts(): array
    {
        return ['shown' => 'boolean', 'hidden_by_academy' => 'boolean', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    public static function directory(string $academyId): string
    {
        return "site/{$academyId}/teachers";
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path !== null ? Storage::disk(self::DISK)->url($this->photo_path) : null;
    }
}
