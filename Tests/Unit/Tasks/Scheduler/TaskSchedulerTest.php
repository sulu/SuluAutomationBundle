<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tests\Unit\Tasks\Scheduler;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AutomationBundle\Entity\Task;
use Sulu\Bundle\AutomationBundle\Exception\TaskExpiredException;
use Sulu\Bundle\AutomationBundle\Tasks\Scheduler\TaskScheduler;
use Sulu\Bundle\AutomationBundle\Tests\Handler\FirstHandler;
use Task\Builder\TaskBuilderInterface;
use Task\Execution\TaskExecutionInterface;
use Task\Handler\TaskHandlerFactoryInterface;
use Task\Scheduler\TaskSchedulerInterface as PHPTaskSchedulerInterface;
use Task\Storage\TaskExecutionRepositoryInterface;
use Task\Storage\TaskRepositoryInterface;
use Task\TaskInterface as PHPTaskInterface;
use Task\TaskStatus;

/**
 * Tests for TaskScheduler class.
 */
class TaskSchedulerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<TaskRepositoryInterface> */
    private ObjectProphecy $taskRepository;

    /** @var ObjectProphecy<TaskExecutionRepositoryInterface> */
    private ObjectProphecy $taskExecutionRepository;

    /** @var ObjectProphecy<TaskHandlerFactoryInterface> */
    private ObjectProphecy $taskHandlerFactory;

    /** @var ObjectProphecy<PHPTaskSchedulerInterface> */
    private ObjectProphecy $phpTaskScheduler;

    private TaskScheduler $taskScheduler;

    protected function setUp(): void
    {
        $this->taskRepository = $this->prophesize(TaskRepositoryInterface::class);
        $this->taskExecutionRepository = $this->prophesize(TaskExecutionRepositoryInterface::class);
        $this->taskHandlerFactory = $this->prophesize(TaskHandlerFactoryInterface::class);
        $this->phpTaskScheduler = $this->prophesize(PHPTaskSchedulerInterface::class);

        $this->taskScheduler = new TaskScheduler(
            $this->taskRepository->reveal(),
            $this->taskExecutionRepository->reveal(),
            $this->taskHandlerFactory->reveal(),
            $this->phpTaskScheduler->reveal()
        );
    }

    public function testSchedule(): void
    {
        $task = $this->createTask();

        $handler = new FirstHandler();
        $this->taskHandlerFactory->create(FirstHandler::class)->willReturn($handler);

        $taskBuilder = $this->prophesize(TaskBuilderInterface::class);
        $phpTask = $this->prophesize(PHPTaskInterface::class);
        $phpTask->getUuid()->willReturn('test-uuid-123');

        $this->phpTaskScheduler
            ->createTask(FirstHandler::class, Argument::type('array'))
            ->willReturn($taskBuilder->reveal());

        $taskBuilder->executeAt($task->getSchedule())->willReturn($taskBuilder->reveal());
        $taskBuilder->schedule()->willReturn($phpTask->reveal());

        $this->taskScheduler->schedule($task);

        $this->assertSame($phpTask->reveal(), $task->getTask());
    }

    public function testRescheduleWithDifferentSchedule(): void
    {
        $task = $this->createTask();
        $existingPhpTask = $this->prophesize(PHPTaskInterface::class);
        $existingPhpTask->getUuid()->willReturn('existing-uuid');
        $existingPhpTask->getFirstExecution()->willReturn(new \DateTimeImmutable('-1 day'));
        $existingPhpTask->getHandlerClass()->willReturn(FirstHandler::class);
        $existingPhpTask->getWorkload()->willReturn(['class' => 'TestClass', 'id' => '1', 'locale' => 'de']);
        $task->setTask($existingPhpTask->reveal());

        $this->taskRepository->findByUuid('existing-uuid')->willReturn($existingPhpTask->reveal());

        $execution = $this->prophesize(TaskExecutionInterface::class);
        $execution->getStatus()->willReturn(TaskStatus::PLANNED);
        $this->taskExecutionRepository->findByTask($existingPhpTask->reveal())->willReturn([$execution->reveal()]);
        $this->taskExecutionRepository->remove($execution->reveal())->shouldBeCalled();
        $this->taskRepository->remove($existingPhpTask->reveal())->shouldBeCalled();

        $handler = new FirstHandler();
        $this->taskHandlerFactory->create(FirstHandler::class)->willReturn($handler);

        $taskBuilder = $this->prophesize(TaskBuilderInterface::class);
        $newPhpTask = $this->prophesize(PHPTaskInterface::class);
        $newPhpTask->getUuid()->willReturn('new-uuid-123');

        $this->phpTaskScheduler
            ->createTask(FirstHandler::class, Argument::type('array'))
            ->willReturn($taskBuilder->reveal());

        $taskBuilder->executeAt($task->getSchedule())->willReturn($taskBuilder->reveal());
        $taskBuilder->schedule()->willReturn($newPhpTask->reveal());

        $this->taskScheduler->reschedule($task);

        $this->assertSame($newPhpTask->reveal(), $task->getTask());
    }

    public function testRescheduleWithSameParametersDoesNothing(): void
    {
        $schedule = new \DateTimeImmutable('+1 day');
        $task = $this->createTask($schedule);

        $existingPhpTask = $this->prophesize(PHPTaskInterface::class);
        $existingPhpTask->getUuid()->willReturn('existing-uuid');
        $existingPhpTask->getFirstExecution()->willReturn($schedule);
        $existingPhpTask->getHandlerClass()->willReturn(FirstHandler::class);
        $existingPhpTask->getWorkload()->willReturn(['class' => 'TestClass', 'id' => '1', 'locale' => 'de']);
        $task->setTask($existingPhpTask->reveal());

        $handler = new FirstHandler();
        $this->taskHandlerFactory->create(FirstHandler::class)->willReturn($handler);

        $this->taskRepository->findByUuid('existing-uuid')->willReturn($existingPhpTask->reveal());
        $this->taskExecutionRepository->findByTask($existingPhpTask->reveal())->willReturn(null)->shouldBeCalled();
        $this->taskExecutionRepository->remove($existingPhpTask->reveal())->shouldNotBeCalled();

        $this->taskScheduler->reschedule($task);
    }

    public function testRescheduleThrowsExceptionWhenTaskIsNotPlanned(): void
    {
        $task = $this->createTask();
        $existingPhpTask = $this->prophesize(PHPTaskInterface::class);
        $existingPhpTask->getUuid()->willReturn('existing-uuid');
        $existingPhpTask->getFirstExecution()->willReturn(new \DateTimeImmutable('-1 day'));
        $existingPhpTask->getHandlerClass()->willReturn(FirstHandler::class);
        $existingPhpTask->getWorkload()->willReturn(['class' => 'TestClass', 'id' => '1', 'locale' => 'de']);
        $task->setTask($existingPhpTask->reveal());

        $this->taskRepository->findByUuid('existing-uuid')->willReturn($existingPhpTask->reveal());

        $execution = $this->prophesize(TaskExecutionInterface::class);
        $execution->getStatus()->willReturn(TaskStatus::COMPLETED); // Not PLANNED
        $this->taskExecutionRepository->findByTask($existingPhpTask->reveal())->willReturn([$execution->reveal()]);

        $handler = new FirstHandler();
        $this->taskHandlerFactory->create(FirstHandler::class)->willReturn($handler);

        $this->expectException(TaskExpiredException::class);
        $this->taskScheduler->reschedule($task);
    }

    public function testRemove(): void
    {
        $task = $this->createTask();
        $existingPhpTask = $this->prophesize(PHPTaskInterface::class);
        $existingPhpTask->getUuid()->willReturn('existing-uuid');
        $task->setTask($existingPhpTask->reveal());

        $this->taskRepository->findByUuid('existing-uuid')->willReturn($existingPhpTask->reveal());
        $this->taskRepository->remove($existingPhpTask->reveal())->shouldBeCalled();

        $this->taskScheduler->remove($task);
    }

    /**
     * Create a real Task object with test data.
     */
    private function createTask(?\DateTimeImmutable $schedule = null): Task
    {
        $task = new Task();
        $task->setId('test-task-123');
        $task->setHandlerClass(FirstHandler::class);
        $task->setEntityClass('TestClass');
        $task->setEntityId('1');
        $task->setLocale('de');
        $task->setSchedule($schedule ?? new \DateTimeImmutable('+1 day'));
        $task->setHost('localhost');
        $task->setScheme('http');

        return $task;
    }
}
