<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class WhatsAppMessageReceivedEvent extends Event
{
    public const NAME = 'whatsapp.message.received';
    
    private string $messageId;
    private string $from;
    private string $type;
    private string $content;
    private int $timestamp;
    private array $rawMessage;
    
    public function __construct(
        string $messageId,
        string $from,
        string $type,
        string $content,
        int $timestamp,
        array $rawMessage
    ) {
        $this->messageId = $messageId;
        $this->from = $from;
        $this->type = $type;
        $this->content = $content;
        $this->timestamp = $timestamp;
        $this->rawMessage = $rawMessage;
    }
    
    public function getMessageId(): string
    {
        return $this->messageId;
    }
    
    public function getFrom(): string
    {
        return $this->from;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function getContent(): string
    {
        return $this->content;
    }
    
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }
    
    public function getRawMessage(): array
    {
        return $this->rawMessage;
    }
}