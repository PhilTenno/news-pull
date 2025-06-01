<?php

$GLOBALS['BE_MOD']['system']['news_pull'] = [
    'tables' => ['tl_newspull']
];

$GLOBALS['TL_MODELS']['tl_newspull'] = \PhilTenno\NewsPull\Model\NewspullModel::class;

// Keywords-Tabelle zum bestehenden Backend-Modul hinzufügen
$GLOBALS['BE_MOD']['system']['news_pull']['tables'][] = 'tl_newspull_keywords';

// Models registrieren
$GLOBALS['TL_MODELS']['tl_newspull_keywords'] = \PhilTenno\NewsPull\Model\NewspullKeywordsModel::class;