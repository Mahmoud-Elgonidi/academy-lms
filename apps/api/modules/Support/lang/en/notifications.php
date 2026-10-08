<?php

return [
    'audience' => [
        'concerned' => 'Who the kind concerns: complaints to who oversees the teachers, payments to who records them, the rest to everyone who answers tickets',
        'requester' => 'Who wrote the ticket',
        'assignee' => 'Who has the ticket (else who its kind concerns)',
        'teacher' => 'The teacher the complaint is about',
    ],
    'ticket_opened' => ['title' => 'New ticket :ticket: :subject', 'body' => ':requester (:kind): :excerpt'],
    'answered' => ['title' => 'The academy answered your ticket :ticket', 'body' => ':excerpt'],
    'requester_replied' => ['title' => ':requester answered on :ticket', 'body' => ':excerpt'],
    'assigned' => ['title' => 'Ticket :ticket is yours', 'body' => ':subject (:requester)'],
    'resolved' => ['title' => 'Your ticket :ticket is resolved', 'body' => '":subject". If it is not solved, reopen it within :days days.'],
    'overdue' => ['title' => 'Ticket :ticket is waiting too long for an answer', 'body' => ':subject (:requester): its first answer is past the academy\'s target.'],
    'complaint_validated' => ['title' => 'A complaint about your lesson was confirmed', 'body' => 'About your :subject lesson on :at. It counts in your numbers; talk to the academy if you want to go over it.'],
    'complaint_validated_general' => ['title' => 'A complaint about your work was confirmed', 'body' => 'It counts in your numbers; talk to the academy if you want to go over it.'],
];
