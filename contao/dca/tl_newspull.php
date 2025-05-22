<?php

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_newspull'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'sorting' => 'index'
            ]
        ]
    ],
    'list' => [
        'sorting' => [
            'mode' => 2,
            'fields' => ['sorting'],
            'flag' => 1,
            'panelLayout' => 'filter;search,limit'
        ],
        'label' => [
            'fields' => ['title'],
            'format' => '%s'
        ],
        'operations' => [
            'edit' => [
                'label' => &$GLOBALS['TL_LANG']['tl_newspull']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.svg'
            ],
            'delete' => [
                'label' => &$GLOBALS['TL_LANG']['tl_newspull']['delete'],
                'href'  => 'act=delete',
                'icon'  => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;event.stopPropagation();"'
            ]            
        ]
    ],
    'palettes' => [
        '__selector__' => [],
        'default' => '{settings_legend},title,token,upload_dir,news_archive,author,auto_publish,batch_size,max_file_size'
    ],
    'fields' => [
        'title' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['title'],
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 128, 'tl_class' => 'w100'],
            'sql' => "varchar(128) NOT NULL default ''"
        ],        
        'id' => [
            'label' => ['ID', 'Primärschlüssel'],
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],
        'sorting' => [
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],        
        'upload_dir' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['upload_dir'],
            'inputType' => 'fileTree',
            'eval' => [
                'mandatory' => true,
                'fieldType' => 'radio', 
                'files' => false,       
                'tl_class' => 'w50'
            ],
            'sql' => "binary(16) NULL" 
        ],
        'news_archive' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['news_archive'],
            'inputType' => 'select',
            'foreignKey' => 'tl_news_archive.title',
            'eval' => ['mandatory' => true, 'tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],
        'author' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['author'],
            'inputType' => 'select',
            'foreignKey' => 'tl_user.name',
            'eval' => ['mandatory' => true, 'tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],
        'batch_size' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['batch_size'],
            'default' => 10,
            'inputType' => 'text',
            'eval' => ['rgxp'=>'digit', 'tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 10"
        ],
        'max_file_size' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['max_file_size'],
            'default' => 256,
            'inputType' => 'text',
            'eval' => ['rgxp'=>'digit', 'tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 256"
        ],
        'token' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['token'],
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''"
        ], 
        'auto_publish' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['auto_publish'],
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'clr'],
            'sql' => "char(1) NOT NULL default ''"
        ]               
    ]
];
