<?php

namespace App\Twig;

use App\Entity\SiteMessage;
use App\Entity\User;
use App\Repository\SiteMessageRepository;
use Symfony\Component\Security\Core\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension to display persistent site messages (information type)
 * to connected users.
 */
class SiteMessageExtension extends AbstractExtension
{
    private SiteMessageRepository $siteMessageRepository;
    private Security $security;

    public function __construct(SiteMessageRepository $siteMessageRepository, Security $security)
    {
        $this->siteMessageRepository = $siteMessageRepository;
        $this->security = $security;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_user_site_messages', [$this, 'getUserSiteMessages']),
        ];
    }

    /**
     * @return SiteMessage[]
     */
    public function getUserSiteMessages(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return $this->siteMessageRepository->findActiveForUser($user);
    }
}
