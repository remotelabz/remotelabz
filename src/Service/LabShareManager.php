<?php

namespace App\Service;

use App\Entity\Group;
use App\Entity\Lab;
use App\Entity\LabShare;
use App\Repository\GroupRepository;
use App\Repository\LabRepository;
use App\Repository\LabShareRepository;
use Psr\Log\LoggerInterface;

/**
 * Applies the share rules of a lab (see shared-labs-front-plan.md §2.3):
 * a rule is the triple (source lab S, target lab L, group G). Only the target
 * lab L has to be available in G: S does not, which is what allows a lab to be
 * shared with a group while being hidden from its members (S is simply not
 * listed in G, but an instance of S can still belong to G).
 */
final class LabShareManager
{
    private LabRepository $labRepository;
    private GroupRepository $groupRepository;
    private LabShareRepository $labShareRepository;
    private LoggerInterface $logger;

    public function __construct(
        LabRepository $labRepository,
        GroupRepository $groupRepository,
        LabShareRepository $labShareRepository,
        LoggerInterface $logger
    ) {
        $this->labRepository = $labRepository;
        $this->groupRepository = $groupRepository;
        $this->labShareRepository = $labShareRepository;
        $this->logger = $logger;
    }

    /**
     * Atomically replace every share rule of a lab.
     *
     * The rules are only applied when all of them are valid: on any error the
     * collection of the lab is left untouched.
     *
     * @param array<int, mixed> $rules payload entries: ['sharedWith' => lab id, 'group' => group uuid]
     *
     * @return string[] the validation errors, empty when the rules are valid
     */
    public function replaceShares(Lab $lab, array $rules): array
    {
        $errors = [];
        $prepared = [];
        $seen = [];

        foreach (array_values($rules) as $index => $rule) {
            if (!is_array($rule)) {
                $errors[] = 'Share rule #'.($index + 1).' must be an object.';
                continue;
            }

            $sharedWithId = $rule['sharedWith'] ?? null;
            $groupUuid = $rule['group'] ?? null;

            if (!is_numeric($sharedWithId)) {
                $errors[] = 'Share rule #'.($index + 1).' requires a target lab.';
                continue;
            }

            $target = $this->labRepository->find((int) $sharedWithId);
            if (is_null($target)) {
                $errors[] = 'Share rule #'.($index + 1).': lab '.(int) $sharedWithId.' does not exist.';
                continue;
            }

            if ($target === $lab || (!is_null($lab->getId()) && $target->getId() === $lab->getId())) {
                $errors[] = 'A lab cannot be shared with itself.';
                continue;
            }

            if (!is_string($groupUuid) || '' === $groupUuid) {
                $errors[] = 'Share rule #'.($index + 1).' requires a group.';
                continue;
            }

            $group = $this->groupRepository->findOneBy(['uuid' => $groupUuid]);
            if (is_null($group)) {
                $errors[] = 'Share rule #'.($index + 1).': group '.$groupUuid.' does not exist.';
                continue;
            }

            // The source lab is not required to be available in the group: this
            // is what allows a lab to stay hidden from the members of the group
            // it is shared with (the instance of the lab still belongs to G).
            if (!$target->getGroups()->contains($group)) {
                $errors[] = 'Lab '.$target->getName().' is not available in group '.$group->getPath().'.';
                continue;
            }

            $key = $target->getId().'|'.$group->getId();
            if (isset($seen[$key])) {
                $errors[] = 'Share rule with lab '.$target->getName().' in group '.$group->getPath().' is duplicated.';
                continue;
            }
            $seen[$key] = true;

            $prepared[] = ['sharedWith' => $target, 'group' => $group];
        }

        if ($errors) {
            return $errors;
        }

        foreach ($lab->getShares()->toArray() as $current) {
            $lab->removeShare($current);
        }

        foreach ($prepared as $item) {
            $share = new LabShare();
            $share
                ->setSharedWith($item['sharedWith'])
                ->setGroup($item['group'])
                ->setCreatedAt(new \DateTime());
            $lab->addShare($share);
        }

        $this->logger->info('[LabShareManager:replaceShares]::'.count($prepared).' share rule(s) set for lab '.$lab->getName());

        return [];
    }

    /**
     * Drop every share rule of a group where the given lab is the target
     * ((*, lab, group)). Used when a lab leaves a group.
     *
     * The rules where the lab is the source ((lab, *, group)) are kept: a source
     * lab is not required to be available in the group it is shared with.
     *
     * @return int the number of removed rules
     */
    public function purgeGroupShareRules(Lab $lab, Group $group): int
    {
        $removed = 0;

        foreach ($this->labShareRepository->findAsTargetInGroup($lab, $group) as $share) {
            $owner = $share->getLab();
            if (!is_null($owner)) {
                $owner->removeShare($share);
                $removed++;
            }
        }

        $this->logger->info('[LabShareManager:purgeGroupShareRules]::'.$removed.' share rule(s) dropped for lab '.$lab->getName().' as target in group '.$group->getPath());

        return $removed;
    }
}
