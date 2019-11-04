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
     *
     * @return TaskInterface
     */
    public function create(): TaskInterface;

    /**
     * Save given task-entity.
     *
     * @param TaskInterface $task
     *
     * @return TaskInterface
     */
    public function save(TaskInterface $task): TaskInterface;

    /**
     * Remove task-entity with given id.
     *
     * @param TaskInterface $task
     *
     * @return TaskInterface
     */
    public function remove(TaskInterface $task): TaskInterface;

    /**
     * Find task-entity with given id.
     *
     * @param string $id
     *
     * @return TaskInterface|null
     */
    public function findById(string $id): ?TaskInterface;

    /**
     * Find task-entity with given php-task id.
     *
     * @param string $id
     *
     * @return TaskInterface|null
     */
    public function findByTaskId(string $id): ?TaskInterface;

    /**
     * Count tasks which will be called in the future in given entity.
     *
     * @param string $entityClass
     * @param string $entityId
     * @param string|null $locale
     *
     * @return int
     */
    public function countFutureTasks(string $entityClass, string $entityId, string $locale = null): int;

    /**
     * Revert given task-entity.
     *
     * @param TaskInterface $task
     *
     * @return TaskInterface
     */
    public function revert(TaskInterface $task): TaskInterface;
}
