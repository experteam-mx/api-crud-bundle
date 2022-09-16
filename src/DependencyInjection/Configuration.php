<?php


namespace Experteam\ApiCrudBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('experteam_api_crud');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->arrayNode('logged_entities')
            ->arrayPrototype()
            ->children()
            ->scalarNode('class')->isRequired()->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}