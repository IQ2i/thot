<?php

namespace App\Command;

use App\Bridge\BridgeInterface;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand(
    name: 'app:document:sync',
    description: 'Sync documents between Thot and external sources',
)]
class SyncDocumentCommand extends Command
{
    public function __construct(
        /**
         * @var BridgeInterface[]
         */
        #[AutowireIterator('iq2i_thot.bridge')]
        private readonly iterable $bridges,
        private readonly EntityManagerInterface $entityManager,
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
                $bridge = array_find(iterator_to_array($this->bridges), fn (BridgeInterface $bridge): bool => $bridge->supports($source));
                if (null === $bridge) {
                    continue;
                }

                $io->block(mb_strtoupper($source));

                $io->comment('Import new documents...');
                $bridge->importNewDocuments($source, $allOption);

                $io->comment('Update documents...');
                $bridge->updateDocuments($source, $allOption);
            }
        }

        return Command::SUCCESS;
    }
}
