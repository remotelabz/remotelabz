<?php

namespace App\DataFixtures;

use App\Entity\Lab;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class LabFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    
    private KernelInterface $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function load(ObjectManager $manager): void
    {
        /** @var KernelInterface $kernel */
        $environment = $this->kernel->getEnvironment();
   }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            DeviceFixtures::class
        ];
    }

    public static function getGroups(): array
    {
        return ['labs'];
    }

    /**
     * {@inheritDoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }
}
