<?php

return [
    // The weekly note in the facts' own words (FactsText); {child} is the child's first name as it goes out.
    'facts' => [
        'intro' => "{child}'s week at the academy:",
        'lessons' => 'Lessons: :done attended.',
        'lessons_missed' => 'Lessons: :done attended, :missed missed.',
        'mastered' => 'Lessons mastered: :lessons.',
        'worked_on' => 'Lessons this week: :lessons.',
        'review' => 'Lessons to review: :lessons.',
        'homework' => 'Homework: :parts.',
        'homework_given' => ':n set',
        'homework_handed_in' => ':n handed in',
        'homework_marked' => ':n marked',
        'homework_missed' => ':n missed',
        'practice' => 'Practice rounds: :rounds.',
        'stars' => 'Stars this week: :stars.',
        'note' => 'The teacher said: “:note”',
        'quoted' => '“:title”',
        'and' => ', ',
    ],
    // The page a guardian opens to hear the recording of the week (no sign-in; their own link).
    'voice' => [
        'title' => ':student recites',
        'lead' => "From this week's homework: :homework",
        'when' => 'Handed in :when',
        'private' => 'This link is yours alone and ends after 14 days. The recording plays here and is not downloaded.',
        'no_player' => 'This browser cannot play the recording.',
    ],
    // Settings › Weekly note (owner and admin).
    'settings' => [
        'nav' => 'Weekly note',
        'title' => 'The weekly note to guardians',
        'subtitle' => "One message a week to each student's guardians about their child's week.",
        'toggle' => 'Send the weekly note',
        'when' => "It goes out on Friday after 7 pm on the student's clock, for every student with a course running or paused, to the guardians who receive reports (push, email and WhatsApp as each chose). A week with no lesson, homework or practice sends nothing.",
        'what' => "It holds: lessons attended and missed, the lessons and how they went, the teacher's words for the family, the academy's questions families see, homework, practice and the week's stars (unless the journey is plain). When the week has a good recitation recording, it carries a link for the guardian to hear it.",
        'never' => "It never says: the teacher's internal notes, the academy's review, ratings, disputes, or teachers' numbers and pay.",
        'wording_on' => 'Wording: the AI helper writes it from these facts alone; anything it writes beyond them sends the facts as they are.',
        'wording_off' => 'Wording: the facts as they are. The AI helper can word it more warmly: Settings › AI helpers.',
        'last_week' => '{0} No notes went out in the last 7 days.|{1} One note went out in the last 7 days.|[2,*] :count notes went out in the last 7 days.',
        'saved' => 'Saved.',
    ],
];
