<?php

namespace Modules\Communication\Channels\Push;

final readonly class PushResult
{
    /**
     * @param  list<string>  $messageIds
     * @param  list<string>  $deadTokens  tokens the provider no longer knows (app removed): forget them
     */
    public function __construct(public array $messageIds, public array $deadTokens = []) {}
}
