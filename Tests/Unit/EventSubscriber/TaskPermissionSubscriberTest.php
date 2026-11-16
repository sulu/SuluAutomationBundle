<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AutomationBundle\Entity\Task;
use Sulu\Bundle\AutomationBundle\EventSubscriber\PHPTaskEventSubscriber;
use Sulu\Bundle\AutomationBundle\EventSubscriber\TaskPermissionSubscriber;
use Sulu\Bundle\AutomationBundle\Exception\TaskPermissionDeniedException;
use Sulu\Bundle\AutomationBundle\Security\TaskPermissionChecker;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface;
use Task\Event\Events;
use Task\Event\TaskEvent;
use Task\Task as PHPTask;

class TaskPermissionSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<TaskRepositoryInterface> */
    private ObjectProphecy $taskRepository;

    /** @var ObjectProphecy<TaskPermissionChecker> */
    private ObjectProphecy $permissionChecker;

    private TaskPermissionSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->taskRepository = $this->prophesize(TaskRepositoryInterface::class);
        $this->permissionChecker = $this->prophesize(TaskPermissionChecker::class);

        $this->subscriber = new TaskPermissionSubscriber(
            $this->taskRepository->reveal(),
            $this->permissionChecker->reveal(),
        );
    }

    public function testRunsBeforeTheRequestIsPushed(): void
    {
        $mine = TaskPermissionSubscriber::getSubscribedEvents()[Events::TASK_BEFORE];
        $pushRequest = PHPTaskEventSubscriber::getSubscribedEvents()[Events::TASK_BEFORE];

        $this->assertGreaterThan($pushRequest[1] ?? 0, $mine[1]);
    }

    public function testChecksUserOfWorkload(): void
    {
        $phpTask = $this->createPhpTask(['class' => 'Entity', 'id' => '1', 'locale' => 'de', 'userId' => 7]);
        $this->taskRepository->findByTask($phpTask)->willReturn(new Task());

        $this->permissionChecker->checkUser(7, 'Handler', 'Entity', '1', 'de')->shouldBeCalled();

        $this->subscriber->checkPermission(new TaskEvent($phpTask));
    }

    public function testFailsWhenUserIsNotAllowedAnymore(): void
    {
        $phpTask = $this->createPhpTask(['class' => 'Entity', 'id' => '1', 'locale' => 'de', 'userId' => 7]);
        $this->taskRepository->findByTask($phpTask)->willReturn(new Task());
        $this->permissionChecker->checkUser(7, 'Handler', 'Entity', '1', 'de')->willThrow(new TaskPermissionDeniedException('no'));

        $this->expectException(TaskPermissionDeniedException::class);

        $this->subscriber->checkPermission(new TaskEvent($phpTask));
    }

    public function testIgnoresTasksOfOtherBundles(): void
    {
        $phpTask = $this->createPhpTask(['class' => 'Entity', 'id' => '1', 'locale' => 'de', 'userId' => 7]);
        $this->taskRepository->findByTask($phpTask)->willReturn(null);

        $this->permissionChecker->checkUser(\Prophecy\Argument::cetera())->shouldNotBeCalled();

        $this->subscriber->checkPermission(new TaskEvent($phpTask));
    }

    /**
     * @group legacy
     */
    public function testRunsTasksWithoutUserAsBefore(): void
    {
        $phpTask = $this->createPhpTask(['class' => 'Entity', 'id' => '1', 'locale' => 'de']);
        $this->taskRepository->findByTask($phpTask)->willReturn(new Task());

        $this->permissionChecker->checkUser(\Prophecy\Argument::cetera())->shouldNotBeCalled();

        $this->subscriber->checkPermission(new TaskEvent($phpTask));
    }

    /**
     * @param array<string, mixed> $workload
     */
    private function createPhpTask(array $workload): PHPTask
    {
        return new PHPTask('Handler', $workload, 'task-uuid');
    }
}
