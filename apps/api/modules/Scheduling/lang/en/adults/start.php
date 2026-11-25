<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../start.php.
// The trainee starts their own programme and pays: every line speaks to them.
return [
    'why' => [
        'family_only' => 'You cannot start the programme from this account. Contact the academy.',
        'self_serve_off' => 'The academy enrols trainees itself. Message them to choose the plan, the trainer and the times together.',
        'has_course' => 'You are already enrolled in :subject. Open the programme from the sessions page, or message the academy to change the plan or the times.',
        'course_pending_own' => 'You started a :subject programme and it is waiting for payment. Pay its invoice to keep the sessions, or call it off to start again.',
        'course_pending' => 'The academy enrolled you in :subject and its invoice is waiting for payment. Pay it from the invoices page to keep the sessions.',
        'course_paused' => 'Your :subject programme is paused until :until. Message the academy to resume it instead of starting a new one.',
        'course_ending' => 'Your :subject programme ends on :on. You can start a new one from that day, or message the academy.',
        'consent' => 'Your account needs a consent before the programme starts. Contact the academy, then carry on where you left off.',
        'times_needed' => 'Choose the trainer and the weekly times for this plan.',
        'teacher_unavailable' => 'This trainer no longer takes new trainees in this area. Choose another trainer from the list.',
        'academy_full' => 'The academy is not taking new trainees right now. Message them to hear when a place opens.',
        'cancel_not_yours' => 'There is no programme you started with this number.',
        'cancel_started' => 'This programme cannot be called off here: it is no longer waiting for its first payment. Message the academy to pause or end it.',
        'cancel_paid' => 'Part of this programme\'s invoice is paid, so it cannot be called off here. Message the academy.',
        'cancel_proof' => 'You sent a proof of payment for this programme and it is being checked. Wait for the academy\'s answer, or message them to call it off.',
        'cancel_checkout' => 'You started paying for this programme online. Wait a few minutes for the payment page to close, then call it off if you wish.',
    ],
    'offer' => [
        'ended' => 'The free trial\'s offer ended before the request was complete, so the programme started at its usual price.',
        'family_discount_bigger' => 'The multi-trainee discount is bigger than your offer, so it applies instead; the two never add up.',
        'not_for_plan' => 'Your offer does not cover this plan, so the programme started at its usual price.',
        'package' => 'The programme joined your shared package, so it has no invoice of its own.',
    ],
    'skipped' => [
        'holiday' => "No session on :day: the academy's holiday",
        'teacher_leave' => 'No session on :day: the trainer is on leave',
    ],
    'pay' => [
        'by' => '{0} Pay before :when to keep the first session; a session still unpaid when it starts is cancelled.|{1} Pay before :when to keep the first session; a session still unpaid one hour before it starts is cancelled.|[2,*] Pay before :when to keep the first session; a session still unpaid :count hours before it starts is cancelled.',
        'any_time' => 'Pay before the first session to keep the sessions.',
        'package' => 'Nothing to pay now: its sessions come from your shared package.',
        'package_unpaid' => 'Its sessions start once your shared package\'s invoice is paid.',
        'group_forming' => 'The group starts when it has enough trainees; we will tell you the first session. Pay the invoice to keep your seat.',
    ],
    'renews' => '{0} The next block\'s invoice comes when no sessions are left, and you can stop any time.|{1} The next block\'s invoice comes when one session is left, and you can stop any time.|[2,*] The next block\'s invoice comes when :count sessions are left, and you can stop any time.',
    'cancelled' => 'The programme is called off and nothing was charged. You can start again any time.',
    'cancel_reason' => 'Called off by the account holder before paying',
    'welcome' => [
        'first_later' => 'when the group has enough trainees; we will send you its time and link',
    ],
];
