<?php

namespace App\AI\Agent\Toolbox\Tool;

use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\StoreInterface;

#[AsTool('similarity_search', description: 'The similarity_search function is used to search for similar documents in the database.')]
readonly class SimilaritySearch
{
    public function __construct(
        private VectorizerInterface $vectorizer,
        private StoreInterface $store,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param string    $query   string used for similarity search
     * @param string    $project ID of project to search in
     * @param bool|null $closed  boolean used for filtering documents (true: opened tickets, false: closed tickets, empty: all tickets)
     * @param bool|null $sort    boolean used for sorting documents (true: sort more recent tickets first, false: sort older tickets first, empty: no sorting)
     */
    public function __invoke(string $query, string $project, ?bool $closed = null, ?bool $sort = null): string
    {
        $this->logger->info('"similarity_search" function called', ['query' => $query, 'project' => $project, 'closed' => $closed, 'sort' => $sort]);

        $filter = 'project = '.$project;
        if (null !== $closed) {
            $filter .= ' AND closed = '.$closed;
        }

        $options = [
            'query' => $query,
            'filter' => [$filter],
        ];

        if (null !== $sort) {
            $options['sort'] = ['createdAt:'.($sort ? 'desc' : 'asc')];
        }

        $vector = $this->vectorizer->vectorize($query);
        $documents = $this->store->query($vector, $options);

        if ([] === $documents) {
            $this->logger->info('"similarity_search" function result', ['result' => 'No results found']);

            return 'No results found';
        }

        $result = 'Found documents with following information:'.\PHP_EOL;
        foreach ($documents as $document) {
            $result .= json_encode($document->metadata->getArrayCopy());
        }

        $this->logger->info('"similarity_search" function result', ['result' => $result]);

        return $result;
    }
}
