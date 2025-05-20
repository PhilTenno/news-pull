<?php
//src/ContaoManager/Plugin.php
declare(strict_types=1);

namespace PhilTenno\NewsPull\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(\PhilTenno\NewsPull\PhilTennoNewsPullBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
}