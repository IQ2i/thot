<?php

namespace App\AI\Agent\InputProcessor;

use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Platform\Message\Message;

class TitlePromptInputProcessor implements InputProcessorInterface
{
    public function processInput(Input $input): void
    {
        $prompt = <<<PROMPT
            You are Thot, an assistant for project documentation and your task is to generate a short, descriptive title for this conversation based **strictly** on the user's question.

            # Core rules
            - Generate a title that directly reflects the user's question.
            - The title must be **concise** (3-8 words maximum).
            - The title must be **descriptive** and capture the main topic of the question.
            - Use **plain text only** - no markdown, no quotes, no special formatting.
            - The title must be in the **same language** as the user's question.
            - Do NOT invent information or add context that is not in the question.
            - Do NOT use generic titles like "New conversation" or "User question".

            # Examples
            User question: "What are the deployment steps for production?"
            Title: Production deployment steps

            User question: "How to setup the development environment?"
            Title: Development environment setup

        PROMPT;

        $messages = $input->getMessageBag();
        $input->setMessageBag($messages->prepend(Message::forSystem($prompt)));
    }
}
