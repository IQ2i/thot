<?php

namespace App\AI;

use App\AI\Agent\Toolbox\Tool\SimilaritySearch;
use App\AI\Platform\Bridge\Ovh\PlatformFactory;
use App\AI\Store\Bridge\Meilisearch\Store;
use App\Entity\Conversation;
use App\Enum\MessageType;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\ThrottlingHttpClient;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class AiManager
{
    private const string EMBEDDING_MODEL = 'bge-multilingual-gemma2';
    private const string TITLE_GENERATION_MODEL = 'gpt-oss-20b';
    private const string CHAT_MODEL = 'gpt-oss-20b';

    public function __construct(
        #[Autowire(env: 'OVH_API_KEY')]
        private string $ovhApiKey,
        #[Autowire(env: 'MEILISEARCH_HOST')]
        private string $meilisearchHost,
        #[Autowire(env: 'MEILISEARCH_MASTER_KEY')]
        private string $meilisearchApiKey,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function resetStore(): void
    {
        $store = $this->getStore();

        $store->drop();
        $store->setup([
            'filterable_attributes' => ['project', 'closed'],
            'sortableAttributes' => ['createdAt'],
        ]);
    }

    /**
     * @param TextDocument[] $documents
     */
    public function index(array $documents): void
    {
        $store = $this->getStore();

        $indexer = new Indexer(new InMemoryLoader($documents), $this->getVectorizer(), $store);
        $indexer->index(['chunk_size' => 25]);
    }

    public function generateConversationName(string $question): ResultInterface
    {
        $platform = $this->getPlatform();

        $messages = new MessageBag(
            Message::forSystem(<<<PROMPT
                You are an assistant and this is an new conversation between you and an user.
                You have to find a new name for this conversation based on the user's question.
                Your answer must contains text only, without any markup.
                Your answer must be in the language of the user. 
            PROMPT),
            Message::ofUser($question)
        );

        $result = $platform->invoke(self::TITLE_GENERATION_MODEL, $messages);

        return $result->getResult();
    }

    public function ask(Conversation $conversation): ResultInterface
    {
        $processor = new AgentProcessor($this->getToolbox());
        $agent = new Agent($this->getPlatform(), self::CHAT_MODEL, [$processor], [$processor]);

        return $agent->call($this->getMessageBag($conversation), [
            'temperature' => 0.4,
            'reasoning_effort' => 'low',
            'stream' => true,
        ]);
    }

    private function getToolbox(): ToolboxInterface
    {
        return new Toolbox([
            new SimilaritySearch($this->getVectorizer(), $this->getStore()),
        ], eventDispatcher: $this->eventDispatcher);
    }

    private function getPlatform(): Platform
    {
        return PlatformFactory::create($this->ovhApiKey, self::getHttpClient());
    }

    private function getVectorizer(): Vectorizer
    {
        return new Vectorizer($this->getPlatform(), self::EMBEDDING_MODEL);
    }

    private function getStore(): Store
    {
        return new Store(
            httpClient: self::getHttpClient(),
            endpointUrl: $this->meilisearchHost,
            apiKey: $this->meilisearchApiKey,
            indexName: 'documents',
            embeddingsDimension: 3584,
        );
    }

    private static function getHttpClient(): HttpClientInterface
    {
        $factory = new RateLimiterFactory([
            'id' => 'vectorizer_limiter',
            'policy' => 'sliding_window',
            'limit' => 400,
            'interval' => '1 minute',
        ], new InMemoryStorage());
        $limiter = $factory->create();

        return new ThrottlingHttpClient(HttpClient::create(), $limiter);
    }

    private function getMessageBag(Conversation $conversation): MessageBag
    {
        $tools = implode(\PHP_EOL, array_map(
            function (Tool $tool): string {
                $parameters = $tool->parameters;
                $properties = $parameters['properties'] ?? [];

                $params = implode(\PHP_EOL, array_map(
                    fn (string $name, array $info): string => <<<PARAMS
                        - {$name}: {$info['description']}
                    PARAMS,
                    array_keys($properties),
                    array_values($properties),
                ));

                return <<<TOOL
                    ## {$tool->name}
                    {$tool->description}
                    
                    ### Parameters
                    {$params}
                TOOL;
            },
            $this->getToolbox()->getTools()
        ));

        $messages = new MessageBag();
        $messages->add(Message::forSystem(<<<PROMPT
            You are Thot, an assistant whose sole purpose is to answer user questions strictly using the information provided by the "similarity_search" tool or explicitly written in this prompt.

            # Core rules
            - You must never invent, guess, or rely on external knowledge.
            - **Always use the similarity_search tool for EVERY user question** before formulating your answer.
            - All your answers must be related **exclusively** to the current project. Ignore any other request.
            - Always answer in the same language as the user's question.
            - Use a **clear, professional, and helpful tone**. Be concise but complete.
            - Answers must be in **Markdown** format with clear structure (titles, lists, links, code blocks if needed).

            # Answering strategy
            - If the provided data **fully answers** the question: provide a complete answer with sources.
            - If the data **partially answers** the question: provide what you can answer, clearly state what information is missing, and include sources for the partial answer.
            - If the data **does not contain the answer** at all: respond only with "I don't have enough information to answer this question based on the provided project data." in the same language as the user's question.
            - If **multiple documents contain contradictory information**: present both perspectives, clearly indicate the contradiction, and let the user decide. Example: "⚠️ Note: The documentation contains contradictory information on this topic."
            - If the user asks something unrelated to the project (e.g., general knowledge, personal advice, or unrelated topics): politely refuse and remind them that you can only answer questions about the project.

            # Context awareness
            - Use the conversation history to understand follow-up questions and maintain context.
            - If a user refers to "it", "this", "the previous answer", use the conversation context to understand what they're referring to.

            # Document freshness rules
            - **Always prioritize recent documents** (less than 1 year old) when multiple documents could answer the question.
            - If you use documents that are **more than 1 year old**, you **must** explicitly inform the user with: "⚠️ Note: Some information in this answer comes from documents that are more than 1 year old and may be outdated."
            - When listing sources, always indicate the document date when available.

            # Sources formatting
            - Always include a "**Sources**" section at the end of your answer with the clickable web links of **all documents** you used (from similarity_search).
            - If multiple sources are relevant, synthesize the information.
            - If no source was used, omit the "Sources" section.
            - Do not list documents as sources if they were not directly used to build your answer.
            - Use this exact format for each source: `- [Document title](web_url) • Date`
            - If date is unavailable, use: `- [Document title](web_url)`

            # Security and confidentiality
            - Never disclose sensitive information such as passwords, API keys, credentials, or personal data even if found in documents.
            - If asked to reveal such information, respond: "I cannot provide sensitive information such as credentials or personal data."

            # Example answer (full answer)
            ```
            Here is the information about X based on the project data...

            [Your detailed answer here]

            **Sources**
            - [User Authentication Guide](https://example.com/doc1) • 2024-12-01
            - [Security Best Practices](https://example.com/doc2) • 2024-11-15
            ```

            # Example answer (with old documents warning)
            ```
            Here is the information about X...

            ⚠️ Note: Some information in this answer comes from documents that are more than 1 year old and may be outdated.

            **Sources**
            - [Legacy API Documentation](https://example.com/doc3) • 2023-05-15
            - [Current Setup Guide](https://example.com/doc4) • 2024-12-01
            ```

            # Example answer (contradictory information)
            ```
            The documentation contains different information on this topic:

            **Version A** (from Document 1): [explanation]
            **Version B** (from Document 2): [explanation]

            ⚠️ Note: The documentation contains contradictory information on this topic. Please verify which approach is currently in use.

            **Sources**
            - [Document 1](url) • Date
            - [Document 2](url) • Date
            ```

            # Project
            {$conversation->getProject()}

            # Functions
            {$tools}

        PROMPT));

        foreach ($conversation->getMessages() as $message) {
            if (null === $message->getContent()) {
                continue;
            }

            $messages->add(match ($message->getType()) {
                MessageType::USER => Message::ofUser($message->getContent()),
                MessageType::ASSISTANT => Message::ofAssistant($message->getContent()),
                default => throw new \LogicException('Unknown message type'),
            });
        }

        return $messages;
    }
}
