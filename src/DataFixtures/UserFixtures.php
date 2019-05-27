<?php

namespace App\DataFixtures;

use App\Entity\User;
use Faker\Factory as RandomDataFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserFixtures extends Fixture implements DependentFixtureInterface
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
            ->addCourse($this->getReference(CourseFixtures::LAST_COURSE))
            ->setPassword($this->passwordEncoder->encodePassword(
                $user,
                'admin'
            ))
        ;
        
        $manager->persist($user);

        // Flush once before to ensure admin has ID == 1
        $manager->flush();

        /* Traditional user */
        $user = new User();
        $user->setLastName("Hubert")
            ->setFirstName("Julien")
            ->setEmail("user@localhost")
            ->addCourse($this->getReference(CourseFixtures::LAST_COURSE))
            ->setPassword(
                $this->passwordEncoder->encodePassword(
                    $user,
                    'user'
                )
            )
        ;
        $manager->persist($user);

        /* Other data, test purpose */
        // $faker = RandomDataFactory::create('fr_FR');
 
        // for ($i = 0; $i < 10; $i++) {
        //     $user = new User();

        //     $user->setFirstName($faker->firstName)
        //         ->setLastName($faker->lastName)
        //         ->setEmail($faker->safeEmail)
        //         ->setPassword($this->passwordEncoder->encodePassword(
        //             $user,
        //             'userdemo'
        //         ))
        //     ;

        //     $manager->persist($user);
        // }
 
        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            CourseFixtures::class,
        ];
    }
}
