<?php

namespace App\Controller;

use App\Repository\IpReputationRepository;
use App\Repository\LoginLogRepository;
use App\Service\IpReputationService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
class LoginLogController extends Controller
{
    private const PAGE_LIMIT = 25;

    private $loginLogRepository;
    private $ipReputationRepository;
    private $ipReputationService;
    private $paginator;

    public function __construct(
        LoginLogRepository $loginLogRepository,
        IpReputationRepository $ipReputationRepository,
        IpReputationService $ipReputationService,
        PaginatorInterface $paginator
    ) {
        $this->loginLogRepository = $loginLogRepository;
        $this->ipReputationRepository = $ipReputationRepository;
        $this->ipReputationService = $ipReputationService;
        $this->paginator = $paginator;
    }

    #[Route(path: '/admin/login-logs', name: 'login_logs', methods: ['GET'])]
    public function indexAction(Request $request)
    {
        [$start, $end] = $this->getDateRange($request);
        $userFilter = trim($request->query->get('user', ''));
        $page = $request->query->getInt('page', 1);

        $pagination = $this->paginator->paginate(
            $this->loginLogRepository->createQueryBuilderForRange($start, $end, $userFilter),
            $page,
            self::PAGE_LIMIT
        );

        $logs = $pagination->getItems();
        $ips = array_values(array_unique(array_map(
            static fn ($log) => $log->getIp(),
            $logs
        )));

        return $this->render('login_log/index.html.twig', [
            'logs' => $logs,
            'pagination' => $pagination,
            'start' => $start,
            'end' => $end,
            'userFilter' => $userFilter,
            'total' => $pagination->getTotalItemCount(),
            'reputations' => $this->ipReputationRepository->findByIps($ips),
            'abuseipdbCheckBase' => $this->ipReputationService->getCheckUrlBase(),
        ]);
    }

    private function getDateRange(Request $request): array
    {
        $start = $this->parseDate($request->query->get('start'), (new \DateTime('now'))->modify('-7 days')->setTime(0, 0, 0));
        $end = $this->parseDate($request->query->get('end'), new \DateTime('now'));

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $end->setTime(23, 59, 59);

        return [$start, $end];
    }

    private function parseDate(?string $value, \DateTime $default): \DateTime
    {
        if (!$value) {
            return $default;
        }

        try {
            return new \DateTime($value);
        } catch (\Exception $e) {
            return $default;
        }
    }
}
