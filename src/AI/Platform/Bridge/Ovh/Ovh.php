<?php

namespace App\AI\Platform\Bridge\Ovh;

use Symfony\AI\Platform\Model;

class Ovh extends Model
{
    /**
     * @param array<mixed> $options The default options for the model usage
     */
    public function __construct(string $name, array $capabilities = [], array $options = [])
    {
        parent::__construct($name, $capabilities, $options);
    }
}
