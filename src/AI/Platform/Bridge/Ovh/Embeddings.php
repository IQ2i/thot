<?php

namespace App\AI\Platform\Bridge\Ovh;

use Symfony\AI\Platform\Model;

final class Embeddings extends Model
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(string $name, array $capabilities = [], array $options = [])
    {
        parent::__construct($name, $capabilities, $options);
    }
}
