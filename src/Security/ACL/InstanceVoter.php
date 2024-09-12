<?php

namespace App\Security\ACL;

use App\Entity\User;
use App\Entity\InvitationCode;
use App\Entity\LabInstance;
use App\Entity\DeviceInstance;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class InstanceVoter extends Voter
{
    const VIEW = 'view';
    const STOP_DEVICE = 'stop_device';
    const START_DEVICE = 'start_device';
    const RESET_DEVICE = 'reset_device';
    const EXPORT_INSTANCE = 'export_instance';
    const GET_LOGS = 'get_logs';
    const DELETE = 'delete';

    protected function supports($attribute, $subject): bool
    {
        if (!$subject instanceof LabInstance && !$subject instanceof DeviceInstance) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User && !$user instanceof InvitationCode) {
            return false;
        }

        
        $instance = $subject;

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($instance, $user);
            case self::STOP_DEVICE:
                return $this->canStopDevice($instance, $user);
            case self::START_DEVICE:
                return $this->canStartDevice($instance, $user);
            case self::RESET_DEVICE:
                return $this->canResetDevice($instance, $user);
            case self::EXPORT_INSTANCE:
                return $this->canExportInstance($instance, $user);
            case self::GET_LOGS:
                return $this->canGetLogs($instance, $user);
            case self::DELETE:
                return $this->canDelete($instance, $user);
            default:
                return false;
        }
    }

    private function canView($instance, $user)
    {
        return $this->canHaveAccess($instance, $user);
    }

    private function canStopDevice($instance, $user)
    {
        if ($instance instanceof DeviceInstance) {
            if ($instance->getDevice()->getType() == "switch") {
                return false;
            }
            if ($instance->getOwnedBy() == "group" && !$instance->getOwner()->isElevatedUser($user)) {
                return false;
            }
        }
        return $this->canHaveAccess($instance, $user);
    }

    private function canStartDevice($instance, $user)
    {
        if ($instance instanceof DeviceInstance) {
            if ($instance->getDevice()->getType() == "switch") {
                return false;
            }
            if ($instance->getOwnedBy() == "group" && !$instance->getOwner()->isElevatedUser($user)) {
                return false;
            }
        }
        return $this->canHaveAccess($instance, $user);
    }

    private function canResetDevice($instance, $user)
    {
        $result=true;
        if (!$user instanceof User) {
            $result=false;
        }
        if ($instance->getOwnedBy() == "group" && !$instance->getOwner()->isElevatedUser($user)) {
            $result=false;
        }
        return $result;
        //return $this->canEdit($instance, $user);
    }

    private function canExportInstance($instance, $user)
    {
        if (!$user instanceof User) {
            return false;
        }
        return $this->canEdit($instance, $user);
    }

    private function canGetLogs($instance, $user)
    {
        return $this->canHaveAccess($instance, $user);
    }

    private function canDelete($instance, $user)
    {
        $isOwner;
        $isAdmin = false;
        $isAuthor = false;
        $isTeacher = false;
        $adminConnection = false;
        $isGroupAdmin = false;

        if ($user instanceof InvitationCode) {
            $isOwner = ($instance->getOwner() == $user);
        }
        else {
            if ($instance->getOwnedBy() == 'group'){
                $isOwner = $instance->getOwner()->isElevatedUser($user);
            }
            else {
                $isOwner = ($instance->getOwner() == $user);
            }
            if ($instance instanceof DeviceInstance) {
                $isAuthor = ($instance->getLabInstance()->getLab()->getAuthor() == $user);
                $lab = $instance->getLabInstance()->getLab();
            }
            else {
                $isAuthor = ($instance->getLab()->getAuthor() == $user);
                $lab = $instance->getLab();
            }
            
            foreach ($user->getGroups() as $groupUser) {
                $group = $groupUser->getGroup();
                foreach ($lab->getGroups() as $labGroup) {
                    if ($labGroup == $group) {
                        if ($group->isElevatedUser($user)) {
                            $isGroupAdmin = true;
                        }
                    }
                }
            }
            
            $isAdmin =  $user->isAdministrator();
        }
        return ($isAdmin || $isAuthor || $isOwner || $isGroupAdmin);
    }

    private function canHaveAccess($instance, $user)
    {

        $isOwner;
        $isAdmin = false;
        $isAuthor = false;
        $isTeacher = false;
        $adminConnection = false;
        $isGroupAdmin = false;

        if ($user instanceof InvitationCode) {
            $isOwner = ($instance->getOwner() == $user);
        }
        else {
            if ($instance->getOwnedBy() == 'group'){
                $isOwner = $user->isMemberOf($instance->getOwner());
            }
            else {
                $isOwner = ($instance->getOwner() == $user);
            }
            if ($instance instanceof DeviceInstance) {
                $isAuthor = ($instance->getLabInstance()->getLab()->getAuthor() == $user);
                $lab = $instance->getLabInstance()->getLab();
            }
            else {
                $isAuthor = ($instance->getLab()->getAuthor() == $user);
                $lab = $instance->getLab();
            }
            
            foreach ($user->getGroups() as $groupUser) {
                $group = $groupUser->getGroup();
                foreach ($lab->getGroups() as $labGroup) {
                    if ($labGroup == $group) {
                        if ($group->isElevatedUser($user)) {
                            $isGroupAdmin = true;
                        }
                    }
                }
            }
            
            $isAdmin =  $user->isAdministrator();
        }
        

        return ($isAdmin || $isAuthor || $isOwner || $isGroupAdmin);
        
    }

    private function canEdit($instance, User $user)
    {
        if ($instance instanceof DeviceInstance) {
            $lab = $instance->getLabInstance()->getLab();
        }
        else {
            $lab = $instance->getLab();
        }

        return ($user->isAdministrator() || $user == $lab->getAuthor());
    }
}
