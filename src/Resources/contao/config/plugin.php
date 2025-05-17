<?php

return [
    PhilTenno\NewsPull\PhilTennoNewsPullBundle::class => ['all' => true],
    // Backend-Modul hinzufügen
        'backend' => [
            'modules' => [
                'content' => [
                    'newspull_settings' => [
                        'tables' => ['tl_newspull_settings'],
                        'label' => 'NewsPull Einstellungen',  // Optional: Label für das Modul
                        'icon'  => 'bundles/philtennonewspull/icons/settings.svg',  // Optional: Icon für das Modul
                    ],
                ],
            ],
        ],    
];