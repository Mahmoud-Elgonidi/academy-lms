<?php

namespace Modules\People\Queries\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;
use Modules\People\Models\StudentProfile;
use Modules\People\Models\TeacherProfile;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * People's read-only view of the users table, for the teachers', students'
 * and guardians' screens (docs/architecture/04, rule 2: reads may cross
 * modules; accounts change through IdentityApi). Saving it is refused.
 *
 * @property string $id
 * @property string $name
 * @property ?string $email
 * @property ?string $phone_e164
 * @property ?string $username
 * @property ?string $locale
 * @property ?string $timezone
 * @property string $status
 * @property bool $is_managed_minor
 * @property CarbonImmutable $created_at
 */
final class Person extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $table = 'users';

    protected $keyType = 'string';

    protected $hidden = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'];

    protected function casts(): array
    {
        return ['is_managed_minor' => 'boolean', 'created_at' => 'immutable_datetime'];
    }

    /** @param  Builder<self>  $query */
    public function scopeWithRole(Builder $query, string $role): void
    {
        $query->whereExists(fn ($q) => $q->from('user_roles')->whereColumn('user_roles.academy_id', 'users.academy_id')
            ->whereColumn('user_roles.user_id', 'users.id')->where('user_roles.role', $role));
    }

    /** @return HasOne<TeacherProfile, $this> */
    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class, 'user_id');
    }

    /** @return HasOne<StudentProfile, $this> */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class, 'user_id');
    }

    /** @return BelongsToMany<self, $this> a student's guardians, with how each is linked */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'guardian_student', 'student_id', 'guardian_id')
            ->withPivot(['relationship', 'is_primary', 'receives_reports', 'created_at']);
    }

    /** @return BelongsToMany<self, $this> a guardian's children */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'guardian_student', 'guardian_id', 'student_id')
            ->withPivot(['relationship', 'is_primary', 'receives_reports', 'created_at']);
    }

    /** Whether they chose a password yet (else their welcome link is still how they get in). */
    public function hasPassword(): bool
    {
        return $this->getAttribute('password') !== null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Email and number as one line ("—" for a child under a guardian), each kept left to right inside Arabic text. */
    public function contact(): string
    {
        $ltr = fn (?string $s) => $s === null ? null : "\u{2066}{$s}\u{2069}";

        return implode(' · ', array_filter([$ltr($this->email), $ltr($this->phone_e164)])) ?: '—';
    }

    public function save(array $options = []): bool
    {
        throw new LogicException('Accounts change through IdentityApi.');
    }
}
