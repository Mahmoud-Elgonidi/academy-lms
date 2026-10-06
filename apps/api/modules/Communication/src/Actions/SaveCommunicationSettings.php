<?php

namespace Modules\Communication\Actions;

use Modules\Communication\Models\CommunicationSettings;

/** Which outside channels the academy uses. */
final class SaveCommunicationSettings
{
    /** @param  array{email_enabled?: bool, whatsapp_enabled?: bool, push_enabled?: bool}  $data */
    public function handle(array $data): CommunicationSettings
    {
        $settings = CommunicationSettings::current();
        $settings->fill(array_map('boolval', array_intersect_key($data, array_flip(['email_enabled', 'whatsapp_enabled', 'push_enabled']))))->save();

        return $settings;
    }
}
