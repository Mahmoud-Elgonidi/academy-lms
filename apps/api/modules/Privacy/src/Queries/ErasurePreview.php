<?php

namespace Modules\Privacy\Queries;

use Modules\SharedKernel\Privacy\PersonalData;

/**
 * Before the academy approves an erasure: what holds it back, from every
 * module (lessons still to come, courses running, money due, a child left
 * without a guardian, the last owner), and what goes and what stays.
 */
final class ErasurePreview
{
    /** What an erasure removes, and what the law or the academy's records keep (shown to the academy and to whoever asks). */
    public const GOES = ['account', 'profile', 'messages', 'homework', 'ratings', 'enquiries'];

    public const STAYS = ['invoices', 'lessons', 'pay', 'consents', 'tickets', 'audit'];

    public function __construct(private readonly PersonalData $data) {}

    /** @return list<string> */
    public function blockers(string $userId): array
    {
        $out = [];
        foreach ($this->data->sources() as $source) {
            array_push($out, ...$source->blockers($userId));
        }

        return $out;
    }
}
