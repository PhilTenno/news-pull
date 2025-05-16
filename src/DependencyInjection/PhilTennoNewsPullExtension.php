<?php

namespace PhilTenno\NewsPull\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
class PhilTennoNewsPullExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        // Konfiguration wird automatisch via services.yaml geladen
    }
}