<?php

namespace App\AI\Platform\Bridge\Ovh\Embeddings;

use App\AI\Platform\Bridge\Ovh\Embeddings;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Vector\Vector;

final readonly class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Embeddings;
    }

    public function convert(RawResultInterface $result, array $options = []): VectorResult
    {
        $httpResponse = $result->getObject();

        if (200 !== $httpResponse->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $httpResponse->getStatusCode(), $httpResponse->getContent(false)));
        }

        $data = $result->getData();

        if (empty($data)) {
            throw new RuntimeException('Response does not contain data.');
        }

        return new VectorResult(
            ...array_map(
                static fn (array $item): Vector => new Vector($item),
                $data,
            ),
        );
    }
}
