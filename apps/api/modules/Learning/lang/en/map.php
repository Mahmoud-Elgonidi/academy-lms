<?php

/** The journey (docs/architecture/02, section 18): the curriculum's levels as islands, its lessons as stations, the missions and the stars. */
return [
    'chest' => [
        'opened' => 'This week\'s chest is already open.',
        'not_ready' => 'The chest opens once every lesson of the week is attended and every homework handed in.',
    ],
    'lesson' => [
        'on_time' => 'On time',
        'late' => '{1} One minute late|[2,*] :n minutes late',
        'stayed' => 'Stayed to the end',
        'missed' => 'Missed',
        'marked' => 'Attended',
    ],
    'notice' => [
        'on_time' => 'You came in on time',
        'on_time_stayed' => 'You came in on time and stayed to the end',
        'late' => 'You came in :n min late',
        'late_stayed' => 'You came in :n min late and stayed to the end',
        'marked' => 'You attended the lesson',
    ],
    'missions' => [
        'lesson' => 'Lesson',
        'homework' => 'Homework',
        'review' => 'Review',
    ],
    'styles' => [
        'playful' => 'Playful: each student in their age\'s look, with islands and games',
        'calm' => 'Calm: levels and missions, no islands',
        'plain' => 'Plain: levels and missions, no stars',
    ],
];
