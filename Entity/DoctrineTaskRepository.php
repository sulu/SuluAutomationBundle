<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface;
use Task\TaskBundle\Entity\TaskExecution;

/**
 * Task-Repository implementation for doctrine.
 */
class DoctrineTaskRepository extends EntityRepository implements TaskRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(): TaskInterface
    {
        $class = $this->_entityName;

        /** @var TaskInterface $entity */
        $entity = new $class();

        return $entity;
    }

    /**
     * {@inheritdoc}
     */
    public function save(TaskInterface $task): TaskInterface
    {
        $this->_em->persist($task);

        return $task;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(TaskInterface $task): TaskInterface
    {
        $this->_em->remove($task);

        return $task;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(string $id): ?TaskInterface
    {
        /** @var TaskInterface $task */
        $task = $this->find($id);

        return $task;
    }

    /**
     * {@inheritdoc}
     */
    public function findByTaskId(string $id): ?TaskInterface
    {
        /** @var TaskInterface $task */
        $task = $this->findOneBy(['taskId' => $id]);

        return $task;
    }

    /**
     * {@inheritdoc}
     */
    public function countFutureTasks(string $entityClass, string $entityId, string $locale = null): int
    {
        $queryBuilder = $this->createQueryBuilder('task')
            ->select('COUNT(task.id)')
            ->where('task.entityClass = :entityClass')
            ->andWhere('task.entityId = :entityId')
            ->andWhere('task.schedule >= :schedule')
            ->setParameter('entityClass', $entityClass)
            ->setParameter('entityId', $entityId)
            ->setParameter('schedule', new \DateTime());

        if (null != $locale) {
            $queryBuilder->andWhere('task.locale = :locale')
                ->setParameter('locale', $locale);
        }

        $query = $queryBuilder->getQuery();

        /** @var int|float|string $result */
        $result = $query->getSingleScalarResult();

        return (int)$result;
    }

    /**
     * {@inheritdoc}
     */
    public function countPendingTasks(string $entityClass, string $entityId, string $locale = null): int
    {
        $queryBuilder = $this->_em->createQueryBuilder()
            ->select('COUNT(taskExecution.uuid)')
            ->from(TaskExecution::class, 'taskExecution')
            ->innerJoin('taskExecution.task', 'task')
            ->innerJoin(Task::class, 'auTask', Join::WITH, 'auTask.taskId = task.uuid')
            ->where('auTask.entityClass = :entityClass')
            ->andWhere('auTask.entityId = :entityId')
            ->andWhere('taskExecution.status = :status')
            ->setParameter('entityClass', $entityClass)
            ->setParameter('entityId', $entityId)
            ->setParameter('status', 'planned');

        if (null != $locale) {
            $queryBuilder->andWhere('auTask.locale = :locale')
                ->setParameter('locale', $locale);
        }

        $query = $queryBuilder->getQuery();

        /** @var int|float|string $result */
        $result = $query->getSingleScalarResult();

        return (int)$result;
    }

    /**
     * {@inheritdoc}
     */
    public function revert(TaskInterface $task): TaskInterface
    {
        $this->_em->refresh($task);

        return $task;
    }
}
