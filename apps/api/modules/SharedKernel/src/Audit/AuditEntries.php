<?php

namespace Modules\SharedKernel\Audit;

use Carbon\CarbonImmutable;

/**
 * Audit entries as people read them: a sentence for the action (wording in
 * shared::audit), who did it (the platform team named, and on whose
 * account), and when, on the reader's clock.
 */
final class AuditEntries
{
    /** Action prefixes by group, for filters. */
    public const GROUPS = [
        'billing' => ['billing.'],
        'scheduling' => ['policy.', 'session.', 'shift.', 'enrollment.'],
        'payroll' => ['payroll.'],
        'import' => ['import.'],
        'identity' => ['identity.'],
        'impersonation' => ['impersonation.'],
        'platform' => ['academy.', 'platform.'],
    ];

    /**
     * @param  object{action: string, data: mixed, actor_type: string, actor_name: ?string, on_behalf_of: ?string, created_at: mixed}  $row
     * @param  array<string, string>  $names  user id => name, for "on behalf of"
     * @return array{title: string, actor: string, when: string, platform: bool, group: ?string}
     */
    public static function describe(object $row, string $tz, array $names = []): array
    {
        $data = is_string($row->data) ? (json_decode($row->data, true) ?: []) : (array) $row->data;
        $params = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $key = (string) $key;
                $params[$key] = trans()->has("shared::audit.values.{$key}.{$value}") ? __("shared::audit.values.{$key}.{$value}") : (string) $value;
            }
        }
        $title = trans()->has('shared::audit.actions.'.$row->action) ? __('shared::audit.actions.'.$row->action, $params) : $row->action;

        $actor = match ($row->actor_type) {
            'platform' => $row->on_behalf_of
                ? __('shared::audit.actor.platform_as', ['name' => (string) $row->actor_name, 'user' => $names[$row->on_behalf_of] ?? '—'])
                : __('shared::audit.actor.platform', ['name' => (string) $row->actor_name]),
            'system' => __('shared::audit.actor.system'),
            default => (string) $row->actor_name,
        };

        return [
            'title' => $title,
            'actor' => $actor,
            'when' => CarbonImmutable::parse($row->created_at)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y · H:i'),
            'platform' => $row->actor_type === 'platform',
            'group' => self::group($row->action),
        ];
    }

    public static function group(string $action): ?string
    {
        foreach (self::GROUPS as $group => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($action, $prefix)) {
                    return $group;
                }
            }
        }

        return null;
    }
}
