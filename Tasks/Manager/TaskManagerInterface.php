<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tasks\Manager;

use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskInterface;

/**
 * Interface for task-manager.
 */
interface TaskManagerInterface
{
    /**
     * Create a new task-entity.
     */
    public function create(TaskInterface $task): TaskInterface;

    /**
     * Update given task-entity.
     */
    public function update(TaskInterface $task): TaskInterface;

    /**
     * Removes given task-entity.
     */
    public function remove(string $id): void;

    /**
     * Find task identified by given id.
     */
    public function findById(string $id): TaskInterface;
}
