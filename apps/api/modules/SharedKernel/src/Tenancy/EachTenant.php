<?php

namespace Modules\SharedKernel\Tenancy;

use Closure;
use Throwable;

/**
 * Runs a sweeper once per active academy, as that academy. One academy's
 * failure is reported (the platform's recent errors) and the others still
 * run: a sweeper never stops at the first academy that throws.
 */
final class EachTenant
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @param  Closure(TenantRef): int  $work
     * @return int the sum of what each run returned
     */
    public function sum(Closure $work): int
    {
        $total = 0;
        foreach ($this->resolver->active() as $academy) {
            try {
                $total += (int) $this->tenants->run($academy, fn () => $work($academy));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $total;
    }
}
