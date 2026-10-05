<?php

namespace Modules\Identity\Filament;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Avatars drawn locally from the user's initials. Filament's default sends
 * every user's name to an outside service; this never leaves the server.
 */
final class InitialsAvatar implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $name = trim((string) $record->getAttribute('name'));
        $words = array_values(array_filter(preg_split('/\s+/u', preg_replace('/^(أ\.|د\.|Mr\.|Ms\.|Dr\.)\s*/u', '', $name)) ?: []));
        $initials = mb_substr($words[0] ?? '?', 0, 1).(isset($words[1]) ? mb_substr($words[1], 0, 1) : '');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" rx="32" fill="#E3F1EC"/>'
            .'<text x="32" y="41" font-family="system-ui, sans-serif" font-size="26" font-weight="700" fill="#0A5A49" text-anchor="middle">'
            .htmlspecialchars($initials, ENT_XML1).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
