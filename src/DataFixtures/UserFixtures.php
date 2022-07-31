<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpKernel\KernelInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserFixtures extends Fixture implements ContainerAwareInterface
{
    /**
     * The dependency injection container.
     *
     * @var ContainerInterface
     */
    protected $container;

    private $passwordEncoder;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    public function load(ObjectManager $manager)
    {
        /* Static data, for super-admin */
        $user = new User();

        $user->setLastName("Doe")
            ->setFirstName("John")
            ->setEmail("root@localhost")
            ->setRoles(['ROLE_SUPER_ADMINISTRATOR'])
            ->setPassword($this->passwordEncoder->encodePassword(
                $user,
                'admin'
            ));

        $this->addReference('root', $user);

        $manager->persist($user);

        // Flush once before to ensure admin has ID == 1
        $manager->flush();

        /* Other data, test purpose */
        /** @var KernelInterface $kernel */
        $kernel = $this->container->get('kernel');
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
