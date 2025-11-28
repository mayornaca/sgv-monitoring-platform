<?php

namespace App\Notifier\WhatsApp;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Psr\Log\LoggerInterface;

/**
 * Factory for creating Meta WhatsApp Business API Transport instances
 * 
 * DSN format: meta-whatsapp://ACCESS_TOKEN@default?phone_number_id=PHONE_ID&template=TEMPLATE_NAME
 * 
 * Example:
 * meta-whatsapp://EAAR912L41Mo...@default?phone_number_id=651420641396348&template=prometheus_alert_firing
 */
final class MetaWhatsAppTransportFactory extends AbstractTransportFactory
{
    private ?LoggerInterface $logger = null;
    
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
    
    /**
     * @return MetaWhatsAppTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        
        if ('meta-whatsapp' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'meta-whatsapp', $this->getSupportedSchemes());
        }
        
        // El access token viene como el "user" del DSN
        $accessToken = $this->getUser($dsn);
        
        // El phone_number_id es obligatorio y viene como query parameter
        $phoneNumberId = $dsn->getOption('phone_number_id');
        if (!$phoneNumberId) {
            throw new \InvalidArgumentException('The "phone_number_id" option is required for Meta WhatsApp transport');
        }
        
        // Template es opcional
        $defaultTemplate = $dsn->getOption('template');
        
        // Host y port (normalmente no se usan, pero los respetamos si vienen)
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();
        
        $transport = new MetaWhatsAppTransport(
            $accessToken,
            $phoneNumberId,
            $defaultTemplate,
            $this->client,
            $this->dispatcher,
            $this->logger
        );
        
        if ($host && $port) {
            $transport->setHost($host)->setPort($port);
        }
        
        return $transport;
    }
    
    protected function getSupportedSchemes(): array
    {
        return ['meta-whatsapp'];
    }
}