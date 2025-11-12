<?php

namespace App\EventSubscriber;

use App\Entity\Log;
use App\Entity\User;
use App\Enum\LogLevel;
use App\Event\ActionEvent;
use App\Event\AgentEvent;
use App\Events;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Agent\Toolbox\Event\ToolCallArgumentsResolved;
use Symfony\AI\Agent\Toolbox\Event\ToolCallFailed;
use Symfony\AI\Agent\Toolbox\Event\ToolCallSucceeded;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class LogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function onActionEvent(ActionEvent $event): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $log = new Log()
            ->setLevel(LogLevel::INFO)
            ->setMessage(\sprintf($event->getContent(), $user->getUsername()));

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function onAgentEvent(AgentEvent $event): void
    {
        /** @var ?User $user */
        $user = $this->security->getUser();

        $log = new Log()
            ->setLevel($event->isError() ? LogLevel::ERROR : LogLevel::DEBUG)
            ->setMessage(\sprintf($event->getContent(), $user?->getUsername()))
            ->setAdditionalContent($event->getMessage());

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function onBeforeToolCallEvent(ToolCallArgumentsResolved $event): void
    {
        $args = [];
        foreach ($event->getArguments() as $name => $value) {
            if (null === $value) {
                continue;
            }

            $args[] = \sprintf('%s: "%s" ', $name, $value);
        }

        $log = new Log()
            ->setLevel(LogLevel::DEBUG)
            ->setMessage(\sprintf('Agent trying to call tool "%s" with arguments', $event->getMetadata()->getName()))
            ->setAdditionalContent(implode(' + ', $args));

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function onSuccessToolCallEvent(ToolCallSucceeded $event): void
    {
        $log = new Log()
            ->setLevel(LogLevel::DEBUG)
            ->setMessage(\sprintf('Tool "%s" call succeeded', $event->getMetadata()->getName()))
            ->setAdditionalContent($event->getResult()->getResult());

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function onErrorToolCallEvent(ToolCallFailed $event): void
    {
        $log = new Log()
            ->setLevel(LogLevel::ERROR)
            ->setMessage(\sprintf('Tool "%s" call failed', $event->getMetadata()->getName()))
            ->setAdditionalContent($event->getException()->getMessage());

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::PROJECT_CREATE => 'onActionEvent',
            Events::PROJECT_EDIT => 'onActionEvent',
            Events::PROJECT_DELETE => 'onActionEvent',
            Events::USER_CREATE => 'onActionEvent',
            Events::USER_EDIT => 'onActionEvent',
            Events::USER_DELETE => 'onActionEvent',
            Events::CONVERSATION_CREATE => 'onActionEvent',
            Events::CONVERSATION_EDIT => 'onActionEvent',
            Events::CONVERSATION_DELETE => 'onActionEvent',
            Events::AGENT_ASK => 'onAgentEvent',
            Events::AGENT_RESPONSE => 'onAgentEvent',
            ToolCallArgumentsResolved::class => 'onBeforeToolCallEvent',
            ToolCallSucceeded::class => 'onSuccessToolCallEvent',
            ToolCallFailed::class => 'onErrorToolCallEvent',
        ];
    }
}
