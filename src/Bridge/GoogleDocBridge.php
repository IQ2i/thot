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
                // Request document with tabs content included
                $googleDoc = $this->createDocApiClient()->documents->get($documentId, [
                    'includeTabsContent' => true,
                ]);
            } catch (Exception) {
                return;
            }

            $content = '';

            // Extract content from main body
            if ($googleDoc->getBody() && $googleDoc->getBody()->getContent()) {
                $content = $this->extractPlainText($googleDoc->getBody()->getContent());
            }

            // Extract content from tabs if any
            $tabs = $googleDoc->getTabs();
            if ($tabs) {
                $content .= $this->extractTabsContent($tabs);
            }

            // Extract footnotes if any
            $footnotes = $googleDoc->getFootnotes();
            if ($footnotes) {
                $content .= $this->extractFootnotes($footnotes);
            }

            $webUrl = "https://docs.google.com/document/d/{$documentId}";
            $metadata = $this->getFileMetadata($documentId);

            $document = new Document()
                ->setSource($source)
                ->setExternalId($documentId)
                ->setTitle($googleDoc->getTitle())
                ->setContent(MarkdownCleaner::clean($content))
                ->setWebUrl($webUrl)
                ->setCreatedAt($metadata['createdTime'] ?? new \DateTime())
                ->setUpdatedAt($metadata['modifiedTime'])
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
            // Request document with tabs content included
            $googleDoc = $this->createDocApiClient()->documents->get($document->getExternalId(), [
                'includeTabsContent' => true,
            ]);

            $content = '';

            // Extract content from main body
            if ($googleDoc->getBody() && $googleDoc->getBody()->getContent()) {
                $content = $this->extractPlainText($googleDoc->getBody()->getContent());
            }

            // Extract content from tabs if any
            $tabs = $googleDoc->getTabs();
            if ($tabs) {
                $content .= $this->extractTabsContent($tabs);
            }

            // Extract footnotes if any
            $footnotes = $googleDoc->getFootnotes();
            if ($footnotes) {
                $content .= $this->extractFootnotes($footnotes);
            }

            $metadata = $this->getFileMetadata($document->getExternalId());

            $document
                ->setTitle($googleDoc->getTitle())
                ->setContent(MarkdownCleaner::clean($content))
                ->setUpdatedAt($metadata['modifiedTime'])
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
     * @return array{createdTime: ?\DateTime, modifiedTime: ?\DateTime}
     */
    private function getFileMetadata(string $id): array
    {
        try {
            $file = $this->createDriveApiClient()->files->get($id, ['fields' => 'createdTime,modifiedTime']);

            $createdTime = $file->getCreatedTime();
            $modifiedTime = $file->getModifiedTime();

            return [
                'createdTime' => $createdTime ? new \DateTime($createdTime) : null,
                'modifiedTime' => $modifiedTime ? new \DateTime($modifiedTime) : null,
            ];
        } catch (\Exception) {
            return [
                'createdTime' => null,
                'modifiedTime' => null,
            ];
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

        // Handle bullet points
        $bullet = $paragraph->getBullet();

        // Get nesting level for indentation
        $nestingLevel = $bullet->getNestingLevel();
        $indent = str_repeat('  ', $nestingLevel);

        // Determine bullet style
        $listId = $bullet->getListId();
        if ($listId) {
            $text .= $indent.'• ';
        }

        if ($paragraph->getElements()) {
            foreach ($paragraph->getElements() as $element) {
                /** @var Docs\TextRun|bool|null $textRun */
                $textRun = $element->getTextRun();

                /** @var Docs\InlineObjectElement|bool|null $inlineObject */
                $inlineObject = $element->getInlineObjectElement();

                /** @var Docs\FootnoteReference|bool|null $footnoteRef */
                $footnoteRef = $element->getFootnoteReference();

                /** @var Docs\Equation|bool|null $equation */
                $equation = $element->getEquation();

                /** @var Docs\HorizontalRule|bool|null $horizontalRule */
                $horizontalRule = $element->getHorizontalRule();

                if ($textRun) {
                    $text .= $element->getTextRun()->getContent();
                } elseif ($footnoteRef) {
                    // Extract footnote content if available
                    $footnoteId = $footnoteRef->getFootnoteId();
                    $text .= " [footnote: $footnoteId]";
                } elseif ($equation) {
                    // For equations, we can't get the LaTeX but we note their presence
                    $text .= ' [equation] ';
                } elseif ($inlineObject) {
                    // Note inline objects like images
                    $text .= ' [inline object] ';
                } elseif ($horizontalRule) {
                    $text .= "\n---\n";
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

    /**
     * @param Docs\Tab[] $tabs
     */
    private function extractTabsContent(array $tabs, int $level = 0): string
    {
        if (empty($tabs)) {
            return '';
        }

        $text = '';

        foreach ($tabs as $tab) {
            // Get tab properties
            $tabProperties = $tab->getTabProperties();
            $tabTitle = $tabProperties->getTitle();

            // Add tab separator with indentation based on level
            $indent = str_repeat('#', $level + 2);
            $text .= "\n\n$indent Tab: $tabTitle\n\n";

            // Extract tab content if it's a document tab
            $documentTab = $tab->getDocumentTab();
            if ($documentTab->getBody()->getContent()) {
                $text .= $this->extractPlainText($documentTab->getBody()->getContent());
            }

            // Recursively extract child tabs
            $childTabs = $tab->getChildTabs();
            if ($childTabs) {
                $text .= $this->extractTabsContent($childTabs, $level + 1);
            }
        }

        return $text;
    }

    /**
     * @param array<string, Docs\Footnote> $footnotes
     */
    private function extractFootnotes(array $footnotes): string
    {
        if (empty($footnotes)) {
            return '';
        }

        $text = "\n\n--- Footnotes ---\n";

        foreach ($footnotes as $footnoteId => $footnote) {
            $text .= "\n[$footnoteId]: ";
            if ($footnote->getContent()) {
                $footnoteContent = $this->extractPlainText($footnote->getContent());
                $text .= mb_trim($footnoteContent);
            }
        }

        return $text;
    }
}
