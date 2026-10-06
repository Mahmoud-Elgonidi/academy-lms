<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\Scheduling\Domain\Policy\PolicySettings;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One version of the academy's policy numbers. Never edited (the database
 * refuses UPDATE and DELETE for the app role): a change is a new version.
 *
 * @property string $id
 * @property int $version
 * @property array<string, int> $settings
 * @property ?string $created_by
 * @property CarbonImmutable $created_at
 */
final class PolicyVersion extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['version', 'settings', 'created_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'settings' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** The version in force; the defaults become version 1 the first time they are needed. */
    public static function current(): self
    {
        return self::query()->orderByDesc('version')->first()
            ?? self::query()->create(['version' => 1, 'settings' => PolicySettings::defaults()->toArray()]);
    }

    /** The numbers in force, for reading (the defaults when the academy never saved any). */
    public static function settingsInForce(): PolicySettings
    {
        return self::query()->orderByDesc('version')->first()?->policy() ?? PolicySettings::defaults();
    }

    public function policy(): PolicySettings
    {
        return PolicySettings::fromArray($this->settings);
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Policy versions are never edited; save a new version.');
        }

        return parent::save($options);
    }
}
