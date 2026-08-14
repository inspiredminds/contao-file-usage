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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('contao_file_usage');
        $treeBuilder
            ->getRootNode()
                ->children()
                ->arrayNode('ignore_tables')
                    ->info('These tables are ignored by the database providers.')
                    ->defaultValue(['tl_version', 'tl_log', 'tl_undo', 'tl_search_index', 'tl_message_queue'])
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('filesystem')
                    ->info('Scans files within the file system for file references.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('paths')
                            ->info('The paths to be scanned, either absolute or relative to the project dir.')
                            ->defaultValue(['%contao.upload_path%', 'templates', 'src'])
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('include_patterns')
                            ->info('Regular expressions the path of a file has to match in order to be scanned.')
                            ->defaultValue(['~\.(twig|html5|css|scss|js|php)$~i'])
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('exclude_patterns')
                            ->info('Regular expressions excluding a file from being scanned.')
                            ->defaultValue(['~/(node_modules|vendor)/~'])
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
