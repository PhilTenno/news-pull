<?php

error_log('DCA tl_settings.php wird geladen: ' . date('c'));
file_put_contents(__DIR__ . '/../../../../news_pull_debug.txt', 'tl_settings.php geladen: ' . date('c') . "\n", FILE_APPEND);

$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] = str_replace(
    '{chmod_legend}',
    '{news_pull_legend},news_pull_upload_dir,news_pull_image_dir,news_pull_auto_publish;{chmod_legend}',
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

$GLOBALS['TL_DCA']['tl_settings']['fields']['news_pull_auto_publish'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['news_pull_auto_publish'],
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "char(1) NOT NULL default ''"
];