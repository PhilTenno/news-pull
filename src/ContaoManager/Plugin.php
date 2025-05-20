<?php

namespace PhilTenno\NewsPull\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use PhilTenno\NewsPull\PhilTennoNewsPullBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(PhilTennoNewsPullBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
}
