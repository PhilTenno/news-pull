<?php

error_log('config.php aus news-pull geladen');

$GLOBALS['BE_MOD']['system']['news_pull'] = [
    'tables' => ['tl_newspull'],
    // Optional: eigenes Icon, falls vorhanden
    // 'icon'   => 'bundles/philtennonewspull/icon.svg',
];

$GLOBALS['TL_MODELS']['tl_newspull'] = \PhilTenno\NewsPull\Model\NewspullModel::class;