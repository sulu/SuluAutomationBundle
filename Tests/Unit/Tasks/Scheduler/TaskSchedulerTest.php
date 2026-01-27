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
use Task\Builder\TaskBuilder;
use Task\Execution\TaskExecution;
use Task\Handler\TaskHandlerFactoryInterface;
use Task\Scheduler\TaskSchedulerInterface as PHPTaskSchedulerInterface;
use Task\Storage\TaskExecutionRepositoryInterface;
use Task\Storage\TaskRepositoryInterface;
use Task\Task as PHPTask;
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

        $phpTask = new PHPTask(FirstHandler::class, null, 'test-uuid-123');
        $taskBuilder = new TaskBuilder($phpTask, $this->phpTaskScheduler->reveal());

        $this->phpTaskScheduler
            ->createTask(FirstHandler::class, Argument::type('array'))
            ->willReturn($taskBuilder);

        $this->phpTaskScheduler
            ->addTask($phpTask)
            ->shouldBeCalled();

        $this->taskScheduler->schedule($task);

        $this->assertSame($phpTask, $task->getTask());
    }

    public function testRescheduleWithDifferentSchedule(): void
    {
        $task = $this->createTask();
        $existingPhpTask = new PHPTask(
            FirstHandler::class,
            ['class' => 'TestClass', 'id' => '1', 'locale' => 'de'],
            'existing-uuid'
        );
        $existingPhpTask->setFirstExecution(new \DateTimeImmutable('-1 day'));
        $task->setTask($existingPhpTask);

        $this->taskRepository->findByUuid('existing-uuid')->willReturn($existingPhpTask);

        $execution = new TaskExecution(
            $existingPhpTask,
            FirstHandler::class,
            new \DateTimeImmutable(),
            ['class' => 'TestClass', 'id' => '1', 'locale' => 'de']
        );
        $execution->setStatus(TaskStatus::PLANNED);
        $this->taskExecutionRepository->findByTask($existingPhpTask)->willReturn([$execution]);
        $this->taskExecutionRepository->remove($execution)->shouldBeCalled();
        $this->taskRepository->remove($existingPhpTask)->shouldBeCalled();

        $handler = new FirstHandler();
        $this->taskHandlerFactory->create(FirstHandler::class)->willReturn($handler);

        $newPhpTask = new PHPTask(FirstHandler::class, null, 'new-uuid-123');

        $taskBuilder = new TaskBuilder($newPhpTask, $this->phpTaskScheduler->reveal());

        $this->phpTaskScheduler
            ->createTask(FirstHandler::class, Argument::type('array'))
            ->willReturn($taskBuilder);

        $this->phpTaskScheduler
            ->addTask($newPhpTask)
            ->shouldBeCalled();

        $this->taskScheduler->reschedule($task);

        $this->assertSame($newPhpTask, $task->getTask());
    }

    public function testRescheduleWithSameParametersDoesNothing(): void
    {
        $schedule = new \DateTimeImmutable('+1 day');
        $task = $this->createTask($schedule);

        $existingPhpTask = new PHPTask(
            FirstHandler::class,
            ['class' => 'TestClass', 'id' => '1', 'locale' => 'de'],
            'existing-uuid'
        );
        $existingPhpTask->setFirstExecution($schedule);
        $task->setTask($existingPhpTask);

        $handler = new FirstHandler();
        $this->taskHandlerFactory->create(FirstHandler::class)->willReturn($handler);

        $this->taskRepository->findByUuid('existing-uuid')->willReturn($existingPhpTask);
        $this->taskExecutionRepository->findByTask($existingPhpTask)->willReturn([])->shouldBeCalled();

        $this->taskScheduler->reschedule($task);
    }

    public function testRescheduleThrowsExceptionWhenTaskIsNotPlanned(): void
    {
        $task = $this->createTask();
        $existingPhpTask = new PHPTask(
            FirstHandler::class,
            ['class' => 'TestClass', 'id' => '1', 'locale' => 'de'],
            'existing-uuid'
        );
        $existingPhpTask->setFirstExecution(new \DateTimeImmutable('-1 day'));
        $task->setTask($existingPhpTask);

        $this->taskRepository->findByUuid('existing-uuid')->willReturn($existingPhpTask);

        $execution = new TaskExecution(
            $existingPhpTask,
            FirstHandler::class,
            new \DateTimeImmutable(),
            ['class' => 'TestClass', 'id' => '1', 'locale' => 'de']
        );
        $execution->setStatus(TaskStatus::COMPLETED); // Not PLANNED
        $this->taskExecutionRepository->findByTask($existingPhpTask)->willReturn([$execution]);

        $handler = new FirstHandler();
        $this->taskHandlerFactory->create(FirstHandler::class)->willReturn($handler);

        $this->expectException(TaskExpiredException::class);
        $this->taskScheduler->reschedule($task);
    }

    public function testRemove(): void
    {
        $task = $this->createTask();
        $existingPhpTask = new PHPTask(FirstHandler::class, null, 'existing-uuid');
        $task->setTask($existingPhpTask);

        $this->taskRepository->findByUuid('existing-uuid')->willReturn($existingPhpTask);
        $this->taskRepository->remove($existingPhpTask)->shouldBeCalled();

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
