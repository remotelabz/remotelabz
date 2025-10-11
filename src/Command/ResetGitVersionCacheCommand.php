<?php

namespace App\Command;

use App\Service\GitVersionService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ResetGitVersionCacheCommand extends Command
{
    protected static $defaultName = 'app:cache:clear-git-version';
    protected static $defaultDescription = 'Reset the Git version cache';

    private $gitVersionService;

    public function __construct(GitVersionService $gitVersionService)
    {
        parent::__construct();
        $this->gitVersionService = $gitVersionService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->gitVersionService->invalidateCache();
            $io->success('Git version cache has been successfully reset.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}