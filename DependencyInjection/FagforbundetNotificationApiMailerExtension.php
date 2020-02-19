<?php


namespace Fagforbundet\NotificationApiMailerBundle\DependencyInjection;

use Fagforbundet\NotificationApiMailer\Factory\AccessTokenFactory;
use Fagforbundet\NotificationApiMailer\Interfaces\AccessTokenFactoryInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FagforbundetNotificationApiMailerExtension extends Extension {

  /**
   * @inheritDoc
   * @throws \Exception
   */
  public function load(array $configs, ContainerBuilder $container) {
    $configuration = new Configuration();
    $config = $this->processConfiguration($configuration, $configs);

    $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
    $loader->load('services.yaml');

    $accessTokenFactory = $container->getDefinition(AccessTokenFactoryInterface::class);
    if ($accessTokenFactory->getClass() === AccessTokenFactory::class) {
      $accessTokenFactory->setArguments([
        '$client' => new Reference(HttpClientInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
        '$tokenEndpoint' => $config['token_endpoint'] ?? null,
        '$scope' => $config['scope'] ?? null
      ]);
    }
  }
}
