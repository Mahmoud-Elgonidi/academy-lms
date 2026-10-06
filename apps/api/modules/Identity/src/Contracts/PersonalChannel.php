<?php

namespace Modules\Identity\Contracts;

/**
 * Each person's own private broadcast channel. Only that person may listen
 * (checked on /broadcasting/auth); modules send them signals on it, such as
 * "your inbox changed" or "your lessons changed", with no data.
 */
final class PersonalChannel
{
    public const PATTERN = 'user.{id}';

    public static function name(string $userId): string
    {
        return "user.{$userId}";
    }
}
