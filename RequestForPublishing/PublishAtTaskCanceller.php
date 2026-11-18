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
use Sulu\Content\Application\WorkflowTransitionRequest\Event\WorkflowTransitionRequestCancelledEvent;
use Sulu\Content\Application\WorkflowTransitionRequest\Event\WorkflowTransitionRequestSettledEvent;
use Task\Storage\TaskExecutionRepositoryInterface;
use Task\TaskStatus;

/**
 * Cancels the publish task of a request when the request ends without it, because it was cancelled or because the
 * content was published by hand first.
 *
 * The task stays in the list as aborted, so it is visible what happened to it. Only tasks that have not started are
 * cancelled. The publish of a task itself settles the request, and that task is running by then.
 */
final class PublishAtTaskCanceller
{
    public function __construct(
        private readonly PublishAtTargets $targets,
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly TaskExecutionRepositoryInterface $taskExecutionRepository,
    ) {
    }

    public function __invoke(WorkflowTransitionRequestCancelledEvent|WorkflowTransitionRequestSettledEvent $event): void
    {
        $resourceKey = $event->getResourceKey();
        if (!$this->targets->supports($resourceKey)) {
            return;
        }

        $tasks = $this->taskRepository->findPendingTasks(
            $this->targets->getHandlerClass($resourceKey),
            $this->targets->getEntityClass($resourceKey),
            $event->getResourceId(),
            $event->getLocale(),
        );

        foreach ($tasks as $task) {
            foreach ($this->taskExecutionRepository->findByTaskUuid((string) $task->getTask()?->getUuid()) as $execution) {
                if (TaskStatus::PLANNED !== $execution->getStatus()) {
                    continue;
                }

                $execution->setStatus(TaskStatus::ABORTED);
                $this->taskExecutionRepository->save($execution);
            }
        }
    }
}
