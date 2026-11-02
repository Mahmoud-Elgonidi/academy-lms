<?php

namespace Modules\Scheduling\Support;

use Illuminate\Validation\ValidationException;

/**
 * Why a family cannot start a course on its own (docs/architecture/02,
 * section 27, J3): a reason the clients act on (time_taken: choose again from
 * the fresh times; price_changed: confirm the new price; consent: agree
 * first; has_course: open that course) and a kind sentence saying what to do
 * next (scheduling::start.why.{reason}), with what the client needs to do it.
 */
final class StartRefused extends ValidationException
{
    public string $reason = '';

    /** @var array<string, mixed> */
    public array $extra = [];

    public int $httpStatus = 422;

    /**
     * A sentence with a count (times_count) is a plural choice by it (trans_choice: Arabic's one, two, few and many).
     *
     * @param  array<string, string|int>  $replace
     * @param  array<string, mixed>  $extra
     */
    public static function because(string $reason, array $replace = [], array $extra = [], int $status = 422): self
    {
        $key = 'scheduling::start.why.'.$reason;
        $e = self::withMessages(['start' => isset($replace['count']) ? trans_choice($key, (int) $replace['count'], $replace) : (string) __($key, $replace)]);
        $e->reason = $reason;
        $e->extra = $extra;
        $e->httpStatus = $status;

        return $e;
    }
}
