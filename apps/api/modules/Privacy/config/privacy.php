<?php

return [
    // The platform's own privacy notice, terms and account-deletion page (on its host, the app stores' links):
    // the name it goes by there and the address people write to about their data. An academy's own pages take
    // both from its privacy settings.
    'platform_name' => env('PLATFORM_LEGAL_NAME') ?: env('APP_NAME', 'Academy LMS'),
    'contact_email' => env('PRIVACY_CONTACT_EMAIL'),
];
