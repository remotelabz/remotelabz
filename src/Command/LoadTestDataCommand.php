<?php

namespace App\Command;

use App\DataFixtures\DeviceTestDataFixtures;
use App\DataFixtures\OperatingSystemTestDataFixtures;
use App\Entity\Device;
use App\Entity\OperatingSystem;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:fixtures:test-data')]
class LoadTestDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OperatingSystemTestDataFixtures $operatingSystemTestDataFixtures,
        private readonly DeviceTestDataFixtures $deviceTestDataFixtures,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Load test operating systems and devices used to check the pagination')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Run the fixtures then roll every change back'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $connection = $this->entityManager->getConnection();

        $operatingSystemsBefore = $this->count(OperatingSystem::class);
        $devicesBefore = $this->count(Device::class);

        $connection->beginTransaction();

        try {
            $executor = new ORMExecutor($this->entityManager);
            $executor->execute(
                [$this->operatingSystemTestDataFixtures, $this->deviceTestDataFixtures],
                true
            );

            $createdOperatingSystems = $this->count(OperatingSystem::class) - $operatingSystemsBefore;
            $createdDevices = $this->count(Device::class) - $devicesBefore;

            if ($dryRun) {
                $connection->rollBack();
            } else {
                $connection->commit();
            }
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            $this->logger->error('[LoadTestDataCommand] Test fixtures loading failed', [
                'message' => $e->getMessage(),
            ]);
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->title($dryRun ? 'Test fixtures (dry run)' : 'Test fixtures loaded');
        $io->listing([
            sprintf('%d operating system(s) %s', $createdOperatingSystems, $dryRun ? 'would be created' : 'created'),
            sprintf('%d device(s) %s', $createdDevices, $dryRun ? 'would be created' : 'created'),
        ]);

        if (!$dryRun && $createdOperatingSystems === 0 && $createdDevices === 0) {
            $io->note('Nothing to do, the test data is already loaded.');
        }

        $io->success(sprintf(
            'Operating systems: %d total, devices: %d total',
            $this->count(OperatingSystem::class),
            $this->count(Device::class)
        ));

        return Command::SUCCESS;
    }

    private function count(string $class): int
    {
        return (int) $this->entityManager->getRepository($class)->count([]);
    }
}
