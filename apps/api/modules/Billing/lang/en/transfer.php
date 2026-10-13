<?php

// The academy's transfer details: where a family sends the money by hand (SaveTransferDetails, Queries\TransferDetails).
return [
    'title' => 'Where to transfer',
    'for' => 'For invoice :numbers',
    'copy' => 'Copy',
    'copied' => 'Copied ✓',
    'settings' => [
        'academy' => 'Transfer details for every payer',
        'academy_hint' => 'The bank, the account holder and the IBAN, or an InstaPay, Vodafone Cash or STC Pay number, and where to send the receipt. Plain text, up to 1,000 characters. Families see it with every invoice they still have to pay and can copy it; without it they can only pay online.',
        'regions' => 'Different details for a price region',
        'regions_hint' => 'Payers in a region with its own text read it instead of the one above (InstaPay and Vodafone Cash for Egypt, say, and a bank account for the Gulf). Leave it empty to use the one above.',
        'region' => ':name · :currency',
        'saved' => 'Transfer details saved.',
    ],
    'errors' => [
        'too_long' => 'Keep it to :max characters.',
        'markup' => 'Plain text only, without HTML.',
        'region' => 'That is not one of the academy\'s price regions.',
    ],
];
