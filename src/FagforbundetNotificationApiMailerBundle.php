<?php

namespace Fagforbundet\NotificationApiMailerBundle;

use Fagforbundet\NotificationApiMailerBundle\Transport\NotificationApiTransportFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class FagforbundetNotificationApiMailerBundle extends AbstractBundle {

  public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void {
    $alias = $this->getContainerExtension()->getAlias();

    $container->services()
      ->set($alias . '.transport_factory', NotificationApiTransportFactory::class)
        ->args([
          service('fagforbundet_notification_api_client.client'),
          service('event_dispatcher'),
          service('http_client')->ignoreOnInvalid(),
          service('logger')->ignoreOnInvalid(),
        ])
        ->tag('monolog.logger', ['channel' => 'mailer'])
        ->tag('mailer.transport_factory')
    ;
  }

}
