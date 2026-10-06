<?php

namespace Modules\Communication\Channels\Push;

interface PushGateway
{
    public function name(): string;

    /**
     * @param  list<string>  $tokens
     * @param  array<string, string>  $data  what the app opens
     */
    public function send(array $tokens, string $title, string $body, array $data): PushResult;
}
