<?php

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\Backend;

// Add fields to tl_module for our frontend module
$GLOBALS['TL_DCA']['tl_module']['fields']['newspull_max_results'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['newspull_max_results'],
    'inputType' => 'text',
    'default' => 5,
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "int(10) unsigned NOT NULL default 5"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['newspull_min_relevance'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['newspull_min_relevance'],
    'inputType' => 'text',
    'default' => 1,
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "int(10) unsigned NOT NULL default 1"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['newspull_cache_duration'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['newspull_cache_duration'],
    'inputType' => 'select',
    'default' => 3600,
    'options' => [
        300 => '5 Minuten',
        900 => '15 Minuten', 
        1800 => '30 Minuten',
        3600 => '1 Stunde',
        7200 => '2 Stunden',
        21600 => '6 Stunden',
        43200 => '12 Stunden',
        86400 => '24 Stunden'
    ],
    'eval' => ['tl_class' => 'w100 clr'],
    'sql' => "int(10) unsigned NOT NULL default 3600"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['customTpl'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['customTpl'],
    'inputType' => 'select',
    'options_callback' => static function () {
        return \Contao\Controller::getTemplateGroup('newspull_');
    },    
    'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50', 'chosen' => true],
    'sql' => "varchar(64) NOT NULL default ''"
];


// Feld: news_archives (Standardfeld aus News-Bundle)
$GLOBALS['TL_DCA']['tl_module']['fields']['news_archives'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['news_archives'],
    'inputType' => 'checkbox',
    'foreignKey' => 'tl_news_archive.title',
    'eval' => ['multiple' => true, 'tl_class' => 'w100'],
    'sql' => "blob NULL",
];

// Palette (komplett, wie gewünscht)
$GLOBALS['TL_DCA']['tl_module']['palettes']['newspull_related'] = 
    '{title_legend},name,headline,type;' .
    '{config_legend},newspull_max_results,newspull_min_relevance,newspull_cache_duration,news_archives;' .
    '{template_legend},customTpl;';