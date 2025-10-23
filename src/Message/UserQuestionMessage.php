<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final class UserQuestionMessage
{
    public function __construct(
        public string $locale,
        public string $question,
        public int $messageId,
        public bool $firstMessage = false,
    ) {
    }
}
