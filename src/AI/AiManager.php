<?php

namespace App\AI;

use App\AI\Agent\InputProcessor\ChatPromptInputProcessor;
use App\AI\Agent\InputProcessor\TitlePromptInputProcessor;
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
        $promptProcessor = new TitlePromptInputProcessor();

        $agent = new Agent($this->getPlatform(), self::TITLE_GENERATION_MODEL, [$promptProcessor]);

        $messages = new MessageBag(
            Message::ofUser($question)
        );

        return $agent->call($messages, [
            'temperature' => 1,
            'reasoning_effort' => 'low',
        ]);
    }

    public function ask(Conversation $conversation): ResultInterface
    {
        $promptProcessor = new ChatPromptInputProcessor($this->getToolbox());
        $agentProcessor = new AgentProcessor($this->getToolbox());

        $agent = new Agent($this->getPlatform(), self::CHAT_MODEL, [$promptProcessor, $agentProcessor], [$agentProcessor]);

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
        $messages = new MessageBag();
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
