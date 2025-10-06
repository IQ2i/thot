<?php

namespace App\AI\Platform\Bridge\Ovh\Llm;

use App\AI\Platform\Bridge\Ovh\Ovh;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ModelClient implements ModelClientInterface
{
    private EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Ovh;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', 'https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/chat/completions', [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => array_merge($options, $payload),
        ]));
    }
}
