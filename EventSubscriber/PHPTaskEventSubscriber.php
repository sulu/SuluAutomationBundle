<?php

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

    /**
     * @param RequestStack $requestStack
     * @param TaskRepositoryInterface $taskRepository
     */
    public function __construct(RequestStack $requestStack, TaskRepositoryInterface $taskRepository)
    {
        $this->requestStack = $requestStack;
        $this->taskRepository = $taskRepository;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            Events::TASK_BEFORE => ['pushRequest'],
            Events::TASK_AFTER => ['popRequest'],
        ];
    }

    /**
     * Create and push new request to requests-stack.
     *
     * @param TaskEvent $event
     */
    public function pushRequest(TaskEvent $event)
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
            ['SERVER_NAME' => $task->getHost(), 'HTTPS' => $task->getScheme() === 'http' ? 'off' : 'on']
        );

        $this->requestStack->push($request);
    }

    /**
     * Pop request from request stack.
     *
     * @param TaskEvent $event
     */
    public function popRequest(TaskEvent $event)
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || $request->attributes->get('_task_id') !== $event->getTask()->getUuid()) {
            // current request was not created for current task

            return;
        }

        $this->requestStack->pop();
    }
}
