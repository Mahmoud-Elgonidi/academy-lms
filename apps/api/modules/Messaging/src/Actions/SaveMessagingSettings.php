<?php

namespace Modules\Messaging\Actions;

use Modules\Messaging\Models\MessagingSettings;
use Modules\SharedKernel\Audit\AuditTrail;

/** Switches messages between teachers and families on or off for the academy (conversations.manage); off, every conversation stays readable. */
final class SaveMessagingSettings
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(bool $enabled): void
    {
        $settings = MessagingSettings::query()->first() ?? new MessagingSettings;
        $settings->fill(['enabled' => $enabled])->save();
        $this->audit->record('messaging.settings_saved', null, null, ['state' => $enabled ? 'on' : 'off']);
    }
}
