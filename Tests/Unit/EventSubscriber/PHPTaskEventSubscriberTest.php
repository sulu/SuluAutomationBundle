<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tests\Unit\EventSubscriber;

use Prophecy\Argument;
use Sulu\Bundle\AutomationBundle\EventSubscriber\PHPTaskEventSubscriber;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Task\Event\Events;
use Task\Event\TaskEvent;
use Task\TaskInterface as PHPTaskInterface;

/**
 * Unit tests for php-task event-subscriber.
 */
class PHPTaskEventSubscriberTest extends \PHPUnit_Framework_TestCase
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
     * @var PHPTaskEventSubscriber
     */
    private $eventSubscriber;

    protected function setUp()
    {
        $this->requestStack = $this->prophesize(RequestStack::class);
        $this->taskRepository = $this->prophesize(TaskRepositoryInterface::class);

        $this->eventSubscriber = new PHPTaskEventSubscriber(
            $this->requestStack->reveal(), $this->taskRepository->reveal()
        );
    }

    public function testGetSubscribedEvents()
    {
        $eventNames = [Events::TASK_BEFORE, Events::TASK_AFTER];
        foreach ($this->eventSubscriber->getSubscribedEvents() as $eventName => $functions) {
            $this->assertTrue(in_array($eventName, $eventNames));

            foreach ($functions as $function) {
                $this->assertTrue(method_exists($this->eventSubscriber, $function));
            }
        }
    }

    public function testPushRequest()
    {
        $event = $this->createEvent();

        $task = $this->prophesize(TaskInterface::class);
        $task->getScheme()->willReturn('http');
        $task->getHost()->willReturn('sulu.io');
        $this->taskRepository->findByTaskId($event->getTask()->getUuid())->willReturn($task->reveal());

        $this->requestStack->push(
            Argument::that(
                function (Request $request) use ($event) {
                    return $request->getScheme() === 'http'
                           && $request->getHost() === 'sulu.io'
                           && $request->attributes->get('_task_id') === $event->getTask()->getUuid();
                }
            )
        )->shouldBeCalled();

        $this->eventSubscriber->pushRequest($event);
    }

    public function testPushRequestHttps()
    {
        $event = $this->createEvent();

        $task = $this->prophesize(TaskInterface::class);
        $task->getScheme()->willReturn('https');
        $task->getHost()->willReturn('sulu.io');
        $this->taskRepository->findByTaskId($event->getTask()->getUuid())->willReturn($task->reveal());

        $this->requestStack->push(
            Argument::that(
                function (Request $request) use ($event) {
                    return $request->getScheme() === 'https'
                           && $request->getHost() === 'sulu.io'
                           && $request->attributes->get('_task_id') === $event->getTask()->getUuid();
                }
            )
        )->shouldBeCalled();

        $this->eventSubscriber->pushRequest($event);
    }

    public function testPushRequestNotManaged()
    {
        $event = $this->createEvent();

        $this->taskRepository->findByTaskId($event->getTask()->getUuid())->willReturn(null);

        $this->requestStack->push(Argument::any())->shouldNotBeCalled();

        $this->eventSubscriber->pushRequest($event);
    }

    public function testPopRequest()
    {
        $event = $this->createEvent();

        $request = new Request([], [], ['_task_id' => $event->getTask()->getUuid()]);
        $this->requestStack->getCurrentRequest()->willReturn($request);

        $this->requestStack->pop()->shouldBeCalled();

        $this->eventSubscriber->popRequest($event);
    }

    public function testPopRequestNoTaskId()
    {
        $event = $this->createEvent();

        $request = new Request();
        $this->requestStack->getCurrentRequest()->willReturn($request);

        $this->requestStack->pop()->shouldNotBeCalled();

        $this->eventSubscriber->popRequest($event);
    }

    protected function createEvent()
    {
        $task = $this->prophesize(PHPTaskInterface::class);
        $task->getUuid()->willReturn('123-123-123');

        $event = $this->prophesize(TaskEvent::class);
        $event->getTask()->willReturn($task->reveal());

        return $event->reveal();
    }
}
