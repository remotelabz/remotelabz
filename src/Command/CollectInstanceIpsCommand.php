<?php

namespace App\Command;

use App\Repository\DeviceInstanceRepository;
use App\Service\Instance\DeviceInfoCache;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Remotelabz\Message\Message\DeviceInfoRequestMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Demande aux workers l'IP des instances demarrees et rafraichit le cache Redis.
 *
 * Lancee par le timer systemd remotelabz-instance-ips.timer (toutes les
 * minutes). Une entree n'est redemandee que si elle est absente ou periemee
 * (parametre INSTANCE_IP_MAX_AGE, 10 min par defaut), ce qui evite d'inonder
 * RabbitMQ : premiere IP sous une minute apres un demarrage, puis rafraichissement
 * toutes les INSTANCE_IP_MAX_AGE secondes.
 *
 * Usage :
 *   php bin/console app:instance:collect-ips
 *   php bin/console app:instance:collect-ips --dry-run
 *   php bin/console app:instance:collect-ips --max-age=300
 */
#[AsCommand(
    name: 'app:instance:collect-ips',
    description: 'Demande aux workers l IP des instances demarrees et rafraichit le cache Redis',
)]
class CollectInstanceIpsCommand extends Command
{
    /** Groupe de serialisation du descripteur envoye au worker (comme ACTION_START). */
    private const WORKER_GROUPS = ['worker'];

    public function __construct(
        private DeviceInstanceRepository $deviceInstanceRepository,
        private DeviceInfoCache $cache,
        private SerializerInterface $serializer,
        private MessageBusInterface $bus,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private int $deviceInfoMaxAge = 600
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'max-age',
                null,
                InputOption::VALUE_REQUIRED,
                'Age maximum (secondes) d\'une entree avant re-collecte',
                (string) $this->deviceInfoMaxAge
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Afficher les demandes qui seraient envoyees sans rien dispatcher'
            )
            ->setHelp(
                'Parcourt les instances demarrees, verifie le cache Redis et demande l\'IP au worker '
                .'uniquement si l\'entree est absente ou periemee.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $maxAge = (int) $input->getOption('max-age');

        $deviceInstances = $this->deviceInstanceRepository->findStartedWithWorker();

        $io->title(sprintf(
            '%d instance(s) demarree(s) sur un worker (peremption : %d s)%s',
            count($deviceInstances),
            $maxAge,
            $dryRun ? ' [dry-run]' : ''
        ));

        // Un descripteur (JSON du LabInstance) est reconstruit une seule fois par
        // lab et reutilise pour chacune de ses instances.
        $labJsonByLab = [];
        $requested = 0;
        $skippedFresh = 0;
        $skippedInFlight = 0;

        foreach ($deviceInstances as $deviceInstance) {
            $uuid = $deviceInstance->getUuid();
            $labInstance = $deviceInstance->getLabInstance();
            if (null === $labInstance) {
                continue;
            }

            if (!$this->cache->isStale($uuid, $maxAge)) {
                ++$skippedFresh;
                continue;
            }

            $requestId = $this->cache->lockAndGetRequestId($uuid);
            if (null === $requestId) {
                // Une requete est deja en vol pour cette instance.
                ++$skippedInFlight;
                continue;
            }

            $labUuid = $labInstance->getUuid();
            if (!isset($labJsonByLab[$labUuid])) {
                $context = SerializationContext::create()->setGroups(self::WORKER_GROUPS);
                $labJsonByLab[$labUuid] = $this->serializer->serialize($labInstance, 'json', $context);
            }

            if ($dryRun) {
                // Le verrou a ete pose : on le retire pour laisser l'etat intact.
                $this->cache->release($uuid, $requestId);
                $io->writeln(sprintf('  [dry-run] %s -> worker %s', $uuid, (string) $labInstance->getWorkerIp()));
                ++$requested;
                continue;
            }

            try {
                $this->bus->dispatch(
                    new DeviceInfoRequestMessage($labJsonByLab[$labUuid], $uuid, $requestId),
                    [new AmqpStamp((string) $labInstance->getWorkerIp(), AMQP_NOPARAM, [])]
                );
                ++$requested;
            } catch (\Throwable $e) {
                // Ne pas laisser un verrou orphelin si le dispatch echoue.
                $this->cache->release($uuid, $requestId);
                $this->logger->error(
                    '[CollectInstanceIpsCommand]::Impossible de demander l\'IP de ' . $uuid . ' : ' . $e->getMessage()
                );
            }
        }

        $io->success(sprintf(
            '%d demande(s) envoyee(s), %d entree(s) fraiche(s), %d en vol.',
            $requested,
            $skippedFresh,
            $skippedInFlight
        ));

        // Liberer l'EntityManager (commande oneshot, coherence avec les autres commandes).
        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
