<?php

namespace App\DataFixtures;

use App\Entity\Lab;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

class LabFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface, ContainerAwareInterface
{
    /**
     * The dependency injection container.
     *
     * @var ContainerInterface
     */
    protected $container;

    public function load(ObjectManager $manager)
    {
        /** @var KernelInterface $kernel */
        $kernel = $this->container->get('kernel');
   }

    public function getDependencies()
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
