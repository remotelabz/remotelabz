<?php

namespace App\Twig;

use Twig\TwigFilter;
use Twig\Extension\AbstractExtension;

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
        $properties = array();

        foreach ($object as $key => $value) {
            if (is_object($value)) {
                $value = '<a href="' .
                    $value->getId() .
                    '">' .
                    $value->getName() .
                    '</a>'
                ;
            }
            
            $properties[] = array($key, $value);
        }

        return $properties;
    }
}
