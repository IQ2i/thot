<?php

namespace App\AI\Agent\Toolbox\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesInterface;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesTrait;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\StoreInterface;

#[AsTool('similarity_search', description: 'The similarity_search function is used to search for similar documents in the database.')]
class SimilaritySearch implements HasSourcesInterface
{
    use HasSourcesTrait;

    public function __construct(
        private readonly VectorizerInterface $vectorizer,
        private readonly StoreInterface $store,
    ) {
    }

    /**
     * @param string    $query   string used for similarity search
     * @param string    $project ID of project to search in
     * @param bool|null $closed  boolean used for filtering documents (true: opened tickets, false: closed tickets, empty: all tickets)
     * @param bool|null $sort    boolean used for sorting documents (true: sort more recent tickets first, false: sort older tickets first, empty: no sorting)
     * @param int       $limit   maximum number of documents returned (default to 3)
     */
    public function __invoke(string $query, string $project, ?bool $closed = null, ?bool $sort = null, int $limit = 3): string
    {
        $filter = 'project = '.$project;
        if (null !== $closed) {
            $filter .= ' AND closed = '.$closed;
        }

        $options = [
            'query' => $query,
            'limit' => $limit,
            'filter' => [$filter],
        ];

        if (null !== $sort) {
            $options['sort'] = ['createdAt:'.($sort ? 'desc' : 'asc')];
        }

        $vector = $this->vectorizer->vectorize($query);
        $documents = $this->store->query($vector, $options);

        if ([] === $documents) {
            return 'No results found';
        }

        $documentUrls = [];
        $result = 'Found documents with following information:'.\PHP_EOL;
        foreach ($documents as $document) {
            $data = $document->metadata->getArrayCopy();
            if (\in_array($data['web_url'], $documentUrls, true)) {
                continue;
            }

            $this->addSource(new Source($data['title'], $data['web_url'], $data['title']));

            $documentUrls[] = $data['web_url'];
            $result .= json_encode($data);
        }

        return $result;
    }
}
