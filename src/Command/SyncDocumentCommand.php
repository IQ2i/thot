<?php

namespace App\Command;

use App\Entity\Project;
use App\Service\SourceUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:document:sync',
    description: 'Sync documents between Thot and external sources',
)]
class SyncDocumentCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SourceUpdater $sourceUpdater,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('project', InputArgument::OPTIONAL, 'ID of the project to sync')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Sync all documents (opened and closed)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $allOption = $input->getOption('all');

        /** @var Project[] $projects */
        $projects = $input->getArgument('project')
            ? [$this->entityManager->getRepository(Project::class)->find($input->getArgument('project'))]
            : $this->entityManager->getRepository(Project::class)->findAll();

        foreach ($projects as $project) {
            $io->section($project->getName());

            foreach ($project->getSources() as $source) {
                $io->block(mb_strtoupper($source));

                $this->sourceUpdater->update($source, $allOption);
            }
        }

        return Command::SUCCESS;
    }
}
