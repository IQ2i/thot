<?php

namespace App\AI;

use App\AI\Agent\Toolbox\Tool\SimilaritySearch;
use App\AI\Platform\Bridge\Ovh\PlatformFactory;
use App\AI\Store\Bridge\Meilisearch\Store;
use App\Entity\Conversation;
use App\Enum\MessageType;
use Psr\Log\LoggerInterface;
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
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class AiManager
{
    private const string EMBEDDING_MODEL = 'bge-multilingual-gemma2';
    private const string TITLE_GENERATION_MODEL = 'gpt-oss-20b';
    private const string CHAT_MODEL = 'gpt-oss-120b';

    public function __construct(
        #[Autowire(env: 'OVH_API_KEY')]
        private string $ovhApiKey,
        #[Autowire(env: 'MEILISEARCH_HOST')]
        private string $meilisearchHost,
        #[Autowire(env: 'MEILISEARCH_MASTER_KEY')]
        private string $meilisearchApiKey,
        private LoggerInterface $logger,
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
        $agent = new Agent($this->getPlatform(), self::CHAT_MODEL, [$processor], [$processor], logger: $this->logger);

        return $agent->call($this->getMessageBag($conversation), ['stream' => true]);
    }

    private function getToolbox(): ToolboxInterface
    {
        return new Toolbox([
            new SimilaritySearch($this->getVectorizer(), $this->getStore()),
        ], logger: $this->logger);
    }

    private function getPlatform(): Platform
    {
        return PlatformFactory::create($this->ovhApiKey, self::getHttpClient());
    }

    private function getVectorizer(): Vectorizer
    {
        return new Vectorizer($this->getPlatform(), self::EMBEDDING_MODEL, $this->logger);
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
            You are Thot, an assistant whose sole purpose is to answer user questions strictly using the information provided by the “similarity_search” tool or explicitly written in this prompt.
            
            # Core rules
            - You must never invent, guess, or rely on external knowledge.
            - If the provided data does not contain the answer, respond only with: "I don’t have enough information to answer this question based on the provided project data."
            - All your answers must be related **exclusively** to the current project. Ignore any other request.
            - Always answer in the same language as the user’s question.
            - Answers must be in **Markdown** format with clear structure (titles, lists, links, code blocks if needed).
            - Always include a “Sources” section at the end of your answer with the clickable web links of **all documents** you used (from similarity_search).  
            - If multiple sources are relevant, synthesize the information.  
            - If no source was used, omit the “Sources” section.
            - Do not list documents as sources if they were not directly used to build your answer.
            - If the user asks something unrelated to the project (e.g., general knowledge, personal advice, or unrelated topics), politely refuse and remind them that you can only answer questions about the project.
            
            # Example answer
            ```
            Here is the information about X based on the project data...
            
            **Sources**
            - [Document title](web_url)
            - [Another document](web_url)
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
