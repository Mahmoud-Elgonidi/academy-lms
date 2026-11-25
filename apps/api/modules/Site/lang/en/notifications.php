<?php

return [
    'audience' => [
        'requester' => 'Whoever asked for a free trial on the academy\'s page (by WhatsApp and email: they have no account yet)',
        'requester_adults' => 'Whoever asked for an introductory session on an adults\' academy page (by WhatsApp and email: they have no account yet)',
        'staff' => 'Who answers it (sales for trial lessons, who runs the teachers for teaching)',
    ],
    'kinds' => ['trial' => 'a free trial lesson', 'teach' => 'to teach with you'],
    'request_new' => ['title' => "A new request from the academy's page", 'body' => 'Someone asked for :kind. Open "Page requests" and get in touch.'],
    'request_received' => [
        'title' => 'We have your request, :name',
        'body' => 'Thank you for asking for a free trial lesson. We will be in touch within :hours hours. :next',
        'pick' => 'Or choose the lesson\'s time yourself here: :link',
        'wait' => 'We will agree on a time that suits you.',
    ],
    // A page for adults (settings.audience): the same reply in training words.
    'request_received_adults' => [
        'title' => 'We have your request, :name',
        'body' => 'Thank you for asking for a free introductory session. We will be in touch within :hours hours. :next',
        'pick' => 'Or choose your session\'s time yourself here: :link',
        'wait' => 'We will agree on a time that suits you.',
    ],
];
