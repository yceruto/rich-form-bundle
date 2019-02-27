<?php

namespace Yceruto\Bundle\RichFormBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('rich_form');
        $rootNode = $this->getRootNode($treeBuilder, 'rich_form');

        $rootNode
            ->children()
                ->arrayNode('entity2')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_results')
                            ->defaultValue(10)
                            ->info('Maximum of results per request.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        $rootNode
            ->children()
                ->arrayNode('select2')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('If false, the Select2 support will be disabled.')
                        ->end()

                        ->scalarNode('theme')
                            ->defaultValue('default')
                            ->info('The Select2 theme.')
                        ->end()

                        ->integerNode('minimum_input_length')
                            ->defaultValue(0)
                            ->info('Minimum number of characters required to start a search.')
                        ->end()

                        ->integerNode('minimum_results_for_search')
                            ->defaultValue(10)
                            ->info('The minimum number of results required to display the search box.')
                        ->end()

                        ->integerNode('ajax_delay')
                            ->defaultValue(250)
                            ->info('How long to wait after an user has stopped typing before sending the request.')
                        ->end()

                        ->booleanNode('ajax_cache')
                            ->defaultTrue()
                            ->info('If false, it will force requested pages not to be cached by the browser.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    private function getRootNode(TreeBuilder $treeBuilder, $name)
    {
        // BC layer for symfony/config 4.1 and older
        if (!\method_exists($treeBuilder, 'getRootNode')) {
            return $treeBuilder->root($name);
        }

        return $treeBuilder->getRootNode();
    }
}
