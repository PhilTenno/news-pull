<?php

namespace PhilTenno\NewsPull\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\CoreBundle\ContaoCoreBundle;
use PhilTenno\NewsPull\NewsPullBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(NewsPullBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
}