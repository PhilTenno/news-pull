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
        'default' => '{settings_legend},title,token,image_dir,image_size,news_archive,author,batch_size,max_payload_size_kb,auto_publish,teaser_image,teaser_news,no_htmltags,linktarget'
    ],
    'fields' => [
        'title' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['title'],
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 128, 'tl_class' => 'w50'],
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
        'news_archive' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['news_archive'],
            'inputType' => 'select',
            'foreignKey' => 'tl_news_archive.title',
            'eval' => ['mandatory' => true, 'tl_class' => 'clr w50'],
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],         
        'image_size' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['image_size'],
            'inputType' => 'select',
            'foreignKey' => 'tl_image_size.name',
            'eval' => ['mandatory' => true, 'tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],               
        'image_dir' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['image_dir'],
            'inputType' => 'fileTree',
            'eval' => [
                'mandatory' => true,
                'fieldType' => 'radio', // Einzelauswahl
                'files' => false,       // Ordnerauswahl
                'tl_class' => 'w50'
            ],
            'sql' => "binary(16) NULL"
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
        'max_payload_size_kb' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['max_payload_size_kb'],
            'default' => 256, // z. B. 256 KB
            'inputType' => 'text',
            'eval' => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 256",
        ],        
        'token' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['token'],
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64, 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''"
        ], 
        'auto_publish' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['auto_publish'],
            'inputType' => 'checkbox',
            'eval' => [
                'isBoolean' => true,
                'tl_class' => 'clr w50'
            ],
            'sql' => "char(1) NOT NULL default ''"
        ],
        'teaser_image' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['teaser_image'],
            'inputType' => 'checkbox',
            'eval' => ['isBoolean' => true,'tl_class' => 'w50'],
            'sql' => "char(1) NOT NULL default ''"
        ],        
        'teaser_news' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['teaser_news'],
            'inputType' => 'checkbox',
            'eval' => ['isBoolean' => true,'tl_class' => 'w50'],
            'sql' => "char(1) NOT NULL default ''"
        ],
        'no_htmltags' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['no_htmltags'],
            'inputType' => 'checkbox',
            'eval' => ['isBoolean' => true,'tl_class' => 'w50'],
            'sql' => "char(1) NOT NULL default ''"
        ],
        'linktarget' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull']['linktarget'],
            'inputType' => 'checkbox',
            'eval' => ['isBoolean' => true,'tl_class' => 'w50'],
            'sql' => "char(1) NOT NULL default ''"
        ]                                                       
    ]
];
