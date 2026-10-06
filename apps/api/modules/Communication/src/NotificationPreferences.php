<?php

namespace Modules\Communication;

use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Domain\ChannelChoice;
use Modules\Communication\Domain\QuietHours;
use Modules\Identity\Contracts\Preferences\PreferenceDefinition;
use Modules\Identity\Contracts\Preferences\UserPreferences;

/**
 * Each person's notification choices, kept as Identity preferences so they
 * show on "My settings" and in the apps: per category, every channel / no
 * WhatsApp / the inbox only; and nightly quiet hours.
 */
final class NotificationPreferences
{
    public const QUIET_HOURS = 'notifications.quiet_hours';

    public function __construct(private readonly UserPreferences $preferences) {}

    public static function key(string $category): string
    {
        return "notifications.{$category}";
    }

    public function define(): void
    {
        foreach (TemplateDefinition::CATEGORIES as $category) {
            $this->preferences->define(new PreferenceDefinition(
                key: self::key($category),
                label: "communication::communication.preferences.categories.{$category}",
                options: array_combine(ChannelChoice::CHOICES, array_map(fn ($c) => "communication::communication.preferences.choices.{$c}", ChannelChoice::CHOICES)),
                default: 'all',
                hint: "communication::communication.preferences.hints.{$category}",
            ));
        }
        $this->preferences->define(new PreferenceDefinition(
            key: self::QUIET_HOURS,
            label: 'communication::communication.preferences.quiet.label',
            options: array_combine(QuietHours::CHOICES, array_map(fn ($c) => "communication::communication.preferences.quiet.{$c}", QuietHours::CHOICES)),
            default: '22-07',
            hint: 'communication::communication.preferences.quiet.hint',
        ));
    }

    public function choice(string $userId, string $category): string
    {
        return $this->preferences->get($userId, self::key($category));
    }

    public function quietHours(string $userId): QuietHours
    {
        return QuietHours::parse($this->preferences->get($userId, self::QUIET_HOURS));
    }

    /** @return array<string, string> key => value, for the apps */
    public function all(string $userId): array
    {
        $keys = [...array_map(fn ($c) => self::key($c), TemplateDefinition::CATEGORIES), self::QUIET_HOURS];

        return array_combine($keys, array_map(fn ($k) => $this->preferences->get($userId, $k), $keys));
    }

    public function set(string $userId, string $key, string $value): void
    {
        $this->preferences->set($userId, $key, $value);
    }
}
