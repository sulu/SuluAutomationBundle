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
use Sulu\Content\Application\RequestWorkflow\WorkflowTransitionRequestStatusResolverInterface;
use Sulu\Content\Application\WorkflowTransitionRequest\Event\WorkflowTransitionRequestActionEvent;
use Sulu\Content\Application\WorkflowTransitionRequest\Event\WorkflowTransitionRequestCancelledEvent;
use Sulu\Content\Application\WorkflowTransitionRequest\Event\WorkflowTransitionRequestSettledEvent;
use Sulu\Content\Domain\Model\WorkflowTransitionRequest\WorkflowTransitionRequest;
use Sulu\Content\Domain\Value\WorkflowTransitionRequest\WorkflowTransitionRequestStatusEnum;
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

    public function testAbortsThePlannedTaskWhenARejectionTakesTheApprovalAway(): void
    {
        $this->assertAborted(
            new WorkflowTransitionRequestActionEvent($this->createRequest(), WorkflowTransitionRequestActionEvent::REJECTED),
            WorkflowTransitionRequestStatusEnum::PENDING,
        );
    }

    public function testKeepsThePlannedTaskWhenTheRequestIsStillApprovedAfterARejection(): void
    {
        $executionRepository = $this->prophesize(TaskExecutionRepositoryInterface::class);
        $executionRepository->save(Argument::any())->shouldNotBeCalled();

        $taskRepository = $this->prophesize(TaskRepositoryInterface::class);
        $taskRepository->findPendingTasks(Argument::cetera())->shouldNotBeCalled();

        $canceller = new PublishAtTaskCanceller(
            new PublishAtTargets(),
            $taskRepository->reveal(),
            $executionRepository->reveal(),
            $this->createStatusResolver(WorkflowTransitionRequestStatusEnum::APPROVED),
        );
        $canceller(new WorkflowTransitionRequestActionEvent($this->createRequest(), WorkflowTransitionRequestActionEvent::REJECTED));
    }

    public function testIgnoresTheOtherActionsOfARequest(): void
    {
        $taskRepository = $this->prophesize(TaskRepositoryInterface::class);
        $taskRepository->findPendingTasks(Argument::cetera())->shouldNotBeCalled();

        $canceller = new PublishAtTaskCanceller(
            new PublishAtTargets(),
            $taskRepository->reveal(),
            $this->prophesize(TaskExecutionRepositoryInterface::class)->reveal(),
            $this->createStatusResolver(WorkflowTransitionRequestStatusEnum::PENDING),
        );
        $canceller(new WorkflowTransitionRequestActionEvent($this->createRequest(), WorkflowTransitionRequestActionEvent::APPROVED));
    }

    public function testLeavesAnExecutionThatIsNotPlannedAlone(): void
    {
        $execution = $this->prophesize(TaskExecutionInterface::class);
        $execution->getStatus()->willReturn(TaskStatus::RUNNING);
        $execution->setStatus(Argument::any())->shouldNotBeCalled();

        $executionRepository = $this->prophesize(TaskExecutionRepositoryInterface::class);
        $executionRepository->findByTaskUuid('php-task-1')->willReturn([$execution->reveal()]);
        $executionRepository->save(Argument::any())->shouldNotBeCalled();

        $canceller = new PublishAtTaskCanceller(new PublishAtTargets(), $this->createTaskRepository(), $executionRepository->reveal(), $this->createStatusResolver());
        $canceller(new WorkflowTransitionRequestSettledEvent($this->createRequest()));
    }

    /**
     * A request without the switch has no task, and a publish scheduled by hand for the content is not its own.
     */
    public function testLeavesTheTaskOfAnotherDateAloneWhenTheRequestHasNone(): void
    {
        $taskRepository = $this->prophesize(TaskRepositoryInterface::class);
        $taskRepository->findPendingTasks(Argument::cetera())->shouldNotBeCalled();
        $executionRepository = $this->prophesize(TaskExecutionRepositoryInterface::class);
        $executionRepository->findByTaskUuid(Argument::any())->shouldNotBeCalled();

        $canceller = new PublishAtTaskCanceller(new PublishAtTargets(), $taskRepository->reveal(), $executionRepository->reveal(), $this->createStatusResolver());
        $canceller(new WorkflowTransitionRequestCancelledEvent($this->createRequest(withTask: false)));
    }

    /**
     * Only the task named by the request is aborted, the manual one of the same content stays planned.
     */
    public function testAbortsOnlyTheTaskOfTheRequest(): void
    {
        $execution = $this->prophesize(TaskExecutionInterface::class);
        $execution->getStatus()->willReturn(TaskStatus::PLANNED);
        $execution->setStatus(TaskStatus::ABORTED)->shouldBeCalledOnce();

        $executionRepository = $this->prophesize(TaskExecutionRepositoryInterface::class);
        $executionRepository->findByTaskUuid('php-task-1')->willReturn([$execution->reveal()]);
        $executionRepository->findByTaskUuid('php-task-manual')->shouldNotBeCalled();

        $canceller = new PublishAtTaskCanceller(new PublishAtTargets(), $this->createTaskRepository(), $executionRepository->reveal(), $this->createStatusResolver());
        $canceller(new WorkflowTransitionRequestCancelledEvent($this->createRequest()));
    }

    public function testLeavesContentWithoutAPublishTaskAlone(): void
    {
        $taskRepository = $this->prophesize(TaskRepositoryInterface::class);
        $taskRepository->findPendingTasks(Argument::cetera())->shouldNotBeCalled();
        $executionRepository = $this->prophesize(TaskExecutionRepositoryInterface::class);
        $executionRepository->save(Argument::any())->shouldNotBeCalled();

        $canceller = new PublishAtTaskCanceller(new PublishAtTargets(), $taskRepository->reveal(), $executionRepository->reveal(), $this->createStatusResolver());
        $canceller(new WorkflowTransitionRequestCancelledEvent($this->createRequest('products')));
    }

    public function testChangesNothingWhenNoTaskIsPending(): void
    {
        $taskRepository = $this->prophesize(TaskRepositoryInterface::class);
        $taskRepository->findPendingTasks(Argument::cetera())->willReturn([]);
        $executionRepository = $this->prophesize(TaskExecutionRepositoryInterface::class);
        $executionRepository->save(Argument::any())->shouldNotBeCalled();

        $canceller = new PublishAtTaskCanceller(new PublishAtTargets(), $taskRepository->reveal(), $executionRepository->reveal(), $this->createStatusResolver());
        $canceller(new WorkflowTransitionRequestSettledEvent($this->createRequest()));
    }

    private function assertAborted(
        WorkflowTransitionRequestActionEvent|WorkflowTransitionRequestCancelledEvent|WorkflowTransitionRequestSettledEvent $event,
        WorkflowTransitionRequestStatusEnum $status = WorkflowTransitionRequestStatusEnum::APPROVED,
    ): void {
        $execution = $this->prophesize(TaskExecutionInterface::class);
        $execution->getStatus()->willReturn(TaskStatus::PLANNED);
        $execution->setStatus(TaskStatus::ABORTED)->shouldBeCalledOnce();

        $executionRepository = $this->prophesize(TaskExecutionRepositoryInterface::class);
        $executionRepository->findByTaskUuid('php-task-1')->willReturn([$execution->reveal()]);
        // the workflow flushes the change with the request, a save would flush the whole entity manager inside it
        $executionRepository->save(Argument::any())->shouldNotBeCalled();

        $canceller = new PublishAtTaskCanceller(new PublishAtTargets(), $this->createTaskRepository(), $executionRepository->reveal(), $this->createStatusResolver($status));
        $canceller($event);
    }

    private function createStatusResolver(WorkflowTransitionRequestStatusEnum $status = WorkflowTransitionRequestStatusEnum::APPROVED): WorkflowTransitionRequestStatusResolverInterface
    {
        $statusResolver = $this->prophesize(WorkflowTransitionRequestStatusResolverInterface::class);
        $statusResolver->resolve(Argument::type(WorkflowTransitionRequest::class))->willReturn($status);

        return $statusResolver->reveal();
    }

    private function createTaskRepository(): TaskRepositoryInterface
    {
        $phpTask = $this->prophesize(PHPTaskInterface::class);
        $phpTask->getUuid()->willReturn('php-task-1');

        $task = $this->prophesize(TaskInterface::class);
        $task->getId()->willReturn('task-1');
        $task->getTask()->willReturn($phpTask->reveal());

        $manualPhpTask = $this->prophesize(PHPTaskInterface::class);
        $manualPhpTask->getUuid()->willReturn('php-task-manual');
        $manualTask = $this->prophesize(TaskInterface::class);
        $manualTask->getId()->willReturn('manual-task');
        $manualTask->getTask()->willReturn($manualPhpTask->reveal());

        $taskRepository = $this->prophesize(TaskRepositoryInterface::class);
        $taskRepository->findPendingTasks(PagePublishTaskHandler::class, PageInterface::class, 'page-uuid', 'en')
            ->willReturn([$manualTask->reveal(), $task->reveal()]);

        return $taskRepository->reveal();
    }

    private function createRequest(string $resourceKey = 'pages', bool $withTask = true): WorkflowTransitionRequest
    {
        $request = new WorkflowTransitionRequest($resourceKey, 'page-uuid', 'en', 'review');
        $request->setData($withTask ? ['publishAutomatically' => true, 'publishAtTaskId' => 'task-1'] : ['publishAutomatically' => false]);

        return $request;
    }
}
