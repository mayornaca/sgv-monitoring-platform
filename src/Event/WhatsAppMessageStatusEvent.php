<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class WhatsAppMessageStatusEvent extends Event
{
    public const NAME = 'whatsapp.message.status';
    
    private string $messageId;
    private string $status;
    private string $recipientId;
    private int $timestamp;
    private array $errors;
    
    public function __construct(
        string $messageId,
        string $status,
        string $recipientId,
        int $timestamp,
        array $errors = []
    ) {
        $this->messageId = $messageId;
        $this->status = $status;
        $this->recipientId = $recipientId;
        $this->timestamp = $timestamp;
        $this->errors = $errors;
    }
    
    public function getMessageId(): string
    {
        return $this->messageId;
    }
    
    public function getStatus(): string
    {
        return $this->status;
    }
    
    public function getRecipientId(): string
    {
        return $this->recipientId;
    }
    
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
    
    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }
    
    public function isRead(): bool
    {
        return $this->status === 'read';
    }
}