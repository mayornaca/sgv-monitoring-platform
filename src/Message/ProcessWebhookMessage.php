<?php

namespace App\Message;

/**
 * Message for async webhook processing via Symfony Messenger
 */
class ProcessWebhookMessage
{
    public function __construct(
        private int $webhookLogId,
        private string $source
    ) {
    }

    public function getWebhookLogId(): int
    {
        return $this->webhookLogId;
    }

    public function getSource(): string
    {
        return $this->source;
    }
}
