<?php

namespace Modules\Identity\Preferences;

use Modules\Identity\Contracts\Preferences\PreferenceDefinition;

/**
 * Whether the person shows the floating zone chip on the panels, the portal
 * and the app (GET/PATCH me `zone_chip`): hidden from the chip's own card,
 * shown again from My settings, the portal's account page or the app's.
 */
final class ZoneChipPreference
{
    public const KEY = 'zone_chip';

    public const SHOWN = 'shown';

    public const HIDDEN = 'hidden';

    public static function definition(): PreferenceDefinition
    {
        return new PreferenceDefinition(
            key: self::KEY,
            label: 'identity::settings.zone_chip.label',
            options: [self::SHOWN => 'identity::settings.zone_chip.shown', self::HIDDEN => 'identity::settings.zone_chip.hidden'],
            default: self::SHOWN,
            hint: 'identity::settings.zone_chip.hint',
        );
    }

    public static function value(bool $shown): string
    {
        return $shown ? self::SHOWN : self::HIDDEN;
    }
}
