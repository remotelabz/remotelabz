<?php

namespace App\Command;

use App\Entity\Group;
use App\Entity\User;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists groups that have no owner and optionally assigns one.
 *
 * A group can lose its owner when the owner is removed from the group or
 * when their account is deleted. Such groups used to make the group tree
 * API crash (Group::getOwner() returned null), so they should be repaired.
 *
 * Usage
 * # List groups without an owner
 * php bin/console app:group:repair-owners
 *
 * # Assign user 1 as owner of every ownerless group
 * php bin/console app:group:repair-owners --owner=1
 *
 * # Same, but only show what would be done
 * php bin/console app:group:repair-owners --owner=root@localhost --dry-run
 */
#[AsCommand(
    name: 'app:group:repair-owners',
    description: 'List groups without an owner and optionally assign one',
)]
class RepairGroupOwnersCommand extends Command
{
    private GroupRepository $groupRepository;
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        GroupRepository $groupRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct();
        $this->groupRepository = $groupRepository;
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'owner',
                'o',
                InputOption::VALUE_REQUIRED,
                'User ID or email to set as owner of the ownerless groups'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Only display the groups that would be modified'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $groups = $this->groupRepository->createQueryBuilder('g')
            ->leftJoin('g.users', 'gu', 'WITH', 'gu.role = :ownerRole')
            ->where('gu.id IS NULL')
            ->setParameter('ownerRole', Group::ROLE_OWNER)
            ->orderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult();

        if (0 === count($groups)) {
            $io->success('Every group has an owner.');

            return Command::SUCCESS;
        }

        $io->section(sprintf('%d group(s) without an owner', count($groups)));
        $io->table(
            ['ID', 'Name', 'Slug', 'Visibility'],
            array_map(function (Group $group) {
                return [
                    $group->getId(),
                    $group->getName(),
                    $group->getSlug(),
                    $group->getVisibility(),
                ];
            }, $groups)
        );

        $ownerInput = $input->getOption('owner');

        if (null === $ownerInput) {
            $io->warning('No --owner option given: nothing was modified. Use --owner=<user id or email> to repair these groups.');

            return Command::SUCCESS;
        }

        $owner = $this->findUser((string) $ownerInput);

        if (null === $owner) {
            $io->error(sprintf('User "%s" was not found.', $ownerInput));

            return Command::FAILURE;
        }

        if ($input->getOption('dry-run')) {
            $io->note(sprintf('Dry run: %d group(s) would be owned by %s.', count($groups), $owner->getEmail()));

            return Command::SUCCESS;
        }

        foreach ($groups as $group) {
            $entry = $group->getGroupUserEntry($owner);

            if (null !== $entry) {
                // The user is already a member (admin, user, ...): promote them.
                $entry->setRole(Group::ROLE_OWNER);
            } else {
                $group->addUser($owner, Group::ROLE_OWNER);
            }

            $this->entityManager->persist($group);
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d group(s) now owned by %s.', count($groups), $owner->getEmail()));

        return Command::SUCCESS;
    }

    private function findUser(string $input): ?User
    {
        if (ctype_digit($input)) {
            return $this->userRepository->find((int) $input);
        }

        return $this->userRepository->findOneBy(['email' => $input]);
    }
}
