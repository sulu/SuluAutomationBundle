<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tests\Unit\Tasks\Manager;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Sulu\Bundle\AutomationBundle\Events\Events;
use Sulu\Bundle\AutomationBundle\Events\TaskEvent;
use Sulu\Bundle\AutomationBundle\Tasks\Manager\TaskManager;
use Sulu\Bundle\AutomationBundle\Tasks\Manager\TaskManagerInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Scheduler\TaskSchedulerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests for task-manager.
 */
class TaskManagerTest extends TestCase
{
    /**
     * @var TaskRepositoryInterface
     */
    private $taskRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var TaskSchedulerInterface
     */
    private $taskScheduler;

    /**
     * @var TaskManagerInterface
     */
    private $taskManager;

    protected function setUp()
    {
        $this->taskRepository = $this->prophesize(TaskRepositoryInterface::class);
        $this->eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $this->taskScheduler = $this->prophesize(TaskSchedulerInterface::class);

        $this->taskManager = new TaskManager(
            $this->taskRepository->reveal(),
            $this->taskScheduler->reveal(),
            $this->eventDispatcher->reveal()
        );
    }

    public function testCreate()
    {
        $task = $this->prophesize(TaskInterface::class);
        $task->setId(Argument::type('string'))->shouldBeCalled();
        $this->taskScheduler->schedule($task->reveal())->shouldBeCalled();

        $this->assertEventDispatched(Events::TASK_CREATE_EVENT, $task->reveal());
        $this->taskRepository->save($task->reveal())->shouldBeCalled()->willReturnArgument(0);

        $this->taskManager->create($task->reveal());
    }

    public function testUpdate()
    {
        $task = $this->prophesize(TaskInterface::class);
        $this->taskScheduler->reschedule($task->reveal())->shouldBeCalled();

        $this->assertEventDispatched(Events::TASK_UPDATE_EVENT, $task->reveal());

        $this->taskManager->update($task->reveal());
    }

    public function testRemove()
    {
        $id = 1;
        $task = $this->prophesize(TaskInterface::class);
        $this->taskRepository->findById($id)->shouldBeCalled()->willReturn($task->reveal());
        $this->taskScheduler->remove($task->reveal())->shouldBeCalled();

        $this->assertEventDispatched(Events::TASK_REMOVE_EVENT, $task->reveal());
        $this->taskRepository->remove($task->reveal())->shouldBeCalled()->willReturnArgument(0);

        $this->taskManager->remove($id);
    }

    public function testFindById()
    {
        $id = 1;
        $task = $this->prophesize(TaskInterface::class);
        $this->taskRepository->findById($id)->shouldBeCalled()->willReturn($task->reveal());

        $this->assertEquals($task->reveal(), $this->taskManager->findById($id));
    }

    private function assertEventDispatched($eventName, $task)
    {
        $this->eventDispatcher->dispatch(
            $eventName,
            Argument::that(
                function (TaskEvent $event) use ($task) {
                    return $task == $event->getTask();
                }
            )
        )->willReturnArgument(1);
    }
}
