<?php

namespace Modules\Communication\Queries;

use Illuminate\Support\Facades\DB;
use Modules\Communication\Channels\Recipient;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\TimeFormat;

/** The people a message goes to, read from Identity's users (reads may cross modules). */
final class Recipients
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param  list<string>  $ids
     * @return array<string, Recipient> active users only, by id
     */
    public function find(array $ids): array
    {
        $academy = $this->tenant->require();

        return DB::table('users')->whereIn('id', array_values(array_unique($ids)))->where('status', 'active')
            ->get(['id', 'name', 'email', 'phone_e164', 'locale', 'timezone', 'time_format'])
            ->mapWithKeys(fn (object $u) => [$u->id => new Recipient(
                $u->id, $u->name, $u->email, $u->phone_e164,
                in_array($u->locale, ['ar', 'en'], true) ? $u->locale : $academy->defaultLocale,
                $u->timezone ?: $academy->defaultTimezone,
                (TimeFormat::of($u->time_format) ?? TimeFormat::of($academy->timeFormat) ?? TimeFormat::DEFAULT)->value,
            )])
            ->all();
    }

    public function one(string $id): ?Recipient
    {
        return $this->find([$id])[$id] ?? null;
    }
}
