<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Billing\Domain\BlockTerms;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $id
 * @property string $name_ar
 * @property string $name_en
 * @property int $session_minutes
 * @property int $sessions_per_block
 * @property int $sessions_per_week
 * @property int $validity_days
 * @property int $renew_at_sessions
 * @property bool $is_active
 * @property int $sort
 * @property string $lesson_format private (one student a lesson) | group (several students in one lesson)
 * @property string $credit_scope enrollment (each course its own balance) | student_pool (a shared package: one balance for all of a student's courses on it)
 */
final class Plan extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['name_ar', 'name_en', 'session_minutes', 'sessions_per_block', 'sessions_per_week', 'validity_days', 'renew_at_sessions', 'is_active', 'sort', 'credit_scope', 'lesson_format'];

    public const SHARED = 'student_pool';

    public const PRIVATE = 'private';

    public const GROUP = 'group';

    protected $attributes = ['renew_at_sessions' => 2, 'is_active' => true, 'sort' => 0, 'credit_scope' => 'enrollment', 'lesson_format' => 'private'];

    protected function casts(): array
    {
        return [
            'session_minutes' => 'integer',
            'sessions_per_block' => 'integer',
            'sessions_per_week' => 'integer',
            'validity_days' => 'integer',
            'renew_at_sessions' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<PlanPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    public function name(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }

    /** A shared package: "12 lessons in any subject", one balance for all of a student's courses on it. */
    public function isShared(): bool
    {
        return $this->credit_scope === self::SHARED;
    }

    /** Group lessons: several students in one lesson with one teacher. */
    public function isGroup(): bool
    {
        return $this->lesson_format === self::GROUP;
    }

    public function terms(): BlockTerms
    {
        return new BlockTerms($this->session_minutes, $this->sessions_per_block, $this->sessions_per_week, $this->validity_days, $this->renew_at_sessions);
    }
}
