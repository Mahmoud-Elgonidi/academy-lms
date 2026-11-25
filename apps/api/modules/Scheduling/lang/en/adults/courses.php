<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../courses.php.
return [
    'errors' => [
        'ended' => 'This programme has ended. A new enrolment is the way back.',
        'ended_then' => 'The programme has ended by then.',
        'paused_then' => 'The programme is paused then (until :until).',
    ],
    'pause' => [
        'title' => 'Pause the programme',
        'hint' => 'For a holiday, a busy time at work or travel: the sessions on those days are cancelled and their minutes come back to the balance. The weekly times stay the trainee\'s; nothing renews while it is paused, and the programme comes back by itself the day after.',
        'reason' => 'Reason (for the office; the trainee does not see it)',
        'submit' => 'Pause the programme',
        'done' => 'Paused until :until: :lessons sessions cancelled, :minutes paid minutes back in the balance.',
    ],
    'resume' => [
        'title' => 'Resume the programme now',
        'hint' => 'Paused until :until. Sessions are made again from now at the usual times and funded from the balance; the credit keeps the days the pause gave it.',
        'hint_call_off' => 'A pause from :from until :until is set. Calling it off keeps the sessions going; the credit keeps the days the pause gave it.',
        'done' => 'The programme is on again: :lessons sessions made.',
    ],
    'end' => [
        'action' => 'End the programme',
        'title' => 'End the programme',
        'hint' => 'The booked sessions from that day are cancelled and their minutes come back to the balance; the weekly times end and the trainer\'s time is free; the programme never renews. To come back later, enrol the trainee again.',
        'on' => 'First day without sessions',
        'reason' => 'Reason (for the office; the trainee does not see it)',
        'submit' => 'End the programme',
        'done' => 'The programme ends from :on: :lessons sessions cancelled.',
    ],
    'preview' => [
        'lessons' => '{0} No booked sessions in those days.|{1} One booked session is cancelled (:minutes paid minutes back in the balance).|[2,*] :count booked sessions are cancelled (:minutes paid minutes back in the balance).',
        'shared' => 'Another programme still draws on this shared balance, so its validity stays as it is.',
        'back' => 'The programme comes back by itself on :day.',
        'invoice' => [
            'reissued' => 'The combined invoice :number waiting to be paid is voided, and the other programmes on it are invoiced again on a new one.',
            'shared' => 'The shared package\'s invoice :number stays for the trainee\'s other programmes on it.',
            'later' => 'The waiting block and extra sessions invoices, if any, are dealt with on that day.',
            'extra' => 'The extra sessions invoice :number waiting to be paid is voided too.',
        ],
    ],
    'family' => [
        'ending' => ':subject ends from :on: no sessions from that day.',
    ],
];
