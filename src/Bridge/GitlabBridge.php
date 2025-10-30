<?php

namespace App\Bridge;

use App\Entity\Document;
use App\Entity\Gitlab;
use App\Entity\Source;
use App\Service\MarkdownCleaner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class GitlabBridge implements BridgeInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function supports(Source $source): bool
    {
        return $source instanceof Gitlab;
    }

    /**
     * @param Gitlab $source
     */
    public function importNewDocuments(Source $source, bool $syncAll): void
    {
        $page = 1;
        $query = [
            'scope' => 'all',
            'per_page' => 100,
            'page' => $page,
        ];
        if (false === $syncAll) {
            $query['state'] = 'opened';
        }

        while ($issues = $this->request($source, 'issues', $query)) {
            foreach ($issues as $issue) {
                $document = $this->entityManager->getRepository(Document::class)->findOneBy([
                    'source' => $source,
                    'externalId' => $issue['iid'],
                ]);

                if (null === $document) {
                    $content = $this->buildIssueContent($source, $issue);
                    $document = new Document()
                        ->setSource($source)
                        ->setExternalId($issue['iid'])
                        ->setTitle($issue['title'])
                        ->setContent(MarkdownCleaner::clean($content))
                        ->setWebUrl($issue['web_url'])
                        ->setCreatedAt(new \DateTime($issue['created_at']))
                        ->setClosed('closed' === $issue['state'])
                        ->setSyncedAt(new \DateTime());
                    $this->entityManager->persist($document);
                }
            }

            $query['page'] = ++$page;
            $this->entityManager->flush();
        }

        $wikis = $this->request($source, 'wikis');
        foreach ($wikis as $wiki) {
            $slug = $wiki['slug'];
            $document = $this->entityManager->getRepository(Document::class)->findOneBy([
                'source' => $source,
                'externalId' => $slug,
            ]);

            if (null === $document) {
                $wikiPage = $this->request($source, 'wikis/'.urlencode($slug));
                if (empty($wikiPage)) {
                    continue;
                }

                $document = new Document()
                    ->setSource($source)
                    ->setExternalId($slug)
                    ->setTitle($wikiPage['title'] ?? $wiki['title'] ?? $slug)
                    ->setContent(MarkdownCleaner::clean($wikiPage['content'] ?? ''))
                    ->setWebUrl($this->buildWikiWebUrl($source, $slug))
                    ->setClosed(false)
                    ->setSyncedAt(new \DateTime());
                $this->entityManager->persist($document);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * @param Gitlab $source
     */
    public function updateDocuments(Source $source, bool $syncAll): void
    {
        $documents = $this->entityManager->getRepository(Document::class)->findToUpdate($source);
        $ids = array_filter(array_map(fn (Document $document): ?string => $document->getExternalId(), $documents));
        $ids = array_chunk($ids, 100);
        unset($documents);

        $page = 1;
        $query = [
            'scope' => 'all',
            'per_page' => 100,
            'page' => $page,
        ];
        if (false === $syncAll) {
            $query['state'] = 'opened';
        }
        if (isset($ids[$page - 1])) {
            $query['ids'] = $ids[$page - 1];
        }

        while ($issues = $this->request($source, 'issues', $query)) {
            foreach ($issues as $issue) {
                $document = $this->entityManager->getRepository(Document::class)->findOneBy([
                    'source' => $source,
                    'externalId' => $issue['iid'],
                ]);

                $content = $this->buildIssueContent($source, $issue);
                $document
                    ->setTitle($issue['title'])
                    ->setContent(MarkdownCleaner::clean($content))
                    ->setClosed('closed' === $issue['state'])
                    ->setSyncedAt(new \DateTime());
            }

            $query['page'] = ++$page;
            if (isset($ids[$page - 1])) {
                $query['ids'] = $ids[$page - 1];
            }

            $this->entityManager->flush();
        }

        $wikis = $this->request($source, 'wikis');
        foreach ($wikis as $wiki) {
            $slug = $wiki['slug'];
            $wikiPage = $this->request($source, 'wikis/'.urlencode($slug));
            if (empty($wikiPage)) {
                continue;
            }

            $document = $this->entityManager->getRepository(Document::class)->findOneBy([
                'source' => $source,
                'externalId' => $slug,
            ]);
            if (null === $document) {
                continue;
            }

            $document
                ->setTitle($wikiPage['title'] ?? $wiki['title'] ?? $slug)
                ->setContent(MarkdownCleaner::clean($wikiPage['content'] ?? ''))
                ->setWebUrl($this->buildWikiWebUrl($source, $slug))
                ->setClosed(false)
                ->setSyncedAt(new \DateTime());
        }
        $this->entityManager->flush();
    }

    /**
     * @param Gitlab $source
     */
    private function createApiClient(Source $source): HttpClientInterface
    {
        return HttpClient::create([
            'base_uri' => $this->getGitlabHost($source).'/api/v4/projects/'.urlencode($this->getProjectId($source)).'/',
            'auth_bearer' => $source->getAccessToken(),
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    /**
     * @param Gitlab               $source
     * @param array<string, mixed> $query
     *
     * @return array<array<string, mixed>>
     */
    private function request(Source $source, string $url, array $query = []): array
    {
        $client = $this->createApiClient($source);

        $response = $client->request('GET', $url, [
            'query' => $query,
        ]);
        if (200 !== $response->getStatusCode()) {
            return [];
        }

        return $response->toArray(false);
    }

    /**
     * @param Gitlab               $source
     * @param array<string, mixed> $issue
     */
    private function buildIssueContent(Source $source, array $issue): string
    {
        $description = $issue['description'] ?? '';

        if (isset($issue['author']['name'])) {
            $description = "**Auteur:** {$issue['author']['name']}\n\n{$description}";
        }

        $notes = $this->request($source, "issues/{$issue['iid']}/notes", ['sort' => 'asc', 'per_page' => 100]);

        if (!empty($notes)) {
            $notesContent = array_map(function (array $note): ?string {
                // Ignorer les notes système
                if (isset($note['system']) && true === $note['system']) {
                    return null;
                }

                if (empty($note['body'])) {
                    return null;
                }

                $author = $note['author']['name'] ?? 'Utilisateur inconnu';
                $createdAt = isset($note['created_at']) ? (new \DateTime($note['created_at']))->format('Y-m-d H:i') : '';

                return "\n\n---\n**Note de {$author}** ({$createdAt}):\n{$note['body']}";
            }, $notes);

            $description .= implode('', array_filter($notesContent));
        }

        return $description;
    }

    /**
     * @param Gitlab $source
     */
    private function buildWikiWebUrl(Source $source, string $slug): string
    {
        return mb_rtrim($this->getGitlabHost($source), '/').'/'.mb_trim($this->getProjectId($source), '/').'/-/wikis/'.rawurlencode($slug);
    }

    /**
     * @param Gitlab $source
     */
    private function getGitlabHost(Source $source): string
    {
        $params = parse_url($source->getProjectUrl());

        return $params['scheme'].'://'.$params['host'];
    }

    /**
     * @param Gitlab $source
     */
    private function getProjectId(Source $source): string
    {
        $params = parse_url($source->getProjectUrl());

        return mb_trim($params['path'], '/');
    }
}
