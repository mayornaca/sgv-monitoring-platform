<?php

namespace App\EventSubscriber;

use App\Service\AuditLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class AuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AuditLogger $auditLogger
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $userId = method_exists($user, 'getId') ? $user->getId() : null;
        
        $this->auditLogger->logLogin($userId, true);
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $identifier = $event->getPassport()?->getUser()?->getUserIdentifier() ?? 'unknown';
        
        $this->auditLogger->log(
            'USER_LOGIN_FAILED',
            'Authentication',
            null,
            null,
            null,
            "Failed login attempt for: {$identifier}",
            'WARNING',
            'AUTH'
        );
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        $userId = (method_exists($user, 'getId')) ? $user->getId() : null;
        
        $this->auditLogger->logLogout($userId);
    }
}