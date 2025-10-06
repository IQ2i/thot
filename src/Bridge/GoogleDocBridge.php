<?php

namespace App\Bridge;

use App\Entity\Document;
use App\Entity\GoogleDoc;
use App\Entity\Source;
use App\Service\MarkdownCleaner;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client;
use Google\Service\Docs;
use Google\Service\Drive;
use Google\Service\Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class GoogleDocBridge implements BridgeInterface
{
    public function __construct(
        #[Autowire(env: 'resolve:GOOGLE_CREDENTIALS_PATH')]
        private string $credentialsPath,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function supports(Source $source): bool
    {
        return $source instanceof GoogleDoc;
    }

    /**
     * @param GoogleDoc $source
     */
    public function importNewDocuments(Source $source, bool $syncAll): void
    {
        $resourceId = $this->extractDocumentId($source->getUrl());

        if ($this->isFolder($resourceId)) {
            $documentIds = $this->listDocumentsInFolder($resourceId);
            foreach ($documentIds as $documentId) {
                $this->importSingleDocument($source, $documentId);
            }
        } else {
            $this->importSingleDocument($source, $resourceId);
        }

        $this->entityManager->flush();
    }

    private function importSingleDocument(Source $source, string $documentId): void
    {
        $document = $this->entityManager->getRepository(Document::class)->findOneBy([
            'source' => $source,
            'externalId' => $documentId,
        ]);

        if (null === $document) {
            try {
                $googleDoc = $this->createDocApiClient()->documents->get($documentId);
            } catch (Exception) {
                return;
            }

            $content = '';
            if ($googleDoc->getBody() && $googleDoc->getBody()->getContent()) {
                $content = $this->extractPlainText($googleDoc->getBody()->getContent());
            }

            $webUrl = "https://docs.google.com/document/d/{$documentId}";

            $document = new Document()
                ->setSource($source)
                ->setExternalId($documentId)
                ->setTitle($googleDoc->getTitle())
                ->setContent(MarkdownCleaner::clean($content))
                ->setWebUrl($webUrl)
                ->setCreatedAt(new \DateTime())
                ->setSyncedAt(new \DateTime());
            $this->entityManager->persist($document);
        }
    }

    /**
     * @param GoogleDoc $source
     */
    public function updateDocuments(Source $source, bool $syncAll): void
    {
        $documents = $this->entityManager->getRepository(Document::class)->findToUpdate($source);
        foreach ($documents as $document) {
            $googleDoc = $this->createDocApiClient()->documents->get($document->getExternalId());

            $content = '';
            if ($googleDoc->getBody() && $googleDoc->getBody()->getContent()) {
                $content = $this->extractPlainText($googleDoc->getBody()->getContent());
            }

            $document
                ->setTitle($googleDoc->getTitle())
                ->setContent(MarkdownCleaner::clean($content))
                ->setSyncedAt(new \DateTime());
        }

        $this->entityManager->flush();
    }

    private function createDocApiClient(): Docs
    {
        $client = new Client();
        $client->setApplicationName('Thot');
        $client->setAuthConfig($this->credentialsPath);
        $client->setScopes([Docs::DOCUMENTS_READONLY, Drive::DRIVE_READONLY]);

        return new Docs($client);
    }

    private function createDriveApiClient(): Drive
    {
        $client = new Client();
        $client->setApplicationName('Thot');
        $client->setAuthConfig($this->credentialsPath);
        $client->setScopes([Drive::DRIVE_READONLY]);

        return new Drive($client);
    }

    private function isFolder(string $id): bool
    {
        try {
            $file = $this->createDriveApiClient()->files->get($id, ['fields' => 'mimeType']);

            return 'application/vnd.google-apps.folder' === $file->getMimeType();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @return string[] Array of Google Doc IDs
     */
    private function listDocumentsInFolder(string $folderId, string $path = ''): array
    {
        $documentIds = [];

        $query = \sprintf("'%s' in parents and trashed = false", $folderId);
        $pageToken = null;

        do {
            $parameters = [
                'q' => $query,
                'fields' => 'nextPageToken, files(id, name, mimeType)',
                'pageSize' => 100,
                'pageToken' => $pageToken,
            ];

            $results = $this->createDriveApiClient()->files->listFiles($parameters);

            foreach ($results->getFiles() as $file) {
                $currentPath = $path ? $path.'/'.$file->getName() : $file->getName();

                if ('application/vnd.google-apps.folder' === $file->getMimeType()) {
                    $subFiles = $this->listDocumentsInFolder($file->getId(), $currentPath);
                    $documentIds = array_merge($documentIds, $subFiles);
                } else {
                    $documentIds[] = $file->getId();
                }
            }

            $pageToken = $results->getNextPageToken();
        } while ($pageToken);

        return $documentIds;
    }

    private function extractDocumentId(string $url): string
    {
        $url = mb_trim($url);

        $patterns = [
            // Google Docs patterns
            '/\/document\/d\/([a-zA-Z0-9-_]+)\//',
            '/\/document\/d\/([a-zA-Z0-9-_]+)/',
            '/docs\.google\.com\/document\/d\/([a-zA-Z0-9-_]+)/',
            // Google Drive folders patterns
            '/\/folders\/([a-zA-Z0-9-_]+)/',
            '/drive\.google\.com\/drive\/folders\/([a-zA-Z0-9-_]+)/',
            // Generic /d/ pattern (works for both docs and drive)
            '/\/d\/([a-zA-Z0-9-_]+)\//',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        if (preg_match('/^[a-zA-Z0-9-_]+$/', $url) && mb_strlen($url) > 40) {
            return $url;
        }

        throw new \InvalidArgumentException('Invalid Google Doc or Drive URL: '.$url);
    }

    /**
     * @param Docs\StructuralElement[] $elements
     */
    private function extractPlainText(array $elements): string
    {
        $text = '';

        foreach ($elements as $element) {
            /** @var Docs\Paragraph|bool|null $paragraph */
            $paragraph = $element->getParagraph();

            /** @var Docs\Table|bool|null $table */
            $table = $element->getTable();

            /** @var Docs\Table|bool|null $tableOfContents */
            $tableOfContents = $element->getTableOfContents();

            if ($paragraph) {
                $text .= $this->extractParagraphPlainText($element->getParagraph());
            } elseif ($table) {
                $text .= $this->extractTablePlainText($element->getTable());
            } elseif ($tableOfContents) {
                $text .= $this->extractPlainText($element->getTableOfContents()->getContent());
            }
        }

        return $text;
    }

    private function extractParagraphPlainText(Docs\Paragraph $paragraph): string
    {
        $text = '';

        if ($paragraph->getElements()) {
            foreach ($paragraph->getElements() as $element) {
                /** @var Docs\TextRun|bool|null $textRun */
                $textRun = $element->getTextRun();

                if ($textRun) {
                    $text .= $element->getTextRun()->getContent();
                }
            }
        }

        return $text;
    }

    private function extractTablePlainText(Docs\Table $table): string
    {
        $text = '';

        if ($table->getTableRows()) {
            foreach ($table->getTableRows() as $row) {
                if ($row->getTableCells()) {
                    foreach ($row->getTableCells() as $cell) {
                        $text .= $this->extractPlainText($cell->getContent())."\t";
                    }
                    $text .= "\n";
                }
            }
        }

        return $text;
    }
}
