<?php

// contao/config/config.php

$GLOBALS['BE_MOD']['system']['news_pull'] = [
    'tables' => ['tl_newspull'],
    // Optional: eigenes Icon, falls vorhanden
    // 'icon'   => 'bundles/philtennonewspull/icon.svg',
];

$GLOBALS['TL_MODELS']['tl_newspull'] = \PhilTenno\NewsPull\Model\NewspullModel::class;