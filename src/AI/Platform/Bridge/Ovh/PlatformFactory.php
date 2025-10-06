<?php

namespace App\AI\Platform\Bridge\Ovh;

use Symfony\AI\Platform\Bridge\OpenAi\Contract\OpenAiContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class PlatformFactory
{
    public static function create(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Platform(
            [
                new Llm\ModelClient($httpClient, $apiKey),
                new Embeddings\ModelClient($httpClient, $apiKey),
            ],
            [
                new Llm\ResultConverter(),
                new Embeddings\ResultConverter(),
            ],
            $modelCatalog,
            $contract ?? OpenAiContract::create(),
        );
    }
}
