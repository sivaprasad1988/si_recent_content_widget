<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Recent Content Widget',
    'description' => 'A widget for the dashboard to display new and recently updated content elements.',
    'category' => 'be',
    'author' => 'Siva (maintainer), Epixskill (original author)',
    'author_email' => 'sivaprasad.s88@gmail.com',
    'state' => 'stable',
    'clearCacheOnLoad' => 0,
    'version' => '2.1.1',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
            'backend' => '13.4.0-14.99.99',
            'dashboard' => '13.4.0-14.99.99',
            'fluid' => '13.4.0-14.99.99'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
