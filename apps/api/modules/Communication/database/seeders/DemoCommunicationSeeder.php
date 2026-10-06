<?php

namespace Modules\Communication\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Communication\Actions\SaveCommunicationSettings;

/**
 * The demo academy sends on every channel. Locally WhatsApp and push are
 * written to the log, and email goes to Mailpit (http://localhost:8025)
 * under docker compose. Runs first, so the messages the other demo
 * seeders cause go out on all of them.
 */
final class DemoCommunicationSeeder extends Seeder
{
    public function run(SaveCommunicationSettings $settings): void
    {
        $settings->handle(['email_enabled' => true, 'whatsapp_enabled' => true, 'push_enabled' => true]);
    }
}
