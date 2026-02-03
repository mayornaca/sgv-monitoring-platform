<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Entity\Device;
use App\Entity\Alert;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class MonitoringVoter extends Voter
{
    // Permisos para módulo COT
    public const COT_VIEW = 'COT_VIEW';
    public const COT_MANAGE = 'COT_MANAGE';
    
    // Permisos para módulo SCADA  
    public const SCADA_VIEW = 'SCADA_VIEW';
    public const SCADA_MANAGE = 'SCADA_MANAGE';
    
    // Permisos para incidentes
    public const INCIDENT_CREATE = 'INCIDENT_CREATE';
    public const INCIDENT_UPDATE = 'INCIDENT_UPDATE';
    public const INCIDENT_APPROVE = 'INCIDENT_APPROVE';
    
    // Permisos para reportes
    public const REPORTS_VIEW = 'REPORTS_VIEW';
    public const AUDIT_VIEW = 'AUDIT_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        $supportedAttributes = [
            self::COT_VIEW, self::COT_MANAGE,
            self::SCADA_VIEW, self::SCADA_MANAGE,
            self::INCIDENT_CREATE, self::INCIDENT_UPDATE, self::INCIDENT_APPROVE,
            self::REPORTS_VIEW, self::AUDIT_VIEW
        ];
        
        return in_array($attribute, $supportedAttributes);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::COT_VIEW => $this->canViewCot($user),
            self::COT_MANAGE => $this->canManageCot($user),
            self::SCADA_VIEW => $this->canViewScada($user),
            self::SCADA_MANAGE => $this->canManageScada($user),
            self::INCIDENT_CREATE => $this->canCreateIncident($user),
            self::INCIDENT_UPDATE => $this->canUpdateIncident($user),
            self::INCIDENT_APPROVE => $this->canApproveIncident($user),
            self::REPORTS_VIEW => $this->canViewReports($user),
            self::AUDIT_VIEW => $this->canViewAudit($user),
            default => false
        };
    }
    
    private function canViewCot(User $user): bool
    {
        $allowedRoles = ['ROLE_OPERATOR_COT', 'ROLE_ADMIN_COT', 'ROLE_SU_COT', 'ROLE_FISCAL_INSPECTOR'];
        return $this->hasAnyRole($user, $allowedRoles);
    }
    
    private function canManageCot(User $user): bool
    {
        $allowedRoles = ['ROLE_OPERATOR_COT', 'ROLE_ADMIN_COT', 'ROLE_SU_COT'];
        return $this->hasAnyRole($user, $allowedRoles);
    }
    
    private function canViewScada(User $user): bool
    {
        $allowedRoles = ['ROLE_OPERATOR_SCADA', 'ROLE_SU_COT'];
        return $this->hasAnyRole($user, $allowedRoles);
    }
    
    private function canManageScada(User $user): bool
    {
        $allowedRoles = ['ROLE_OPERATOR_SCADA'];
        return $this->hasAnyRole($user, $allowedRoles);
    }
    
    private function canCreateIncident(User $user): bool
    {
        $allowedRoles = ['ROLE_OPERATOR_COT', 'ROLE_OPERATOR_INCIDENTS'];
        return $this->hasAnyRole($user, $allowedRoles);
    }
    
    private function canUpdateIncident(User $user): bool
    {
        $allowedRoles = ['ROLE_OPERATOR_COT', 'ROLE_OPERATOR_INCIDENTS', 'ROLE_ADMIN_COT'];
        return $this->hasAnyRole($user, $allowedRoles);
    }
    
    private function canApproveIncident(User $user): bool
    {
        $allowedRoles = ['ROLE_SU_COT', 'ROLE_ADMIN_COT'];
        return $this->hasAnyRole($user, $allowedRoles);
    }
    
    private function canViewReports(User $user): bool
    {
        $allowedRoles = ['ROLE_ADMIN', 'ROLE_ADMIN_COT', 'ROLE_SU_COT', 'ROLE_FISCAL_INSPECTOR'];
        return $this->hasAnyRole($user, $allowedRoles);
    }
    
    private function canViewAudit(User $user): bool
    {
        $allowedRoles = ['ROLE_FISCAL_INSPECTOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'];
        return $this->hasAnyRole($user, $allowedRoles);
    }
    
    private function hasAnyRole(User $user, array $roles): bool
    {
        return !empty(array_intersect($user->getRoles(), $roles));
    }
}
