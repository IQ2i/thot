<?php

namespace App\AI\Store\Bridge\Meilisearch;

use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class Store implements ManagedStoreInterface, StoreInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $endpointUrl,
        #[\SensitiveParameter] private string $apiKey,
        private string $indexName,
        private string $embedder = 'default',
        private string $vectorFieldName = '_vectors',
        private int $embeddingsDimension = 1536,
    ) {
    }

    public function setup(array $options = []): void
    {
        $this->request('POST', 'indexes', [
            'uid' => $this->indexName,
            'primaryKey' => 'id',
        ]);

        $this->request('PATCH', \sprintf('indexes/%s/settings', $this->indexName), [
            'filterableAttributes' => $options['filterable_attributes'] ?? [],
            'sortableAttributes' => $options['sortableAttributes'] ?? [],
            'searchableAttributes' => $options['searchableAttributes'] ?? ['*'],
            'embedders' => [
                $this->embedder => [
                    'source' => 'userProvided',
                    'dimensions' => $this->embeddingsDimension,
                ],
            ],
        ]);
    }

    public function add(VectorDocument ...$documents): void
    {
        $this->request('POST', \sprintf('indexes/%s/documents/delete', $this->indexName), [
            'filter' => implode(' AND ', array_map($this->generateFilterByParentId(...), $documents)),
        ]);

        $this->request('PUT', \sprintf('indexes/%s/documents', $this->indexName), array_map(
            $this->convertToIndexableArray(...), $documents)
        );
    }

    public function query(Vector $vector, array $options = []): array
    {
        $params = [
            'q' => $options['query'],
            'limit' => $options['limit'] ?? 3,
            'vector' => $vector->getData(),
            'showRankingScore' => true,
            'retrieveVectors' => true,
            'hybrid' => [
                'embedder' => $this->embedder,
                'semanticRatio' => $options['semanticRatio'] ?? 0.5,
            ],
        ];

        if (\array_key_exists('filter', $options)) {
            $params['filter'] = $options['filter'];
        }

        if (\array_key_exists('sort', $options)) {
            $params['sort'] = $options['sort'];
        }

        $result = $this->request('POST', \sprintf('indexes/%s/search', $this->indexName), $params);

        return array_map($this->convertToVectorDocument(...), $result['hits']);
    }

    public function drop(): void
    {
        $this->request('DELETE', \sprintf('indexes/%s', $this->indexName), []);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $payload): array
    {
        $url = \sprintf('%s/%s', $this->endpointUrl, $endpoint);
        $result = $this->httpClient->request($method, $url, [
            'headers' => [
                'Authorization' => \sprintf('Bearer %s', $this->apiKey),
            ],
            'json' => $payload,
        ]);

        return $result->toArray();
    }

    private function generateFilterByParentId(VectorDocument $document): string
    {
        $data = $document->metadata->getArrayCopy();

        return 'parent_id = '.$data['parent_id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function convertToIndexableArray(VectorDocument $document): array
    {
        return array_merge([
            'id' => $document->id->toRfc4122(),
            $this->vectorFieldName => [
                $this->embedder => [
                    'embeddings' => $document->vector->getData(),
                    'regenerate' => false,
                ],
            ],
        ], $document->metadata->getArrayCopy());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function convertToVectorDocument(array $data): VectorDocument
    {
        $id = $data['id'] ?? throw new InvalidArgumentException('Missing "id" field in the document data.');
        $vector = !\array_key_exists($this->vectorFieldName, $data) || null === $data[$this->vectorFieldName]
            ? new NullVector()
            : new Vector($data[$this->vectorFieldName][$this->embedder]['embeddings']);

        $score = $data['_rankingScore'] ?? null;

        unset($data['id'], $data[$this->vectorFieldName], $data['_rankingScore']);

        return new VectorDocument(Uuid::fromString($id), $vector, new Metadata($data), $score);
    }
}
