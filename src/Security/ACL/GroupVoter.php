<?php

namespace App\Security\ACL;

use App\Entity\User;
use App\Entity\Group;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class GroupVoter extends Voter
{
    const VIEW = 'view';
    const EDIT = 'edit';
    const DELETE = 'delete';
    const ADD_MEMBER = 'add_member';
    const CREATE_SUBGROUP = 'create_subgroup';

    protected function supports($attribute, $subject): bool
    {
        // only vote on Group objects inside this voter
        if (!$subject instanceof Group) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            // the user must be logged in; if not, deny access
            return false;
        }

        // you know $subject is a Group object, thanks to supports
        /** @var Group $group */
        $group = $subject;

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($group, $user);
            case self::ADD_MEMBER:
                return $this->canAddMember($group, $user);
            case self::EDIT:
                return $this->canEdit($group, $user);
            case self::CREATE_SUBGROUP:
                return $this->canCreateSubgroup($group, $user);
            case self::DELETE:
                return $this->canDelete($group, $user);
            default:
                return false;
        }
    }

    private function canView(Group $group, User $user)
    {
        return $user->isAdministrator() ||
            $group->isPublic() ||
            ($group->isInternal() && $user->isMemberOf($group)) ||
            ($group->isPrivate() && $group->isElevatedUser($user))
        ;
    }

    private function canAddMember(Group $group, User $user)
    {
        // user is the group owner or an admin
        return 
            ($user->isMemberOf($group) && 
            ($user === $group->getOwner() || in_array($group->getUserRole($user), [Group::ROLE_ADMIN, Group::ROLE_OWNER]))
            ) || $user->isAdministrator();

    }

    private function canEdit(Group $group, User $user)
    {
        // user is the group owner or an admin
        return ($user->isAdministrator()) || ($user->isMemberOf($group) && ($user === $group->getOwner() || in_array($group->getUserRole($user), [Group::ROLE_ADMIN, Group::ROLE_OWNER])));
    }

    private function canCreateSubgroup(Group $group, User $user)
    {
        // user is the group owner or an admin
        return ($user->isAdministrator()) || ($user->isMemberOf($group) && ($group->isOwner($user) || $group->isAdmin($user)));
    }

    private function canDelete(Group $group, User $user)
    {
        // user is the group owner or an admin
        return ($user->isAdministrator()) || ($user->isMemberOf($group) && $group->isOwner($user));
    }
}
