<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\DependencyInjection\Compiler;

use InspiredMinds\ContaoFileUsage\Provider\AbstractDatabaseProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class IgnoreTablesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds('contao_file_usage.provider')) as $id) {
            $definition = $container->getDefinition($id);

            if (is_a($definition->getClass(), AbstractDatabaseProvider::class, true)) {
                $definition->addMethodCall('setIgnoreTables', [$container->getParameter('contao_file_usage.ignore_tables')]);
            }
        }
    }
}
