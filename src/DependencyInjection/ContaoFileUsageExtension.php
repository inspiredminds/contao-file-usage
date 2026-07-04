<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\DependencyInjection;

use InspiredMinds\ContaoFileUsage\Provider\FileUsageProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ContaoFileUsageExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        (new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config')))->load('services.yaml');

        $container->registerForAutoconfiguration(FileUsageProviderInterface::class)
            ->addTag('contao_file_usage.provider')
        ;

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('contao_file_usage.ignore_tables', $config['ignore_tables']);

        $filesystem = $config['filesystem'];
        $container->setParameter('contao_file_usage.filesystem.include_folders', $filesystem['include_folders']);
        $container->setParameter('contao_file_usage.filesystem.exclude_folders', $filesystem['exclude_folders']);
        $container->setParameter('contao_file_usage.filesystem.include_extensions', $filesystem['include_extensions']);
        $container->setParameter('contao_file_usage.filesystem.exclude_extensions', $filesystem['exclude_extensions']);
    }
}
