<?php

namespace App\AI\Platform\Bridge\Ovh\Embeddings;

use App\AI\Platform\Bridge\Ovh\Embeddings;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ModelClient implements ModelClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Embeddings;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $url = \sprintf('https://%s.endpoints.kepler.ai.cloud.ovh.net/api/batch_text2vec', $model->getName());

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'auth_bearer' => $this->apiKey,
            'json' => \is_array($payload) ? $payload : [$payload],
        ]));
    }
}
