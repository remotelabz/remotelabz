<?php

namespace App\Controller;

use App\Repository\LoginLogRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
class LoginLogController extends Controller
{
    private const PAGE_LIMIT = 25;

    private $loginLogRepository;
    private $paginator;

    public function __construct(LoginLogRepository $loginLogRepository, PaginatorInterface $paginator)
    {
        $this->loginLogRepository = $loginLogRepository;
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

        return $this->render('login_log/index.html.twig', [
            'logs' => $pagination->getItems(),
            'pagination' => $pagination,
            'start' => $start,
            'end' => $end,
            'userFilter' => $userFilter,
            'total' => $pagination->getTotalItemCount(),
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
