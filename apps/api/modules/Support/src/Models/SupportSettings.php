<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;
use Modules\Support\Enums\TicketPriority;

/**
 * The academy's answer targets by priority (docs/architecture/02, section
 * 12): hours to the first answer and to the solution, counted from when the
 * ticket came in. Without a row, the defaults.
 *
 * @property array<string, array{respond: int, resolve: int}> $targets
 */
final class SupportSettings extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['targets'];

    protected function casts(): array
    {
        return ['targets' => 'array'];
    }

    /** @return array{respond: int, resolve: int} */
    public static function targetsFor(TicketPriority $priority): array
    {
        return self::targets()[$priority->value];
    }

    /** @return array<string, array{respond: int, resolve: int}> every priority, the academy's or the default */
    public static function targets(): array
    {
        $own = self::query()->value('targets');
        $own = is_string($own) ? json_decode($own, true) : ($own ?? []);
        $out = [];
        foreach (TicketPriority::cases() as $p) {
            $out[$p->value] = [
                'respond' => (int) ($own[$p->value]['respond'] ?? $p->defaultTargets()['respond']),
                'resolve' => (int) ($own[$p->value]['resolve'] ?? $p->defaultTargets()['resolve']),
            ];
        }

        return $out;
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('academy_id', $this->getAttribute('academy_id'));
    }
}
