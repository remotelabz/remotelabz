<?php

namespace App\Twig;

use Twig\TwigFilter;
use Twig\TwigFunction;
use Symfony\Component\Asset\Package;
use Twig\Extension\AbstractExtension;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;

class AppExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('cast_to_array', [$this, 'stdClassObject'])
        ];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('svg', [$this, 'renderSvg'], ['is_safe' => ['html']])
        ];
    }

    public function stdClassObject($object)
    {
        $properties = [];

        foreach ((array) $object as $key => $value) {
            $properties[$key] = $value;
        }

        return $properties;
    }

    public function renderSvg($svg)
    {
        $package = new Package(new EmptyVersionStrategy());
        $url = $package->getUrl(__DIR__.'../../../public/build/svg/'.$svg.'.svg');

        readfile($url);
    }
}
