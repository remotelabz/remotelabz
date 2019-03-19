<?php

namespace App\Twig;

use Twig\TwigFilter;
use Twig\Extension\AbstractExtension;
use Doctrine\ORM\PersistentCollection;

class AppExtension extends AbstractExtension
{
    public function getFilters()
    {
        return array(
            new TwigFilter('cast_to_array', array($this, 'stdClassObject'))
        );
    }

    public function stdClassObject($object)
    {
        $properties = [];

        foreach ((array) $object as $key => $value) {
            $properties[$key] = $value;
        }

        return $properties;
    }
}
