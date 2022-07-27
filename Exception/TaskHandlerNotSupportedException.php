<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Exception;

use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskInterface;
use Task\Handler\TaskHandlerInterface;

/**
 * Will be thrown if task-handler is not supported.
 */
class TaskHandlerNotSupportedException extends \Exception
{
    /**
     * @var TaskHandlerInterface
     */
    private $taskHandler;

    /**
     * @var TaskInterface
     */
    private $task;

    public function __construct(TaskHandlerInterface $taskHandler, TaskInterface $task)
    {
        parent::__construct(\sprintf('Task-Handler "%s" is not supported.', \get_class($taskHandler)));

        $this->taskHandler = $taskHandler;
        $this->task = $task;
    }

    public function getTaskHandler(): TaskHandlerInterface
    {
        return $this->taskHandler;
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }
}
