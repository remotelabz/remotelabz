<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Controller\TemplateController;

class CleanupTemplatesCommand extends Command
{
    protected static $defaultName = 'app:templates:cleanup';
    private $templateController;

    public function __construct(TemplateController $templateController)
    {
        parent::__construct();
        $this->templateController = $templateController;
    }

    protected function configure()
    {
        $this->setDescription('Clean orphaned template YAML files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Template Files Cleanup');
        
        $stats = $this->templateController->cleanOrphanedTemplateFiles();
        
        $io->success(sprintf(
            'Cleanup completed! Scanned: %d, Deleted: %d, Kept: %d',
            $stats['scanned'],
            $stats['deleted'],
            $stats['kept']
        ));
        
        if (!empty($stats['errors'])) {
            $io->error('Errors occurred:');
            $io->listing($stats['errors']);
        }
        
        return Command::SUCCESS;
    }
}