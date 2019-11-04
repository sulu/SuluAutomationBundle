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
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface;

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

        return new $class();
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

        return (int) $query->getSingleScalarResult();
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
