<?php

namespace App\EventSubscriber;

use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OAuth2ConsentSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'league.oauth2_server.event.authorization_request_resolve' => 'onAuthorizationRequest',
        ];
    }

    public function onAuthorizationRequest(AuthorizationRequestResolveEvent $event): void
    {
        // Auto-approve all authorization requests for authenticated users
        // This is appropriate for internal SSO where users are already authenticated
        $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_APPROVED);
    }
}
