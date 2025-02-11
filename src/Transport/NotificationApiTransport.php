<?php

namespace Fagforbundet\NotificationApiMailerBundle\Transport;

use Fagforbundet\NotificationApiClientBundle\Client\NotificationApiClientInterface;
use Fagforbundet\NotificationApiClientBundle\Exception\HttpSendMessageException;
use Fagforbundet\NotificationApiClientBundle\Exception\SendMessageException;
use Fagforbundet\NotificationApiClientBundle\Notification\Email\EmailAttachment;
use Fagforbundet\NotificationApiClientBundle\Notification\Email\EmailMessage;
use Fagforbundet\NotificationApiClientBundle\Notification\Email\EmailRecipient;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

final class NotificationApiTransport extends AbstractTransport {
  public const HEADER_FORCE_USE_RECIPIENTS = 'X-Force-Use-Recipients';
  public const HEADER_DEV_RECIPIENT_OVERRIDE = 'X-Dev-Recipient-Override';

  /**
   * NotificationApiTransport constructor.
   */
  public function __construct(
    private readonly NotificationApiClientInterface $notificationApiClient,
    private readonly array $devRecipientOverrides = [],
    private readonly bool $forceUseRecipients = false,
    EventDispatcherInterface $dispatcher = null,
    LoggerInterface $logger = null
  ) {
    parent::__construct($dispatcher, $logger);
  }

  protected function doSend(SentMessage $message): void {
    $originalMessage = $message->getOriginalMessage();
    if (!$originalMessage instanceof Message) {
      throw new TransportException(\sprintf("can't send a message that is not instance of %s", Message::class));
    }

    $email = MessageConverter::toEmail($originalMessage);
    $envelope = $message->getEnvelope();

    try {
      $sentEmailMessage = $this->notificationApiClient->sendEmailMessage(
        $this->createNotificationApiEmailMessage($email, $envelope),
        $this->isForceUseRecipients($email->getHeaders()),
        \array_map($this->createEmailRecipient(...), $this->getDevRecipientOverrides($email->getHeaders()))
      );
    } catch (HttpSendMessageException $e) {
      $message->appendDebug($e->getResponse()->getInfo('debug') ?? '');
      throw new HttpTransportException($e->getMessage(), $e->getResponse(), previous: $e);
    } catch (SendMessageException $e) {
      throw new TransportException($e->getMessage(), previous: $e);
    }

    $message->setMessageId((string) $sentEmailMessage->getUuid());
  }

  public function __toString(): string {
    return 'notification-api+api://default';
  }

  /**
   * @param Email    $email
   * @param Envelope $envelope
   *
   * @return EmailMessage
   */
  private function createNotificationApiEmailMessage(Email $email, Envelope $envelope): EmailMessage {
    $emailMessage = new EmailMessage($email->getSubject());

    if ($text = $email->getTextBody()) {
      $emailMessage->setText($text);
    }

    if ($html = $email->getHtmlBody()) {
      $emailMessage->setHtml($html);
    }

    $emailMessage->addFrom(...array_map($this->createEmailRecipient(...), $email->getFrom()));
    $emailMessage->addReplyTo(...array_map($this->createEmailRecipient(...), $email->getReplyTo()));
    $emailMessage->addTo(...array_map($this->createEmailRecipient(...), $this->getToRecipients($email, $envelope)));
    $emailMessage->addCc(...array_map($this->createEmailRecipient(...), $this->getCcRecipients($email, $envelope)));
    $emailMessage->addBcc(...array_map($this->createEmailRecipient(...), $this->getBccRecipients($email, $envelope)));

    $emailMessage->addAttachment(...\array_map($this->createEmailAttachment(...), $email->getAttachments()));

    return $emailMessage;
  }

  /**
   * @param Address|string $address
   *
   * @return EmailRecipient
   */
  private function createEmailRecipient(Address|string $address): EmailRecipient {
    if (\is_string($address)) {
      $address = Address::create($address);
    }

    return (new EmailRecipient($address->getAddress()))
      ->setName($address->getName() ?: null);
  }

  /**
   * @param DataPart $dataPart
   *
   * @return EmailAttachment
   */
  private function createEmailAttachment(DataPart $dataPart): EmailAttachment {
    $attachment = new EmailAttachment($dataPart->getFilename(), $dataPart->bodyToString());

    if ($dataPart->getDisposition() === 'inline') {
      $attachment = $attachment->asInline();
    }

    return $attachment;
  }

  /**
   * @param Email    $email
   * @param Envelope $envelope
   *
   * @return Address[]
   */
  protected function getToRecipients(Email $email, Envelope $envelope): array {
    return array_filter($envelope->getRecipients(), fn (Address $address) => false === \in_array($address, array_merge($email->getCc(), $email->getBcc()), true));
  }

  /**
   * @param Email    $email
   * @param Envelope $envelope
   *
   * @return Address[]
   */
  private function getCcRecipients(Email $email, Envelope $envelope): array {
    return array_filter($envelope->getRecipients(), fn (Address $address) => \in_array($address, $email->getCc(), true));
  }

  /**
   * @param Email    $email
   * @param Envelope $envelope
   *
   * @return Address[]
   */
  private function getBccRecipients(Email $email, Envelope $envelope): array {
    return array_filter($envelope->getRecipients(), fn (Address $address) => \in_array($address, $email->getBcc(), true));
  }

  /**
   * @param Headers $headers
   *
   * @return bool
   */
  private function isForceUseRecipients(Headers $headers): bool {
    if ($headers->has(self::HEADER_FORCE_USE_RECIPIENTS)) {
      return 'true' === $headers->get(self::HEADER_FORCE_USE_RECIPIENTS);
    }

    return $this->forceUseRecipients;
  }

  /**
   * @param Headers $headers
   *
   * @return array
   */
  private function getDevRecipientOverrides(Headers $headers): array {
    if ($headers->has(self::HEADER_DEV_RECIPIENT_OVERRIDE)) {
      return \iterator_to_array($headers->all(self::HEADER_DEV_RECIPIENT_OVERRIDE));
    }

    return $this->devRecipientOverrides;
  }

}
