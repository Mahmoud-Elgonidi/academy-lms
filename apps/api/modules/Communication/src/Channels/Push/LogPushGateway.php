<?php

namespace Modules\Communication\Channels\Push;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Development: writes what would be pushed to the log instead. */
final class LogPushGateway implements PushGateway
{
    public function name(): string
    {
        return 'log';
    }

    public function send(array $tokens, string $title, string $body, array $data): PushResult
    {
        Log::info('Push to '.count($tokens)." device(s): {$title} — {$body}", $data);

        return new PushResult(array_map(fn () => 'log-'.Str::uuid7(), $tokens));
    }
}
