<?php

declare(strict_types=1);

namespace App\Command;

use App\AI\AiManager;
use App\Entity\Document;
use App\Entity\Project;
use App\Service\DocumentSplitter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(name: 'app:document:index', description: 'Index documents in Meilisearch')]
class IndexDocumentCommand extends Command
{
    public function __construct(
        private readonly AiManager $aiManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Sync all documents (opened and closed)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexAll = $input->getOption('all');
        if ($indexAll) {
            $this->aiManager->resetStore();
        }

        /** @var Project[] $projects */
        $projects = $this->entityManager->getRepository(Project::class)->findAll();
        foreach ($projects as $project) {
            $textDocuments = [];

            $documents = $this->entityManager->getRepository(Document::class)->findToIndex($project, $indexAll);
            foreach ($documents as $document) {
                $chunks = DocumentSplitter::split($document->getContent() ?? '', 3000, 300);
                foreach ($chunks as $chunk) {
                    $content = 'Title: '.$document->getTitle().\PHP_EOL.'Source: '.$document->getSource()->getType()->value.\PHP_EOL.'Content: '.$chunk;
                    $textDocuments[] = new TextDocument(
                        id: Uuid::v4(),
                        content: $content,
                        metadata: new Metadata([
                            'title' => $document->getTitle(),
                            'content' => $document->getContent(),
                            'source' => $document->getSource()->getType()->value,
                            'project' => $project->getId(),
                            'closed' => $document->isClosed(),
                            'createdAt' => $document->getCreatedAt()?->format('Y-m-d H:i:s'),
                            'web_url' => $document->getWebUrl(),
                        ])
                    );

                    if (25 === \count($textDocuments)) {
                        $this->aiManager->index($textDocuments);
                        $this->entityManager->flush();

                        $textDocuments = [];
                    }
                }

                $document->setIndexedAt(new \DateTime());
            }

            $this->aiManager->index($textDocuments);
            $this->entityManager->flush();
        }

        return Command::SUCCESS;
    }
}
