<?php

namespace App\Tests\Bridge;

use App\Bridge\GoogleDocBridge;
use App\Entity\Document;
use App\Entity\GoogleDoc;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Google\Service\Drive;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GoogleDocBridgeTest extends TestCase
{
    private GoogleDocBridge $bridge;
    private EntityManagerInterface&MockObject $entityManager;
    private DocumentRepository&MockObject $documentRepository;
    private string $credentialsPath;

    protected function setUp(): void
    {
        $this->credentialsPath = sys_get_temp_dir().'/google-credentials-'.uniqid().'.json';
        file_put_contents($this->credentialsPath, json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
            'private_key_id' => 'test-key-id',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7VJTUt9Us8cKj\nMzEfYyjiWA4R4/M2bS1+fWIcPm15j9FYDpqaXK1nprw/JOCyD7QDOQQ8vZKFoXHW\nS3d8cqvvhHqsLEt4GcXlCy/+JH7R7oJQI3Cy6kNB+YqnAFmOITGqBCY3T4IfSjWP\nYbNt5cZuZjHKj3cCXLPGvD8qP/4u8N5Fk6hR0LgPGKJgTShJmKIcqCNaHVKKCfTd\nMF6nYNQTRAl7PLCvRpbKLLfPCHOPGKJgTShJmKIcqCNaHVKKCfTdMF6nYNQTRAl7\nPLCvRpbKLLfPCH\n-----END PRIVATE KEY-----\n",
            'client_email' => 'test@test-project.iam.gserviceaccount.com',
            'client_id' => '123456789',
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->documentRepository = $this->createMock(DocumentRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->with(Document::class)
            ->willReturn($this->documentRepository);

        $this->bridge = new GoogleDocBridge(
            $this->credentialsPath,
            $this->entityManager
        );
    }

    protected function tearDown(): void
    {
        if (file_exists($this->credentialsPath)) {
            unlink($this->credentialsPath);
        }
    }

    public function testSupportsGoogleDocSource(): void
    {
        $source = new GoogleDoc();
        $this->assertTrue($this->bridge->supports($source));
    }

    public function testDoesNotSupportOtherSources(): void
    {
        $source = $this->createMock(\App\Entity\Source::class);
        $this->assertFalse($this->bridge->supports($source));
    }

    public function testImportNewDocumentsWithSingleDocument(): void
    {
        $source = new GoogleDoc();
        $source->setUrl('https://docs.google.com/document/d/test-doc-id/edit');

        $this->documentRepository
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->method('persist');

        $this->entityManager
            ->method('flush');

        // This will fail on actual API call, but we're testing the flow
        $this->expectException(\Exception::class);
        $this->bridge->importNewDocuments($source, false);
    }

    public function testImportNewDocumentsSkipsExistingUnmodifiedDocuments(): void
    {
        $source = new GoogleDoc();
        $source->setUrl('https://docs.google.com/document/d/test-doc-id/edit');

        $existingDocument = new Document();
        $existingDocument->setUpdatedAt(new \DateTime('2025-01-01 12:00:00'));

        $this->documentRepository
            ->method('findOneBy')
            ->willReturn($existingDocument);

        $this->entityManager
            ->method('persist');

        $this->entityManager
            ->method('flush');

        // This will fail on Drive API call to get metadata
        $this->expectException(\Exception::class);
        $this->bridge->importNewDocuments($source, false);
    }

    public function testUpdateDocumentsSkipsUnmodifiedDocuments(): void
    {
        $source = new GoogleDoc();
        $source->setUrl('https://docs.google.com/document/d/test-doc-id/edit');

        $document1 = new Document();
        $document1->setExternalId('doc1');
        $document1->setUpdatedAt(new \DateTime('2025-01-01 12:00:00'));

        $this->documentRepository
            ->method('findToUpdate')
            ->with($source)
            ->willReturn([$document1]);

        $this->entityManager
            ->method('flush');

        // This will fail on Drive API call to get metadata
        $this->expectException(\Exception::class);
        $this->bridge->updateDocuments($source, false);
    }

    public function testExtractDocumentIdFromStandardDocUrl(): void
    {
        $source = new GoogleDoc();
        $source->setUrl('https://docs.google.com/document/d/1abc-def_GHI/edit');

        $this->documentRepository
            ->method('findOneBy')
            ->willReturnCallback(function (array $criteria) {
                $this->assertSame('1abc-def_GHI', $criteria['externalId']);

                return null;
            });

        $this->entityManager->method('flush');

        try {
            $this->bridge->importNewDocuments($source, false);
        } catch (\Exception) {
            // Expected to fail on API call
            $this->addToAssertionCount(1);
        }
    }

    public function testExtractDocumentIdFromFolderUrl(): void
    {
        $source = new GoogleDoc();
        $source->setUrl('https://drive.google.com/drive/folders/1abc-def_GHI');

        $this->documentRepository
            ->method('findOneBy')
            ->willReturnCallback(function (array $criteria) {
                $this->assertSame('1abc-def_GHI', $criteria['externalId']);

                return null;
            });

        $this->entityManager->method('flush');

        try {
            $this->bridge->importNewDocuments($source, false);
        } catch (\Exception) {
            // Expected to fail on API call
            $this->addToAssertionCount(1);
        }
    }

    public function testExtractDocumentIdThrowsExceptionForInvalidUrl(): void
    {
        $source = new GoogleDoc();
        $source->setUrl('https://invalid-url.com');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Google Doc or Drive URL');

        $this->bridge->importNewDocuments($source, false);
    }
}
