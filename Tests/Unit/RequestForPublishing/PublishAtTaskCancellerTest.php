<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tests\Unit\RequestForPublishing;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Bundle\AutomationBundle\RequestForPublishing\PublishAtTargets;
use Sulu\Bundle\AutomationBundle\RequestForPublishing\PublishAtTaskCanceller;
use Sulu\Bundle\AutomationBundle\TaskHandler\PagePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface;
use Sulu\Content\Application\WorkflowTransitionRequest\Event\WorkflowTransitionRequestCancelledEvent;
use Sulu\Content\Application\WorkflowTransitionRequest\Event\WorkflowTransitionRequestSettledEvent;
use Sulu\Content\Domain\Model\WorkflowTransitionRequest\WorkflowTransitionRequest;
use Sulu\Page\Domain\Model\PageInterface;
use Task\Execution\TaskExecutionInterface;
use Task\Storage\TaskExecutionRepositoryInterface;
use Task\TaskInterface as PHPTaskInterface;
use Task\TaskStatus;

class PublishAtTaskCancellerTest extends TestCase
{
    use ProphecyTrait;

    public function testAbortsThePlannedTaskWhenTheRequestIsCancelled(): void
    {
        $this->assertAborted(new WorkflowTransitionRequestCancelledEvent($this->createRequest()));
    }

    public function testAbortsThePlannedTaskWhenTheRequestIsPublishedByHand(): void
    {
        $this->assertAborted(new WorkflowTransitionRequestSettledEvent($this->createRequest()));
    }

    public function testLeavesAnExecutionThatIsNotPlannedAlone(): void
    {
        $execution = $this->prophesize(TaskExecutionInterface::class);
        $execution->getStatus()->willReturn(TaskStatus::RUNNING);
        $execution->setStatus(Argument::any())->shouldNotBeCalled();

        $executionRepository = $this->prophesize(TaskExecutionRepositoryInterface::class);
        $executionRepository->findByTaskUuid('php-task-1')->willReturn([$execution->reveal()]);
        $executionRepository->save(Argument::any())->shouldNotBeCalled();

        $canceller = new PublishAtTaskCanceller(new PublishAtTargets(), $this->createTaskRepository(), $executionRepository->reveal());
        $canceller(new WorkflowTransitionRequestSettledEvent($this->createRequest()));
    }

    public function testLeavesContentWithoutAPublishTaskAlone(): void
    {
        $taskRepository = $this->prophesize(TaskRepositoryInterface::class);
        $taskRepository->findPendingTasks(Argument::cetera())->shouldNotBeCalled();
        $executionRepository = $this->prophesize(TaskExecutionRepositoryInterface::class);
        $executionRepository->save(Argument::any())->shouldNotBeCalled();

        $canceller = new PublishAtTaskCanceller(new PublishAtTargets(), $taskRepository->reveal(), $executionRepository->reveal());
        $canceller(new WorkflowTransitionRequestCancelledEvent($this->createRequest('products')));
    }

    public function testChangesNothingWhenNoTaskIsPending(): void
    {
        $taskRepository = $this->prophesize(TaskRepositoryInterface::class);
        $taskRepository->findPendingTasks(Argument::cetera())->willReturn([]);
        $executionRepository = $this->prophesize(TaskExecutionRepositoryInterface::class);
        $executionRepository->save(Argument::any())->shouldNotBeCalled();

        $canceller = new PublishAtTaskCanceller(new PublishAtTargets(), $taskRepository->reveal(), $executionRepository->reveal());
        $canceller(new WorkflowTransitionRequestSettledEvent($this->createRequest()));
    }

    private function assertAborted(WorkflowTransitionRequestCancelledEvent|WorkflowTransitionRequestSettledEvent $event): void
    {
        $execution = $this->prophesize(TaskExecutionInterface::class);
        $execution->getStatus()->willReturn(TaskStatus::PLANNED);
        $execution->setStatus(TaskStatus::ABORTED)->shouldBeCalledOnce();

        $executionRepository = $this->prophesize(TaskExecutionRepositoryInterface::class);
        $executionRepository->findByTaskUuid('php-task-1')->willReturn([$execution->reveal()]);
        $executionRepository->save($execution->reveal())->shouldBeCalledOnce();

        $canceller = new PublishAtTaskCanceller(new PublishAtTargets(), $this->createTaskRepository(), $executionRepository->reveal());
        $canceller($event);
    }

    private function createTaskRepository(): TaskRepositoryInterface
    {
        $phpTask = $this->prophesize(PHPTaskInterface::class);
        $phpTask->getUuid()->willReturn('php-task-1');

        $task = $this->prophesize(TaskInterface::class);
        $task->getTask()->willReturn($phpTask->reveal());

        $taskRepository = $this->prophesize(TaskRepositoryInterface::class);
        $taskRepository->findPendingTasks(PagePublishTaskHandler::class, PageInterface::class, 'page-uuid', 'en')
            ->willReturn([$task->reveal()]);

        return $taskRepository->reveal();
    }

    private function createRequest(string $resourceKey = 'pages'): WorkflowTransitionRequest
    {
        return new WorkflowTransitionRequest($resourceKey, 'page-uuid', 'en', 'review');
    }
}
