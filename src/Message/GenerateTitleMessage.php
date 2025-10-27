<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final class GenerateTitleMessage
{
    public function __construct(
        public string $locale,
        public string $question,
        public int $conversationId,
    ) {
    }
}
