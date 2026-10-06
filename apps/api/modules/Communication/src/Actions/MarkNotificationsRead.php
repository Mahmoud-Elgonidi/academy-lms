<?php

namespace Modules\Communication\Actions;

use Carbon\CarbonImmutable;
use Modules\Communication\Models\Notification;

/** Marks some (or all) of a person's notifications read; returns how many changed. */
final class MarkNotificationsRead
{
    /** @param  list<string>|null  $ids  null: all of them */
    public function handle(string $userId, ?array $ids = null): int
    {
        return Notification::query()->where('user_id', $userId)->whereNull('read_at')
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->update(['read_at' => CarbonImmutable::now()]);
    }
}
