<?php


namespace Fagforbundet\NotificationApiMailerBundle\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface {

  /**
   * @inheritDoc
   */
  public function getConfigTreeBuilder() {
    $treeBuilder = new TreeBuilder('fagforbundet_notification_api_mailer');

    $treeBuilder->getRootNode()
      ->children()
        ->scalarNode('token_endpoint')->end()
        ->scalarNode('scope')->end()
      ->end();

    return $treeBuilder;
  }
}
