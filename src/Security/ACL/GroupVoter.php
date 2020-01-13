<?php

namespace App\Security\ACL;

use App\Entity\User;
use App\Entity\Group;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class GroupVoter extends Voter
{
    // these strings are just invented: you can use anything
    const ADD_MEMBER = 'add_member';
    const EDIT = 'edit';

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, [self::ADD_MEMBER, self::EDIT])) {
            return false;
        }

        // only vote on Group objects inside this voter
        if (!$subject instanceof Group) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
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
            case self::ADD_MEMBER:
                return $this->canAddMember($group, $user);
            case self::EDIT:
                return $this->canEdit($group, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canAddMember(Group $group, User $user)
    {
        // user is the group owner or an admin
        return $user->isMemberOf($group) && ($user === $group->getOwner() || in_array($group->getUserRole($user), [Group::ROLE_ADMIN, Group::ROLE_OWNER]));
    }

    private function canEdit(Group $group, User $user)
    {
        // user is the group owner or an admin
        return $user->isMemberOf($group) && ($user === $group->getOwner() || in_array($group->getUserRole($user), [Group::ROLE_ADMIN, Group::ROLE_OWNER]));
    }
}