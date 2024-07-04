<?php

namespace App\Security\ACL;

use App\Entity\User;
use App\Entity\InvitationCode;
use App\Entity\Lab;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class LabVoter extends Voter
{
    const SEE_TEXTOBJECT = 'see_textobject';
    const EDIT_TEXTOBJECT = 'edit_textobject';
    const SEE_DEVICE = 'see_device';
    const EDIT_DEVICE = 'edit_device';
    const SEE_INTERFACE = 'see_interface';
    const EDIT_INTERFACE = 'edit_interface';
    const EDIT_CODE = 'edit_code';
    const EDIT = 'edit';
    const CREATE = 'create';
    const SEE = 'see';

    protected function supports($attribute, $subject): bool
    {
        if (!$subject instanceof Lab) {
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

        // you know $subject is a Lab object, thanks to supports
        /** @var Lab $group */
        $lab = $subject;

        switch ($attribute) {
            case self::SEE_TEXTOBJECT:
                return $this->canSeeTextObject($lab, $user);
            case self::EDIT_TEXTOBJECT:
                return $this->canEditTextObject($lab, $user);
            case self::SEE_DEVICE:
                return $this->canSeeDevice($lab, $user);
            case self::EDIT_DEVICE:
                return $this->canEditDevice($lab, $user);
            case self::SEE_INTERFACE:
                return $this->canSeeInterface($lab, $user);
            case self::EDIT_INTERFACE:
                return $this->canEditInterface($lab, $user);
            case self::EDIT_CODE:
                return $this->canEditCode($lab, $user);
            case self::CREATE:
                return $this->canCreate($lab, $user);
            case self::EDIT:
                return $this->canEdit($lab, $user);
            case self::SEE:
                return $this->canSee($lab, $user);
            default:
                return false;
        }
    }

    private function canSeeTextObject(Lab $lab, $user)
    {
        return $this->canHaveAccess($lab, $user);
    }

    private function canEditTextObject(Lab $lab, $user)
    {
        if (!$user instanceof User) {
            return false;
        }
        return $this->canHaveEditorRights($lab, $user);
    }

    private function canSeeDevice(Lab $lab, $user)
    {
        return $this->canHaveAccess($lab, $user);
    }

    private function canEditDevice(Lab $lab, $user)
    {
        if (!$user instanceof User) {
            return false;
        }
        return $this->canHaveEditorRights($lab, $user);
    }

    private function canSeeInterface(Lab $lab, $user)
    {
        return $this->canHaveAccess($lab, $user);
    }

    private function canEditInterface(Lab $lab, $user)
    {
        if (!$user instanceof User) {
            return false;
        }
        return $this->canHaveEditorRights($lab, $user);
    }

    private function canEditCode(Lab $lab, $user)
    {
        if (!$user instanceof User) {
            return false;
        }
        return $this->canHaveEditorRights($lab, $user);
    }

    private function canCreate(Lab $lab, $user)
    {
        if (!$user instanceof User) {
            return false;
        }
        return ($user->isAdministrator() || $user->getHighestRole() == "ROLE_TEACHER" || $user->getHighestRole() == "ROLE_TEACHER_EDITOR");
    }

    private function canEdit(Lab $lab, $user)
    {
        if (!$user instanceof User) {
            return false;
        }
        return $this->canHaveEditorRights($lab, $user);
    }

    
    private function canSee(Lab $lab, $user)
    {
        return $this->canHaveAccess($lab, $user);
    }

    private function canHaveAccess(Lab $lab, $user)
    {
        if($user instanceof User) {
            $isMember = false;
            $hasBooking = false;
            foreach($lab->getGroups() as $group) {
                if ($user->isMemberOf($group)) {
                    $isMember = true;
                }
            }
            foreach($lab->getBookings() as $booking) {
                if ($booking->isReservedForUser()) {
                    if ($booking->getOwner() == $user) {
                        $hasBooking = true;
                    }
                }
                else {
                    if ($user->isMemberOf($booking->getOwner())) {
                        $hasBooking = true;
                    }
                }
            }
            return ($user->isAdministrator() || $user == $lab->getAuthor() || $isMember || $hasBooking);
        }
        else {
            return ($user->getLab() == $lab);
        }
        
    }
    

    private function canHaveEditorRights(Lab $lab, User $user)
    {
        return ($user->isAdministrator() || $user == $lab->getAuthor());
    }
}
