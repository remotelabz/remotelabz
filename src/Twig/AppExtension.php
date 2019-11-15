<?php

namespace App\Twig;

use Twig\TwigFilter;
use Twig\Environment;
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
            new TwigFunction('svg', [$this, 'renderSvg'], ['is_safe' => ['html']]),
            new TwigFunction('category', [$this, 'setActiveCategory'], ['is_safe' => ['html'], 'needs_context' => true])
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

    public function renderSvg($svg, $class = 'image-sm v-sub')
    {
        // $package = new Package(new EmptyVersionStrategy());
        // $url = $package->getUrl(__DIR__.'../../../public/build/svg/'.$svg.'.svg');

        // readfile($url);
        return '<svg class="' . $class . '"><use xlink:href="/build/svg/icons.svg#'.$svg.'"></use></svg>';
    }

    public function setActiveCategory($context, string $category)
    {
        if ($context['category'] == $context) {
            return "active";
        }
    }
}
