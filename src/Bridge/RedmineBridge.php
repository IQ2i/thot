<?php

namespace App\Bridge;

use App\Entity\Document;
use App\Entity\Redmine;
use App\Entity\Source;
use App\Service\MarkdownCleaner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;

readonly class RedmineBridge implements BridgeInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function supports(Source $source): bool
    {
        return $source instanceof Redmine;
    }

    /**
     * @param Redmine $source
     */
    public function importNewDocuments(Source $source, bool $syncAll): void
    {
        $page = 1;
        while ($issues = $this->request($source, page: $page, all: $syncAll)) {
            foreach ($issues as $issue) {
                $document = $this->entityManager->getRepository(Document::class)->findOneBy([
                    'source' => $source,
                    'externalId' => $issue['id'],
                ]);

                if (null === $document) {
                    $document = new Document()
                        ->setSource($source)
                        ->setExternalId($issue['id'])
                        ->setTitle($issue['subject'])
                        ->setContent(MarkdownCleaner::clean($issue['description'] ?? ''))
                        ->setWebUrl($this->getRedmineHost($source).'/issues/'.$issue['id'])
                        ->setCreatedAt(new \DateTime($issue['created_on']))
                        ->setUpdatedAt(isset($issue['updated_on']) ? new \DateTime($issue['updated_on']) : null);
                    $this->entityManager->persist($document);
                }
            }

            ++$page;
            $this->entityManager->flush();
        }
    }

    /**
     * @param Redmine $source
     */
    public function updateDocuments(Source $source, bool $syncAll): void
    {
        $documents = $this->entityManager->getRepository(Document::class)->findToUpdate($source);
        $ids = array_map(fn (Document $document): ?string => $document->getExternalId(), $documents);
        array_filter($ids);
        unset($documents);

        $page = 1;
        while ($issues = $this->request($source, page: $page, ids: $ids, all: $syncAll)) {
            foreach ($issues as $issue) {
                $document = $this->entityManager->getRepository(Document::class)->findOneBy([
                    'source' => $source,
                    'externalId' => $issue['id'],
                ]);

                $document
                    ->setTitle($issue['subject'])
                    ->setContent(MarkdownCleaner::clean($issue['description'] ?? ''))
                    ->setUpdatedAt(isset($issue['updated_on']) ? new \DateTime($issue['updated_on']) : null)
                    ->setClosed(isset($issue['closed_on']))
                    ->setSyncedAt(new \DateTime());
            }

            ++$page;
            $this->entityManager->flush();
        }
    }

    /**
     * @param Redmine       $source
     * @param array<string> $ids
     *
     * @return array<array<string, mixed>>
     */
    private function request(Source $source, int $page = 1, int $limit = 100, ?array $ids = null, bool $all = false): array
    {
        $client = HttpClient::create([
            'base_uri' => $this->getRedmineHost($source),
            'query' => [
                'project_id' => urlencode($this->getProjectId($source)),
                'include' => 'journals,attachments,relations,watchers',
            ],
            'headers' => ['Accept' => 'application/json', 'X-Redmine-API-Key' => $source->getAccessToken()],
        ]);

        $ids = array_chunk($ids ?? [], $limit);

        $query = [
            'limit' => $limit,
            'offset' => $limit * ($page - 1),
        ];

        if (true === $all) {
            $query['status_id'] = '*';
        }

        $issues = [];
        if (isset($ids[$page - 1])) {
            foreach ($ids[$page - 1] as $id) {
                $response = $client->request('GET', 'issues/'.$id.'.json', [
                    'query' => $query,
                ]);
                if (200 !== $response->getStatusCode()) {
                    continue;
                }

                $data = $response->toArray(false);
                $data = $data['issue'] ?? [];

                $description = $data['description'] ?? '';
                if (isset($data['author']['name'])) {
                    $description = "**Auteur:** {$data['author']['name']}\n\n{$description}";
                }

                $journalNotes = array_map(function (array $journal): ?string {
                    if (empty($journal['notes'])) {
                        return null;
                    }
                    $author = $journal['user']['name'] ?? 'Utilisateur inconnu';
                    $createdOn = isset($journal['created_on']) ? (new \DateTime($journal['created_on']))->format('Y-m-d H:i') : '';

                    return "\n\n---\n**Note de {$author}** ({$createdOn}):\n{$journal['notes']}";
                }, $data['journals'] ?? []);

                $description .= implode('', array_filter($journalNotes));

                $issues[] = [
                    'id' => $data['id'],
                    'subject' => $data['subject'],
                    'description' => $description,
                    'created_on' => $data['created_on'],
                    'updated_on' => $data['updated_on'] ?? null,
                    'closed_on' => $data['closed_on'] ?? null,
                ];
            }
        } else {
            $response = $client->request('GET', 'issues.json', [
                'query' => $query,
            ]);
            if (200 !== $response->getStatusCode()) {
                return [];
            }

            $data = $response->toArray(false);

            $issues = array_map(fn (array $data): array => [
                'id' => $data['id'],
                'subject' => $data['subject'],
                'description' => $data['description'],
                'created_on' => $data['created_on'],
                'updated_on' => $data['updated_on'] ?? null,
                'closed_on' => $data['closed_on'] ?? null,
            ], $data['issues'] ?? []);
        }

        return $issues;
    }

    /**
     * @param Redmine $source
     */
    private function getRedmineHost(Source $source): string
    {
        $params = parse_url($source->getProjectUrl());

        return $params['scheme'].'://'.$params['host'];
    }

    /**
     * @param Redmine $source
     */
    private function getProjectId(Source $source): string
    {
        $params = parse_url($source->getProjectUrl());
        $id = str_replace('/projects/', '', $params['path']);

        return mb_trim($id, '/');
    }
}
