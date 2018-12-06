<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Course;

class CourseFixtures extends Fixture
{
    public const LAST_COURSE = 'last-course';

    public function load(ObjectManager $manager)
    {
        for ($i = 0; $i < 5; $i++) {
            $course = new Course();

            $course->setName(sprintf('Course %d', $i));

            $manager->persist($course);
        }
 
        $manager->flush();

        $this->addReference(self::LAST_COURSE, $course);
    }
}
