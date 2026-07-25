<?php

namespace App\Command;

use App\Entity\LabInstance;
use App\Service\Worker\WorkerManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Repository\LabRepository;

class TestHiddenLoadCommand extends Command
{
    protected static $defaultName = 'app:test-hidden-load';
    private $em;
    private $wm;
    private $lr;

    public function __construct(EntityManagerInterface $em, WorkerManager $wm, LabRepository $lr)
    {
        $this->em = $em;
        $this->wm = $wm;
        $this->lr = $lr;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repo = $this->em->getRepository(LabInstance::class);
        $instances = $repo->findBy(['state' => 'creating']);
        
        $output->writeln("Found " . count($instances) . " instances in state 'creating'");
        
        foreach ($instances as $inst) {
            $output->writeln("Instance: " . $inst->getUuid() . " on worker: " . $inst->getWorkerIp());
            if ($inst->getLab()) {
                $mem = $this->wm->Memory_Usage($inst->getLab());
                $output->writeln(" - Memory usage: " . $mem . " MB");
            }
        }
        
        $lab = $this->lr->find(16);
        if ($lab) {
            $output->writeln("Lab 16 memory needed: " . $this->wm->Memory_Usage($lab) . " MB");
        }

        return Command::SUCCESS;
    }
}
