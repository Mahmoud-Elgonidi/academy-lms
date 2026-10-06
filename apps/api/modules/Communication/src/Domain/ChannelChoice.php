<?php

namespace Modules\Communication\Domain;

/**
 * A person's choice for one category of messages: every channel the
 * template uses, the same without WhatsApp, or the inbox only.
 */
final class ChannelChoice
{
    public const CHOICES = ['all', 'no_whatsapp', 'app'];

    /**
     * @param  list<string>  $templateChannels
     * @return list<string>
     */
    public static function channels(string $choice, array $templateChannels): array
    {
        return array_values(match ($choice) {
            'app' => [],
            'no_whatsapp' => array_diff($templateChannels, ['whatsapp']),
            default => $templateChannels,
        });
    }
}
