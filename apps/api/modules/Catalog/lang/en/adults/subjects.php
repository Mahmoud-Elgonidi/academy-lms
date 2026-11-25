<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../subjects.php.
return [
    'nav' => 'Areas',
    'model' => 'area',
    'subtitle' => 'Each area with its levels in order and its categories (tracks such as foundations and advanced practice, or professional certificates such as PMP and CMA). A trainer takes a range of levels and the categories they train in, and "Find a trainer" searches by them.',
    'create' => 'New area',
    'saved' => 'Area saved',
    'empty' => 'No areas yet',
    'empty_hint' => 'Add your first area, or import your trainers from a sheet and their areas are added for you.',
    'columns' => [
        'name' => 'Area',
        'teachers' => 'Trainers',
    ],
    'fields' => [
        'levels_hint' => 'From the easiest to the hardest; drag to reorder. A level a trainer trains in cannot be removed.',
        'categories_hint' => "The area's tracks or certificates, such as foundations, advanced practice, certificate exam preparation, PMP.",
    ],
    'errors' => [
        'name' => 'Give the area a name (up to 120 characters).',
        'taken' => 'There is already an area called ":name".',
        'levels_max' => 'An area has at most :max levels.',
        'categories_max' => 'An area has at most :max categories.',
        'levels_in_use' => 'Trainers train in the level ":name", so it cannot be removed. Rename it, or take it off those trainers first.',
        'categories_in_use' => 'Trainers are on the category ":name", so it cannot be removed. Rename it, or take it off those trainers first.',
    ],
];
