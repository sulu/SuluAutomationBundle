<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Serializer;

use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use JMS\Serializer\Visitor\SerializationVisitorInterface;
use Sulu\Bundle\AutomationBundle\TaskHandler\AutomationTaskHandlerInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskInterface;
use Task\Handler\TaskHandlerFactoryInterface;
use Task\Storage\TaskExecutionRepositoryInterface;

/**
 * Extend serialization of tasks.
 */
class TaskSerializerSubscriber implements EventSubscriberInterface
{
    /**
     * @var TaskHandlerFactoryInterface
     */
    private $handlerFactory;

    /**
     * @var TaskExecutionRepositoryInterface
     */
    private $taskExecutionRepository;

    public function __construct(
        TaskHandlerFactoryInterface $handlerFactory,
        TaskExecutionRepositoryInterface $taskExecutionRepository
    ) {
        $this->handlerFactory = $handlerFactory;
        $this->taskExecutionRepository = $taskExecutionRepository;
    }

    /**
     * @return mixed[]
     */
    public static function getSubscribedEvents()
    {
        return [
            [
                'event' => Events::POST_SERIALIZE,
                'format' => 'json',
                'method' => 'onTaskSerialize',
            ],
        ];
    }

    /**
     * Append task-name to task-serialization.
     *
     * @throws \Task\Handler\TaskHandlerNotExistsException
     */
    public function onTaskSerialize(ObjectEvent $event): void
    {
        $object = $event->getObject();
        if (!$object instanceof TaskInterface) {
            return;
        }

        $handler = $this->handlerFactory->create($object->getHandlerClass());
        if ($handler instanceof AutomationTaskHandlerInterface) {
            /** @var SerializationVisitorInterface $serializationVisitor */
            $serializationVisitor = $event->getVisitor();
            $serializationVisitor->visitProperty(
                new StaticPropertyMetadata('', 'taskName', $handler->getConfiguration()->getTitle()),
                $handler->getConfiguration()->getTitle()
            );
        }

        $executions = $this->taskExecutionRepository->findByTaskUuid((string) $object->getTaskId());
        if (0 < \count($executions)) {
            /** @var SerializationVisitorInterface $serializationVisitor */
            $serializationVisitor = $event->getVisitor();
            $serializationVisitor->visitProperty(
                new StaticPropertyMetadata('', 'status', $executions[0]->getStatus()),
                $executions[0]->getStatus()
            );
        }
    }
}
