<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\RequestForPublishing;

use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface;
use Sulu\Content\Application\RequestWorkflow\WorkflowTransitionRequestStatusResolverInterface;
use Sulu\Content\Application\WorkflowTransitionRequest\Event\WorkflowTransitionRequestActionEvent;
use Sulu\Content\Application\WorkflowTransitionRequest\Event\WorkflowTransitionRequestCancelledEvent;
use Sulu\Content\Application\WorkflowTransitionRequest\Event\WorkflowTransitionRequestSettledEvent;
use Sulu\Content\Domain\Value\WorkflowTransitionRequest\WorkflowTransitionRequestStatusEnum;
use Task\Storage\TaskExecutionRepositoryInterface;
use Task\TaskStatus;

/**
 * Aborts the task of a request that is cancelled, published by hand, or loses its approval through a rejection.
 * Manually scheduled tasks stay. Aborted tasks remain listed. A started task is left alone, because its own publish
 * settles the request while it runs.
 */
final class PublishAtTaskCanceller
{
    public function __construct(
        private readonly PublishAtTargets $targets,
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly TaskExecutionRepositoryInterface $taskExecutionRepository,
        private readonly WorkflowTransitionRequestStatusResolverInterface $statusResolver,
    ) {
    }

    public function __invoke(WorkflowTransitionRequestActionEvent|WorkflowTransitionRequestCancelledEvent|WorkflowTransitionRequestSettledEvent $event): void
    {
        if ($event instanceof WorkflowTransitionRequestActionEvent && !$this->isRejectedApproval($event)) {
            return;
        }

        $resourceKey = $event->getResourceKey();
        if (!$this->targets->supports($resourceKey)) {
            return;
        }

        $data = $event instanceof WorkflowTransitionRequestActionEvent
            ? $event->getWorkflowTransitionRequest()->getData()
            : $event->getData();
        $taskId = $data[PublishAtAction::TASK_ID_KEY] ?? null;
        if (!\is_string($taskId) || '' === $taskId) {
            return;
        }

        $tasks = $this->taskRepository->findPendingTasks(
            $this->targets->getHandlerClass($resourceKey),
            $this->targets->getEntityClass($resourceKey),
            $event->getResourceId(),
            $event->getLocale(),
        );

        foreach ($tasks as $task) {
            if ($taskId !== $task->getId()) {
                continue;
            }

            foreach ($this->taskExecutionRepository->findByTaskUuid((string) $task->getTask()?->getUuid()) as $execution) {
                if (TaskStatus::PLANNED !== $execution->getStatus()) {
                    continue;
                }

                // not saved: the workflow flushes the managed execution with the marking of the request
                $execution->setStatus(TaskStatus::ABORTED);
            }
        }
    }

    private function isRejectedApproval(WorkflowTransitionRequestActionEvent $event): bool
    {
        return WorkflowTransitionRequestActionEvent::REJECTED === $event->getAction()
            && WorkflowTransitionRequestStatusEnum::APPROVED !== $this->statusResolver->resolve($event->getWorkflowTransitionRequest());
    }
}
