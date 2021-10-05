<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tasks\Model;

/**
 * Interface for task-repository.
 */
interface TaskRepositoryInterface
{
    /**
     * Create a new task-entity.
     */
    public function create(): TaskInterface;

    /**
     * Save given task-entity.
     */
    public function save(TaskInterface $task): TaskInterface;

    /**
     * Remove task-entity with given id.
     */
    public function remove(TaskInterface $task): TaskInterface;

    /**
     * Find task-entity with given id.
     */
    public function findById(string $id): ?TaskInterface;

    /**
     * Find task-entity with given php-task id.
     */
    public function findByTaskId(string $id): ?TaskInterface;

    /**
     * @deprecated
     *
     * Count tasks which have a schedule date in the future
     */
    public function countFutureTasks(string $entityClass, string $entityId, string $locale = null): int;

    /**
     * Count pending tasks which have not been executed yet.
     */
    public function countPendingTasks(string $entityClass, string $entityId, string $locale = null): int;

    /**
     * Revert given task-entity.
     */
    public function revert(TaskInterface $task): TaskInterface;
}
