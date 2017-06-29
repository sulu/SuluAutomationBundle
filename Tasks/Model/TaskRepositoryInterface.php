<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
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
    public function create();

    /**
     * Save given task-entity.
     *
     * @param TaskInterface $task
     *
     * @return TaskInterface
     */
    public function save(TaskInterface $task);

    /**
     * Remove task-entity with given id.
     *
     * @param TaskInterface $task
     */
    public function remove(TaskInterface $task);

    /**
     * Find task-entity with given id.
     *
     * @param int $id
     *
     * @return TaskInterface
     */
    public function findById($id);

    /**
     * Find task-entity with given php-task id.
     *
     * @param int $id
     *
     * @return TaskInterface
     */
    public function findByTaskId($id);

    /**
     * Count tasks which will be called in the future in given entity.
     *
     * @param string $entityClass
     * @param string $entityId
     *
     * @return int
     */
    public function countFutureTasks($entityClass, $entityId, $locale = null);

    /**
     * Revert given task-entity.
     *
     * @param $task
     *
     * @return TaskInterface
     */
    public function revert(TaskInterface $task);
}
