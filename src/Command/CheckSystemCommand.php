<?php

namespace App\Command;

use App\Service\Monitor\SshConnectionMonitor;
use App\Service\Monitor\CertificateMonitor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckSystemCommand extends Command
{
    protected static $defaultName = 'app:check:system';
    protected static $defaultDescription = 'Check SSH connections and SSL certificates validity';

    private $sshMonitor;
    private $certMonitor;

    public function __construct(
        SshConnectionMonitor $sshMonitor,
        CertificateMonitor $certMonitor
    ) {
        parent::__construct();
        $this->sshMonitor = $sshMonitor;
        $this->certMonitor = $certMonitor;
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addOption('ssh', null, InputOption::VALUE_NONE, 'Check SSH connections only')
            ->addOption('cert', null, InputOption::VALUE_NONE, 'Check certificates only')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command checks system health:

  <info>php %command.full_name%</info>

Check only SSH connections:
  <info>php %command.full_name% --ssh</info>

Check only certificates:
  <info>php %command.full_name% --cert</info>

Get JSON output:
  <info>php %command.full_name% --json</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $checkSsh = $input->getOption('ssh');
        $checkCert = $input->getOption('cert');
        $jsonOutput = $input->getOption('json');
        
        // If no specific check is requested, check both
        if (!$checkSsh && !$checkCert) {
            $checkSsh = true;
            $checkCert = true;
        }

        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ssh' => null,
            'certificates' => null,
            'overall_status' => 'OK'
        ];

        // Check SSH connections
        if ($checkSsh) {
            $sshResults = $this->checkSshConnections($io, $jsonOutput);
            $results['ssh'] = $sshResults;
            if (!$sshResults['overall_status']) {
                $results['overall_status'] = 'WARNING';
            }
        }

        // Check certificates
        if ($checkCert) {
            $certResults = $this->checkCertificates($io, $jsonOutput);
            $results['certificates'] = $certResults;
            if (!$certResults['overall_status']) {
                $results['overall_status'] = 'ERROR';
            }
        }

        // Output results
        if ($jsonOutput) {
            $output->writeln(json_encode($results, JSON_PRETTY_PRINT));
        } else {
            $io->newLine();
            if ($results['overall_status'] === 'OK') {
                $io->success('All checks passed successfully!');
            } elseif ($results['overall_status'] === 'WARNING') {
                $io->warning('Some checks have warnings');
            } else {
                $io->error('Some checks failed');
            }
        }

        return $results['overall_status'] === 'OK' ? Command::SUCCESS : Command::FAILURE;
    }

    private function checkSshConnections(SymfonyStyle $io, bool $jsonOutput): array
    {
        if (!$jsonOutput) {
            $io->section('SSH Connection Check');
        }

        $sshStatus = $this->sshMonitor->isStarted();
        
        $overallStatus = true;
        $details = [];
        $rows = [];

        foreach ($sshStatus as $ip => $status) {
            if ($ip === 'overall_status') {
                continue;
            }

            if (is_array($status)) {
                if ($status['status']) {
                    $statusText = '✓ Connected';
                    $method = $status['method'];
                    $error = '-';
                } else {
                    $statusText = '✗ Failed';
                    $method = '-';
                    $error = $status['error'];
                    $overallStatus = false;
                }

                if (!$jsonOutput) {
                    $rows[] = [$ip, $statusText, $method, $error];
                }

                $details[$ip] = [
                    'status' => $status['status'],
                    'method' => $status['method'],
                    'error' => $status['error']
                ];
            }
        }

        if (!$jsonOutput && !empty($rows)) {
            $io->table(
                ['Worker IP', 'Status', 'Auth Method', 'Error'],
                $rows
            );
        }

        return [
            'overall_status' => $overallStatus,
            'workers' => $details
        ];
    }

    private function checkCertificates(SymfonyStyle $io, bool $jsonOutput): array
    {
        if (!$jsonOutput) {
            $io->section('Certificate Check');
        }

        $certStatus = $this->certMonitor->isStarted();
        
        $overallStatus = $certStatus['overall_status'];
        $rows = [];
        $details = [];

        foreach ($certStatus['certificates'] as $key => $cert) {
            $statusIcon = $cert['valid'] ? '✓' : '✗';
            
            if ($cert['valid']) {
                if (isset($cert['warning']) && $cert['warning']) {
                    $statusText = $statusIcon . ' Valid (Warning)';
                    $statusColor = 'yellow';
                } else {
                    $statusText = $statusIcon . ' Valid';
                    $statusColor = 'green';
                }
            } else {
                $statusText = $statusIcon . ' Invalid';
                $statusColor = 'red';
            }

            $daysRemaining = isset($cert['days_remaining']) ? $cert['days_remaining'] . ' days' : 'N/A';
            $error = $cert['error'] ?? '-';

            if (!$jsonOutput) {
                $rows[] = [
                    $cert['name'],
                    sprintf('<%s>%s</>', $statusColor, $statusText),
                    $daysRemaining,
                    $error
                ];
            }

            $details[$key] = [
                'name' => $cert['name'],
                'valid' => $cert['valid'],
                'days_remaining' => $cert['days_remaining'] ?? null,
                'expires_at' => $cert['expires_at'] ?? null,
                'warning' => $cert['warning'] ?? false,
                'error' => $cert['error']
            ];
        }

        if (!$jsonOutput && !empty($rows)) {
            $io->table(
                ['Certificate', 'Status', 'Expires In', 'Error/Info'],
                $rows
            );
        }

        return [
            'overall_status' => $overallStatus,
            'certificates' => $details
        ];
    }
}