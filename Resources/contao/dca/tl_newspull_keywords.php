<?php
//NEWS-PULL -> Resources/contao/dca/tl_newspull_keywords.php

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_newspull_keywords'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid' => 'index',
                'keywords' => 'index'
            ]
        ]
    ],
    'list' => [
        'sorting' => [
            'mode' => 4,
            'fields' => ['pid'],
            'headerFields' => ['title'],
            'panelLayout' => 'filter;search,limit',
            'child_record_callback' => ['tl_newspull_keywords', 'listKeywords']
        ],
        'operations' => [
            'edit' => [
                'label' => &$GLOBALS['TL_LANG']['tl_newspull_keywords']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.svg'
            ],
            'delete' => [
                'label' => &$GLOBALS['TL_LANG']['tl_newspull_keywords']['delete'],
                'href'  => 'act=delete',
                'icon'  => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;event.stopPropagation();"'
            ]
        ]
    ],
    'palettes' => [
        'default' => '{keywords_legend},keywords'
    ],
    'fields' => [
        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ],
        'pid' => [
            'foreignKey' => 'tl_news.headline',
            'sql' => "int(10) unsigned NOT NULL default 0",
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy']
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],
        'keywords' => [
            'label' => &$GLOBALS['TL_LANG']['tl_newspull_keywords']['keywords'],
            'inputType' => 'textarea',
            'eval' => [
                'mandatory' => true,
                'maxlength' => 500,
                'rows' => 3,
                'tl_class' => 'clr',
                'helpwizard' => true
            ],
            'explanation' => 'keywords_help',
            'sql' => "varchar(500) NOT NULL default ''"
        ]
    ]
];

class tl_newspull_keywords
{
    public function listKeywords($arrRow)
    {
        return '<div class="tl_content_left">' . 
               '<strong>Keywords:</strong> ' . 
               htmlspecialchars($arrRow['keywords']) . 
               '</div>';
    }
}