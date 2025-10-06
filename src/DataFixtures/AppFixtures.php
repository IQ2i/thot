<?php

namespace App\DataFixtures;

use App\Enum\MessageType;
use App\Factory\ConversationFactory;
use App\Factory\MessageFactory;
use App\Factory\ProjectFactory;
use App\Factory\UserFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $user = UserFactory::createOne([
            'username' => 'Loïc Sapone',
            'email' => 'loic@sapone.fr',
            'roles' => ['ROLE_ADMIN'],
        ])->_real();

        $project = ProjectFactory::createOne([
            'name' => 'IQ2i - Data Importer',
            'startedAt' => new \DateTimeImmutable('2014-01-01 00:00:00'),
        ])->_real();

        $conversation = ConversationFactory::createOne([
            'name' => 'My first conversation',
            'user' => $user,
            'project' => $project,
        ])->_real();
        MessageFactory::createOne([
            'type' => MessageType::USER,
            'content' => 'Why is the sky blue?',
            'conversation' => $conversation,
        ]);
        MessageFactory::createOne([
            'type' => MessageType::ASSISTANT,
            'content' => 'The sky is blue because it is the color of the sky.',
            'conversation' => $conversation,
        ]);
    }
}
