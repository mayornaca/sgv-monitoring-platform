<?php

namespace App\Notifier\WhatsApp;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * Options for Meta WhatsApp Business API messages
 * 
 * Allows configuration of template messages and their parameters
 */
final class MetaWhatsAppOptions implements MessageOptionsInterface
{
    private ?string $recipientId = null;
    private ?string $template = null;
    private array $templateParameters = [];
    private string $languageCode = 'es';
    
    /**
     * Set the recipient phone number (with country code)
     * Example: +56972126016
     */
    public function recipientId(string $recipientId): self
    {
        $this->recipientId = $recipientId;
        return $this;
    }
    
    public function getRecipientId(): ?string
    {
        return $this->recipientId;
    }
    
    /**
     * Set the template name to use
     * Example: 'prometheus_alert_firing' or 'alarma_vehiculo'
     */
    public function template(string $template): self
    {
        $this->template = $template;
        return $this;
    }
    
    public function getTemplate(): ?string
    {
        return $this->template;
    }
    
    /**
     * Set the template parameters
     * Each parameter will be sent as a text parameter in the template body
     * 
     * @param array $parameters Array of string parameters
     */
    public function templateParameters(array $parameters): self
    {
        $this->templateParameters = $parameters;
        return $this;
    }
    
    /**
     * Add a single parameter to the template
     */
    public function addTemplateParameter(string $parameter): self
    {
        $this->templateParameters[] = $parameter;
        return $this;
    }
    
    public function getTemplateParameters(): array
    {
        return $this->templateParameters;
    }
    
    /**
     * Set the language code for the template
     * Default: 'es' (Spanish)
     */
    public function languageCode(string $code): self
    {
        $this->languageCode = $code;
        return $this;
    }
    
    public function getLanguageCode(): string
    {
        return $this->languageCode;
    }
    
    public function toArray(): array
    {
        return [
            'recipient_id' => $this->recipientId,
            'template' => $this->template,
            'template_parameters' => $this->templateParameters,
            'language_code' => $this->languageCode,
        ];
    }
    
    /**
     * Create options for a Prometheus alert
     */
    public static function prometheusAlert(
        string $recipientId,
        string $alertName,
        string $severity,
        string $summary,
        string $instance
    ): self {
        return (new self())
            ->recipientId($recipientId)
            ->template('prometheus_alert_firing')
            ->templateParameters([
                $alertName,
                $severity,
                $summary,
                $instance
            ]);
    }
    
    /**
     * Create options for a vehicle/spire alert
     */
    public static function vehicleAlert(
        string $recipientId,
        string $period,
        string $device1,
        string $device2 = '-',
        string $device3 = '-'
    ): self {
        return (new self())
            ->recipientId($recipientId)
            ->template('alarma_vehiculo')
            ->templateParameters([
                $period,
                $device1,
                $device2,
                $device3
            ]);
    }
}