<?php

namespace Modules\People\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\People\Actions\Consent\GrantConsent;
use Modules\People\Actions\Consent\RecordConsent;

/**
 * Guardians' consent in the demo: most guardians agreed from their account,
 * Omar's father from the link in the request, the office recorded Layan's on
 * the paper form, and Rana's father has not answered yet (her sign-in waits,
 * and the office sees her under "Waiting"). The trial children the CRM made
 * accounts for wait too, as they would.
 */
final class DemoConsentsSeeder extends Seeder
{
    public function run(GrantConsent $grant, RecordConsent $record): void
    {
        $id = fn (string $email) => (string) DB::table('users')->where('email', $email)->value('id');
        $owner = $id('owner@demo.test');

        foreach (['yousef' => 'family', 'ziad' => 'family', 'maryam' => 'family', 'adam' => 'family', 'hamza' => 'family', 'zain' => 'family', 'omar' => 'link'] as $key => $via) {
            $grant->handle($id("{$key}@demo.test"), $id("{$key}.guardian@demo.test"), $via, '203.0.113.'.(10 + strlen($key)), 'Demo');
        }
        $record->handle($id('layan@demo.test'), $id('layan.guardian@demo.test'), 'نموذج موافقة ورقي موقّع من الأب، محفوظ في ملف الطالبة.', $owner);
    }
}
