<?php

namespace Modules\SharedKernel\Tenancy;

use Illuminate\Database\Eloquent\Model;

/**
 * For models whose table has academy_id and an RLS policy.
 *
 * Adds TenantScope and fills academy_id from the current tenant on create.
 * Creating a row with no tenant set throws instead of writing a NULL.
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            if (! $model->getAttribute('academy_id')) {
                $model->setAttribute('academy_id', app(TenantContext::class)->require()->id);
            }
        });
    }
}
