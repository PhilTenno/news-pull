<?php

declare(strict_types=1);

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use PhilTenno\NewsPull\PhilTennoNewsPullBundle;

return static function (ParserInterface $parser) {
    return [
        BundleConfig::create(PhilTennoNewsPullBundle::class)
            ->setLoadAfter([ContaoCoreBundle::class]),
    ];
};