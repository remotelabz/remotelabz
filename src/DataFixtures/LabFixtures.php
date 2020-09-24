<?php

namespace App\DataFixtures;

use Faker\Factory;
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

    public const COUNT = 5;

    public function load(ObjectManager $manager)
    {
        /** @var KernelInterface $kernel */
        $kernel = $this->container->get('kernel');

        if (in_array($kernel->getEnvironment(), ['dev', 'test'])) {
            $faker = Factory::create();

            $description = <<<EOF
# Scelerisque porttitor tincidunt potenti a sa

Lorem ipsum dolor sit amet consectetur adipiscing elit mattis mus natoque, hendrerit purus elementum odio aliquam eros proin nulla ridiculus erat, facilisis litora tincidunt praesent sollicitudin habitasse luctus cum hac. Erat sociis sagittis cras cursus duis a, morbi quis etiam aliquam facilisi, tellus enim varius platea montes. Curae placerat felis convallis velit netus fermentum et praesent eros, in nostra porta dictumst proin lobortis dictum bibendum, ligula fusce massa scelerisque ornare cum odio euismod. 

Taciti ad quisque litora duis viverra erat volutpat arcu vestibulum, leo himenaeos cubilia facilisi nascetur scelerisque justo eget, eleifend rutrum ut tempor porta laoreet dictum id. Arcu urna tincidunt ornare rhoncus litora venenatis, sociosqu lobortis bibendum habitasse maecenas lacus suspendisse, ad dapibus curabitur orci risus. Sem ridiculus aliquam habitant ut sollicitudin egestas neque magna, suspendisse scelerisque facilisis consequat fermentum vel odio aptent mollis, luctus ultrices dignissim accumsan proin felis lacinia. 

## Facilisis pellentesque lacus aenean sc

- Parturient ut nulla aliquet nunc blandit, leo phasellus sem arcu.

- Potenti sem taciti fringilla porttitor, proin tempor viverra.

- Est arcu mauris eros erat, libero cursus nibh.

- Varius auctor tristique neque platea vehicula, himenaeos porttitor litora.



Odio penatibus commodo bibendum nullam pellentesque arcu, consequat per nibh fames turpis dignissim lectus, primis sagittis fermentum at laoreet. Interdum magnis sociosqu ut quis semper platea commodo taciti vulputate, leo neque proin nulla ad vestibulum posuere faucibus lectus, inceptos cras per integer pulvinar nunc accumsan ligula. Inceptos justo velit metus mattis eleifend himenaeos neque quam a leo, est et vivamus sapien curabitur duis iaculis tellus.
EOF;

            foreach (range(1, self::COUNT) as $number) {
                $lab = Lab::create()
                    ->setName($faker->words(2, true))
                    ->setDescription($description)
                    ->setAuthor($this->getReference('user' . $faker->numberBetween(0, 9)));

                $manager->persist($lab);

                $this->addReference('lab' . $number, $lab);
            }

            $manager->flush();
        }
    }

    public function getDependencies()
    {
        return [
            UserFixtures::class,
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
