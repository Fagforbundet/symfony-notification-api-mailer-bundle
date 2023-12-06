<?php

namespace Fagforbundet\NotificationApiMailerBundle\Transport;

use Fagforbundet\NotificationApiClientBundle\Client\NotificationApiClientInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NotificationApiTransportFactory extends AbstractTransportFactory {

  /**
   * NotificationApiTransportFactory constructor.
   */
  public function __construct(
    private readonly NotificationApiClientInterface $notificationApiClient,
    EventDispatcherInterface $dispatcher = null,
    HttpClientInterface $client = null,
    LoggerInterface $logger = null,
  ) {
    parent::__construct($dispatcher, $client, $logger);
  }

  protected function getSupportedSchemes(): array {
    return ['notification-api', 'notification-api+api'];
  }

  /**
   * @inheritDoc
   */
  public function create(Dsn $dsn): TransportInterface {
    if (!\in_array($dsn->getScheme(), $this->getSupportedSchemes(), true)) {
      throw new UnsupportedSchemeException($dsn, 'notification-api', $this->getSupportedSchemes());
    }

    $devRecipientOverridesOption = $dsn->getOption('devRecipientOverrides');
    $devRecipientOverrides = $devRecipientOverridesOption ? \explode(',', $devRecipientOverridesOption) : [];
    $forceUseRecipients = 'true' === $dsn->getOption('forceUseRecipients');
    if ($dsn->getHost() !== 'default') {
      throw new IncompleteDsnException('Host must be set to default. Use Notification API client configuration to set host');
    }

    return (new NotificationApiTransport($this->notificationApiClient, $devRecipientOverrides, $forceUseRecipients, $this->dispatcher, $this->logger));
  }

}
