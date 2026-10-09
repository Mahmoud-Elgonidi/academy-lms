<?php

use Modules\SharedKernel\Logging\Redactor;

it('masks people, tokens, signatures and secret fields in a log line, and keeps it readable', function () {
    $line = 'Sent to salma.guardian@demo.test and +966501234567 (also 0501234567) at 2026-10-09 06:42:01, lesson 01a11f65-2484-738a-82eb-0d8b58e0c861 '
        .'GET https://demo.lms.test/consent/x?g=1&expires=1791000000&signature=abc123def Authorization: Bearer 12|AbCdEfGhIjKlMnOpQrStUvWxYz0123456789abcdEF '
        .'{"password":"hunter22","code":"482913","two_factor_token":"t\"x","user":{"name":"Salma","phone_e164":"+201001234567"},"amount":94000}';

    expect(Redactor::text($line))->toBe(
        'Sent to ***@demo.test and ***67 (also ***67) at 2026-10-09 06:42:01, lesson 01a11f65-2484-738a-82eb-0d8b58e0c861 '
        .'GET https://demo.lms.test/consent/x?g=1&expires=1791000000&signature=[redacted] Authorization: Bearer [redacted] '
        .'{"password":"[redacted]","code":"[redacted]","two_factor_token":"[redacted]","user":{"name":"Salma","phone_e164":"***67"},"amount":94000}'
    );
});

it('leaves a line with nothing personal as it is', function () {
    $line = '[2026-10-09 06:42:01] local.INFO: revenue export 2026-10-01..2026-10-09 SAR rows=128 took 412ms {"academy":"01a11f65-20f6-71fb-b92a-6a5692e948bb","code":404}';

    expect(Redactor::text($line))->toBe($line);
});
