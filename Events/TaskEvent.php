<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Events;

use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for task-events.
 */
abstract class TaskEvent extends Event
{
    /**
     * @var TaskInterface
     */
    private $task;

    public function __construct(TaskInterface $task)
    {
        $this->task = $task;
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }
}
