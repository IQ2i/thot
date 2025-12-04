<?php

namespace App\Tests\Command;

use App\Command\DeleteLogCommand;
use App\Entity\Log;
use App\Enum\LogLevel;
use App\Repository\LogRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class DeleteLogCommandTest extends TestCase
{
    private CommandTester $commandTester;
    private LogRepository&MockObject $logRepository;
    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $this->logRepository = $this->createMock(LogRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        // Mock getRepository to return our mocked LogRepository
        $this->entityManager
            ->method('getRepository')
            ->with(Log::class)
            ->willReturn($this->logRepository);

        $command = new DeleteLogCommand($this->entityManager);

        $application = new Application();
        $application->addCommand($command);

        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteWithNoOldLogs(): void
    {
        $this->logRepository
            ->method('findToDelete')
            ->willReturn([]);

        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->once())->method('flush');

        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithDefaultRetentionPeriod(): void
    {
        $this->logRepository
            ->method('findToDelete')
            ->with($this->callback(function (\DateTime $date) {
                // Verify the date is approximately 7 days ago
                $expectedDate = new \DateTime('-7 days');
                $diff = abs($date->getTimestamp() - $expectedDate->getTimestamp());

                return $diff < 2; // Allow 2 seconds difference
            }))
            ->willReturn([]);

        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithCustomRetentionPeriod(): void
    {
        $this->logRepository
            ->method('findToDelete')
            ->with($this->callback(function (\DateTime $date) {
                // Verify the date is approximately 30 days ago
                $expectedDate = new \DateTime('-30 days');
                $diff = abs($date->getTimestamp() - $expectedDate->getTimestamp());

                return $diff < 2; // Allow 2 seconds difference
            }))
            ->willReturn([]);

        $this->commandTester->execute(['--days' => '30']);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithInvalidRetentionPeriod(): void
    {
        $this->commandTester->execute(['--days' => '-5']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Retention days must be a positive number', $output);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testExecuteDeletesLogs(): void
    {
        $oldLog1 = $this->createLog(1, 'Test log 1', new \DateTime('-10 days'));
        $oldLog2 = $this->createLog(2, 'Test log 2', new \DateTime('-15 days'));

        $this->logRepository
            ->method('findToDelete')
            ->willReturn([$oldLog1, $oldLog2]);

        $this->entityManager->expects($this->exactly(2))->method('remove')
            ->willReturnCallback(function ($log) use ($oldLog1, $oldLog2) {
                $this->assertContains($log, [$oldLog1, $oldLog2]);
            });
        $this->entityManager->expects($this->once())->method('flush');

        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteDeletesMultipleLogs(): void
    {
        $logs = [];
        for ($i = 1; $i <= 10; ++$i) {
            $logs[] = $this->createLog($i, "Test log $i", new \DateTime("-{$i}0 days"));
        }

        $this->logRepository
            ->method('findToDelete')
            ->willReturn($logs);

        $this->entityManager->expects($this->exactly(10))->method('remove');
        $this->entityManager->expects($this->once())->method('flush');

        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    private function createLog(int $id, string $message, \DateTime $createdAt): Log
    {
        $log = new Log();
        $log->setMessage($message);
        $log->setLevel(LogLevel::INFO);
        $log->setCreatedAt($createdAt);

        // Use reflection to set the ID
        $reflection = new \ReflectionClass($log);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($log, $id);

        return $log;
    }
}
