<?php

declare(strict_types=1);

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use PhilTenno\NewsPull\PhilTennoNewsPullBundle;  // <-- HIER geändert!

return static function (ParserInterface $parser) {
    $bundles = [
        BundleConfig::create(PhilTennoNewsPullBundle::class)  // <-- HIER geändert!
            ->setLoadAfter([ContaoCoreBundle::class]),
    ];

    // Backend-Modul registrieren
    $GLOBALS['BE_MOD']['content']['newspull_settings'] = [
        'tables' => ['tl_newspull_settings'],
        'label'  => 'NewsPull Einstellungen',
        'icon'   => 'bundles/philtennonewspull/icons/settings.svg',
    ];

    return $bundles;
};