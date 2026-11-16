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

namespace Sulu\Bundle\AutomationBundle\EventSubscriber;

use Sulu\Bundle\AutomationBundle\Security\TaskPermissionChecker;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Task\Event\Events;
use Task\Event\TaskEvent;

/**
 * Checks right before a task runs that its user still has the permission its handler needs.
 */
class TaskPermissionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private TaskPermissionChecker $permissionChecker,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::TASK_BEFORE => ['checkPermission', 10],
        ];
    }

    public function checkPermission(TaskEvent $event): void
    {
        if (!$this->taskRepository->findByTask($event->getTask())) {
            return;
        }

        /** @var mixed $workload */
        $workload = $event->getTask()->getWorkload();
        if (!\is_array($workload) || !isset($workload['userId'])) {
            trigger_deprecation('sulu/automation-bundle', '3.0.2', 'Running a task without a "userId" in its workload is deprecated, it skips the "live" permission check. Schedule the task again to add it.');

            return;
        }

        $this->permissionChecker->checkUser(
            $workload['userId'],
            $event->getTask()->getHandlerClass(),
            (string) $workload['class'],
            (string) $workload['id'],
            (string) $workload['locale'],
        );
    }
}
