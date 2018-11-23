<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\User;
use Faker\Factory as RandomDataFactory;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserFixtures extends Fixture
{
    private $passwordEncoder;
 
    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }
    
    public function load(ObjectManager $manager)
    {
        /* Static data, for super-admin */
        $user = new User();

        $user->setLastName("Nolot")
        ->setFirstName("Florent")
        ->setEmail("root@localhost")
        ->setRoles(['ROLE_SUPER_ADMINISTRATOR'])
        ->addSwarm($this->getReference(SwarmFixtures::LAST_SWARM))
        ->setPassword($this->passwordEncoder->encodePassword(
            $user,
            'admin'
        ));
        
        $manager->persist($user);

        // Flush once before to ensure admin has ID == 1
        $manager->flush();

        /* Other data, test purpose */
        $faker = RandomDataFactory::create('fr_FR');
 
        for ($i = 0; $i < 10; $i++) {
            $user = new User();

            $user->setFirstName($faker->firstName)
            ->setLastName($faker->lastName)
            ->setEmail(sprintf('userdemo%d@example.com', $i))
            ->setPassword($this->passwordEncoder->encodePassword(
                $user,
                'userdemo'
            ));

            $manager->persist($user);
        }
 
        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            SwarmFixtures::class,
        ];
    }
}
