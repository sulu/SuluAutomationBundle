<?php

declare(strict_types=1);

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
use Task\TaskInterface as PHPTaskInterface;

/**
 * Task-Repository implementation for doctrine.
 *
 * @extends EntityRepository<TaskInterface>
 */
class DoctrineTaskRepository extends EntityRepository implements TaskRepositoryInterface
{
    public function create(): TaskInterface
    {
        $class = $this->getEntityName();

        /** @var TaskInterface $entity */
        $entity = new $class();

        return $entity;
    }

    public function save(TaskInterface $task): TaskInterface
    {
        $this->getEntityManager()->persist($task);

        return $task;
    }

    public function remove(TaskInterface $task): TaskInterface
    {
        $this->getEntityManager()->remove($task);

        return $task;
    }

    public function findById(string $id): ?TaskInterface
    {
        /** @var TaskInterface $task */
        $task = $this->find($id);

        return $task;
    }

    public function findByTask(PHPTaskInterface $task): ?TaskInterface
    {
        /** @var TaskInterface|null $result */
        $result = $this->findOneBy(['task' => $task]);

        return $result;
    }

    public function countPendingTasks(string $entityClass, string $entityId, ?string $locale = null): int
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(taskExecution.uuid)')
            ->from(TaskExecution::class, 'taskExecution')
            ->innerJoin('taskExecution.task', 'task')
            ->innerJoin(Task::class, 'auTask', Join::WITH, 'auTask.task = task')
            ->where('auTask.entityClass = :entityClass')
            ->andWhere('auTask.entityId = :entityId')
            ->andWhere('taskExecution.status = :status')
            ->setParameter('entityClass', $entityClass)
            ->setParameter('entityId', $entityId)
            ->setParameter('status', 'planned');

        if (null !== $locale) {
            $queryBuilder->andWhere('auTask.locale = :locale')
                ->setParameter('locale', $locale);
        }

        $query = $queryBuilder->getQuery();

        /** @var int|float|string $result */
        $result = $query->getSingleScalarResult();

        return (int) $result;
    }

    public function revert(TaskInterface $task): TaskInterface
    {
        $this->getEntityManager()->refresh($task);

        return $task;
    }
}
