<?php

namespace App\Twig;

use Twig\TwigFilter;
use Twig\Environment;
use Twig\TwigFunction;
use Symfony\Component\Asset\Package;
use Twig\Extension\AbstractExtension;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class AppExtension extends AbstractExtension
{
    private $rootDirectory;
    private $gfmConverter;
    public function __construct(string $rootDirectory)
    {
        $this->rootDirectory = $rootDirectory;
        $this->gfmConverter = new GithubFlavoredMarkdownConverter();
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('cast_to_array', [$this, 'stdClassObject']),
            new TwigFilter('firstLetter', [$this, 'firstLetterFilter']),
            new TwigFilter('truncate', [$this, 'truncate']),
            new TwigFilter('json_decode', [$this, 'jsonDecode']),
            // GFM (tables, strikethrough, autolinks, task lists) so the
            // server-side rendering matches the EasyMDE preview
            new TwigFilter('markdown_gfm_to_html', [$this, 'markdownGfmToHtml'], ['is_safe' => ['html']]),
        ];
    }

    public function markdownGfmToHtml(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return '';
        }
        return (string) $this->gfmConverter->convert($text);
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('svg', [$this, 'renderSvg'], ['is_safe' => ['html']]),
            new TwigFunction('category', [$this, 'setActiveCategory'], ['is_safe' => ['html'], 'needs_context' => true]),
            new TwigFunction('groupicon', [$this, 'getGroupIcon'], ['is_safe' => ['html']]),
            new TwigFunction('svg_icons_list', [$this, 'getSvgIconsList']),
        ];
    }

    public function stdClassObject($data)
    {
        if ((! is_array($data)) and (! is_object($data)))
            return 'xxx'; // $data;

        $result = array();

        $data = (array) $data;
        foreach ($data as $key => $value) {
            if (is_object($value))
                $value = (array) $value;
            if (is_array($value))
                $result[$key] = $this->stdClassObject($value);
            else
                $result[$key] = $value;
        }
        return $result;
    }

    public function firstLetterFilter(string $str)
    {
        return substr($str, 0, 1);
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

    public function groupicon($value)
    {
        
    }
    
    public function jsonDecode(string|array $value, bool $assoc = true): array
    {
        if (is_array($value)) {
            return $value;
        }

        return json_decode($value, $assoc) ?? [];
    }

    public function truncate(?string $text, int $length = 50, string $suffix = '...'): string
    {
        if (empty($text)) {
            return '';
        }

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . $suffix;
    }

    /**
     * Récupère la liste des icônes SVG depuis le fichier icons.svg
     */
    public function getSvgIconsList(): array
    {
        $svgFile = $this->rootDirectory . '/public/build/svg/icons.svg';
        
        if (!file_exists($svgFile)) {
            return [];
        }

        $content = file_get_contents($svgFile);
        $icons = [];

        // Utilisation d'une regex pour extraire les ID des symboles
        preg_match_all('/<symbol[^>]*id="([^"]*)"/', $content, $matches);
        
        if (!empty($matches[1])) {
            $icons = $matches[1];
        }

        return $icons;
    }
}
