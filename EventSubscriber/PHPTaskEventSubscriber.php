<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\EventSubscriber;

use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Task\Event\Events;
use Task\Event\TaskEvent;

/**
 * Fake-Request handling for tasks.
 */
class PHPTaskEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var TaskRepositoryInterface
     */
    private $taskRepository;

    public function __construct(RequestStack $requestStack, TaskRepositoryInterface $taskRepository)
    {
        $this->requestStack = $requestStack;
        $this->taskRepository = $taskRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::TASK_BEFORE => ['pushRequest'],
            Events::TASK_AFTER => ['popRequest'],
        ];
    }

    /**
     * Create and push new request to requests-stack.
     */
    public function pushRequest(TaskEvent $event): void
    {
        $task = $this->taskRepository->findByTaskId($event->getTask()->getUuid());
        if (!$task) {
            // current task is not managed by this bundle

            return;
        }

        $request = new Request(
            [],
            [],
            ['_task_id' => $event->getTask()->getUuid()],
            [],
            [],
            ['SERVER_NAME' => $task->getHost(), 'HTTPS' => 'http' === $task->getScheme() ? 'off' : 'on']
        );

        $this->requestStack->push($request);
    }

    /**
     * Pop request from request stack.
     */
    public function popRequest(TaskEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || $request->attributes->get('_task_id') !== $event->getTask()->getUuid()) {
            // current request was not created for current task

            return;
        }

        $this->requestStack->pop();
    }
}
