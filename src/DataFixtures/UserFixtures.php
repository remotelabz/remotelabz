<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpKernel\KernelInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture 
{
    private KernelInterface $kernel;

    private $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher, KernelInterface $kernel)
    {
        $this->passwordHasher = $passwordHasher;
        $this->kernel = $kernel;
    }

    public function load(ObjectManager $manager): void
    {
        /* Static data, for super-admin */
        $user = new User();

        $user->setLastName("Doe")
            ->setFirstName("John")
            ->setEmail("root@localhost")
            ->setRoles(['ROLE_SUPER_ADMINISTRATOR'])
            ->setPassword($this->passwordHasher->hashPassword(
                $user,
                'admin'
            ));

        $this->addReference('root', $user);

        $manager->persist($user);

        // Flush once before to ensure admin has ID == 1
        $manager->flush();

        /* Other data, test purpose */
        /** @var KernelInterface $kernel */
        $environment = $this->kernel->getEnvironment();
        $manager->flush();
    }

    /**
     * {@inheritDoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }
}
