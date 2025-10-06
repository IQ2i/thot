<?php

namespace App\AI\Platform\Bridge\Ovh;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'gpt-oss-20b' => [
                'class' => Ovh::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-oss-120b' => [
                'class' => Ovh::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'bge-multilingual-gemma2' => [
                'class' => Embeddings::class,
                'capabilities' => [Capability::INPUT_MULTIPLE],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
