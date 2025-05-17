<?php

$GLOBALS['TL_DCA']['tl_newspull_settings'] = [

    // Konfiguration
    'config' => [
        'dataContainer'               => 'Table',
        'ctable'                      => [],
        'switchToEdit'                => true,
        'enableVersioning'            => true,
        'onload_callback'             => [],
        'onsubmit_callback'           => [],
        'ondelete_callback'           => [],
        'sql' => [
            'keys' => [
                'id' => 'primary'
            ]
        ]
    ],

    // Listen
    'list' => [
        'sorting' => [
            'mode'        => 1,
            'fields'      => ['tstamp'],
            'flag'        => 8,
            'panelLayout' => 'filter;search,limit'
        ],
        'label' => [
            'fields'      => ['news_pull_upload_dir', 'news_pull_image_dir'],
            'format'      => '%s / %s',
        ],
        'global_operations' => [
            'all' => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();"'
            ]
        ],
        'operations' => [
            'edit' => [
                'label' => &$GLOBALS['TL_LANG']['tl_newspull_settings']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif'
            ],
            'copy' => [
                'label' => &$GLOBALS['TL_LANG']['tl_newspull_settings']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.gif'
            ],
            'delete' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_newspull_settings']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"'
            ],
            'show' => [
                'label' => &$GLOBALS['TL_LANG']['tl_newspull_settings']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.gif'
            ]
        ]
    ],

    // Palettes
    'palettes' => [
        'default' => '{title_legend},news_pull_upload_dir,news_pull_image_dir,news_pull_news_archive,news_pull_auto_publish'
    ],

    // Fields
    'fields' => [
        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ],        
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ],
        'news_pull_upload_dir' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_newspull_settings']['news_pull_upload_dir'],
            'inputType' => 'fileTree',
            'eval'      => ['fieldType' => 'radio', 'filesOnly' => false, 'mandatory' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ],
        'news_pull_image_dir' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_newspull_settings']['news_pull_image_dir'],
            'inputType' => 'fileTree',
            'eval'      => ['fieldType' => 'radio', 'filesOnly' => false, 'mandatory' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ],
        'news_pull_news_archive' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_newspull_settings']['news_pull_news_archive'],
            'inputType' => 'select',
            'foreignKey'=> 'tl_news_archive.title',
            'eval'      => ['mandatory'=>true, 'chosen'=>true, 'tl_class'=>'w50'],
            'sql'       => "int(10) unsigned NOT NULL default '0'"
        ],
        'news_pull_auto_publish' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_newspull_settings']['news_pull_auto_publish'],
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''"
        ],
    ]
];