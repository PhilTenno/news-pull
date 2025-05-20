<?php

namespace PhilTenno\NewsPull\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class PhilTennoNewsPullExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new \Symfony\Component\DependencyInjection\Loader\YamlFileLoader(
            $container,
            new \Symfony\Component\Config\FileLocator(__DIR__ . '/../../config')
        );
        $loader->load('services.yaml');
    }
}
