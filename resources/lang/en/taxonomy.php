<?php

return [
    'navigation_group' => 'Taxonomy',

    'fields' => [
        'name' => 'Name',
        'slug' => 'Slug',
        'description' => 'Description',
        'parent' => 'Parent',
        'usage' => 'Used',
    ],

    'validation' => [
        'existing_only' => 'You can only choose existing :vocabulary.',
        'wrong_vocabulary' => 'Choose :vocabulary only.',
    ],

    'tree' => [
        'empty' => 'No terms yet.',
        'move' => 'Drag to move',
        'move_refused' => "That move isn't allowed",
    ],
];
