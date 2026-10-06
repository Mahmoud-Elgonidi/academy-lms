<?php

namespace Modules\SharedKernel\Tenancy;

use Closure;

/** Runs a sweeper once per active academy, as that academy. */
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
            $total += (int) $this->tenants->run($academy, fn () => $work($academy));
        }

        return $total;
    }
}
