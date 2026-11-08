<?php

// The academy's usage of its plan, to the staff who see the plan (TellStaffAboutUsage).
return [
    'audience' => 'The academy\'s owner and admins',
    'units' => [
        'video_minutes' => '{0} no minutes|{1} one minute|[2,*] :count minutes',
        'ai_credits' => '{0} no credits|{1} one credit|[2,*] :count credits',
        'days' => '{1} one day|[2,*] :count days',
    ],
    'then' => [
        'bought' => 'The balance you bought is in use now.',
        'bought_later' => 'Once it is used up, the balance you bought is used.',
        'video_minutes' => 'No lesson stops; what is used from now on comes off the next allowance on the first of the month, or off any balance you buy.',
        'video_minutes_later' => 'When it runs out no lesson stops; you can buy extra minutes or move lessons to Zoom or Google Meet.',
        'ai_credits' => 'The AI helpers have stopped and the work goes on by hand until the credits start again on the first of the month, or you buy extra credits.',
        'ai_credits_later' => 'When it runs out the AI helpers stop and the work goes on by hand.',
    ],
    'usage_half' => [
        'title' => 'Half of this month\'s :meter used',
        'body' => 'The academy has used :used of :allowance of this month\'s :meter. :then Details: :link',
    ],
    'usage_near' => [
        'title' => 'This month\'s :meter are nearly used up',
        'body' => 'The academy has used :used of :allowance of this month\'s :meter. :then Details: :link',
    ],
    'usage_full' => [
        'title' => 'This month\'s :meter are used up',
        'body' => 'The academy has used all of this month\'s :meter (:allowance). :then Details: :link',
    ],
    'usage_expiring' => [
        'title' => 'Some of your :meter end soon',
        'body' => ':left of the :meter you bought are left, and they end on :on. Use them before then. Details: :link',
    ],
    'usage_runout' => [
        'title' => 'Your :meter may run out in :days',
        'body' => 'This month\'s allowance is used up and :left of the balance you bought is left. At the last two weeks\' pace it runs out in about :days. Details: :link',
    ],
];
