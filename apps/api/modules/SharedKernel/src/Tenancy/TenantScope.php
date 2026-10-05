<?php

namespace Modules\SharedKernel\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Application-level twin of the RLS policy: rows of the current academy only,
 * and nothing at all when no academy is set.
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            $builder->whereRaw('false');

            return;
        }

        $builder->where($model->qualifyColumn('academy_id'), $tenantId);
    }
}
