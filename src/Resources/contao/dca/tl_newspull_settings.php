<?php

$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] = str_replace(
    '{chmod_legend}',
    '{news_pull_legend},news_pull_upload_dir,news_pull_image_dir,news_pull_news_archive,news_pull_auto_publish;{chmod_legend}',
    $GLOBALS['TL_DCA']['tl_settings']['palettes']['default']
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['news_pull_upload_dir'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['news_pull_upload_dir'],
    'inputType' => 'fileTree',
    'eval'      => ['fieldType' => 'radio', 'filesOnly' => false, 'mandatory' => true, 'tl_class' => 'w50'],
    'sql'       => "varchar(255) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['news_pull_image_dir'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['news_pull_image_dir'],
    'inputType' => 'fileTree',
    'eval'      => ['fieldType' => 'radio', 'filesOnly' => false, 'mandatory' => true, 'tl_class' => 'w50'],
    'sql'       => "varchar(255) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['news_pull_news_archive'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['news_pull_news_archive'],
    'inputType' => 'select',
    'foreignKey'=> 'tl_news_archive.title',
    'eval'      => ['mandatory'=>true, 'chosen'=>true, 'tl_class'=>'w50'],
    'sql'       => "int(10) unsigned NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['news_pull_auto_publish'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['news_pull_auto_publish'],
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "char(1) NOT NULL default ''"
];