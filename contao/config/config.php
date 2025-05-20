<?php

$GLOBALS['BE_MOD']['system']['news_pull'] = [
    'tables' => ['tl_newspull']
];

$GLOBALS['TL_MODELS']['tl_newspull'] = \PhilTenno\NewsPull\Model\NewspullModel::class;