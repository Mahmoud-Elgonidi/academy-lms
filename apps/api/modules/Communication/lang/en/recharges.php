<?php

// The academy's recharges, to the staff who see the plan (TellStaffAboutRecharges).
return [
    'how' => [
        'card' => 'by card',
        'auto' => 'by automatic recharge',
        'transfer' => 'by transfer',
    ],
    'declines' => [
        'declined' => 'The bank declined the payment.',
        'insufficient_funds' => 'The card does not have enough funds.',
        'expired_card' => 'The card has expired, and was removed from the academy.',
        'lost_card' => 'The bank has stopped the card, and it was removed from the academy.',
        'invalid_card' => 'The card is no longer valid, and was removed from the academy.',
        'authentication_required' => 'The bank asked the card holder to confirm the payment.',
        'processing_error' => 'The bank could not process the payment.',
    ],
    'then' => [
        'retry' => 'We will try again in a day.',
        'off' => 'Automatic recharge is now off; you can buy a pack, or turn it back on with another card.',
    ],
    'off' => [
        'no_card' => 'There is no valid saved card.',
        'no_price' => "The chosen pack no longer has a price in the academy's currency.",
    ],
    'recharge_succeeded' => [
        'title' => ':amount added to the :meter balance',
        'body' => 'The payment of :price went through :how; bought balance lasts 12 months. Receipt :receipt. Details: :link',
    ],
    'recharge_failed' => [
        'title' => 'Automatic recharge of :meter did not go through',
        'body' => ':reason :then Details: :link',
    ],
    'auto_recharge_off' => [
        'title' => 'Automatic recharge of :meter is off',
        'body' => ':reason You can turn it back on from the usage page: :link',
    ],
];
