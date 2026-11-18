<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tests\Functional\RequestForPublishing;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\AutomationBundle\Entity\Task;
use Sulu\Bundle\AutomationBundle\TaskHandler\PagePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\Tasks\Manager\TaskManagerInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Application\WorkflowTransitionRequest\Event\WorkflowTransitionRequestCancelledEvent;
use Sulu\Content\Application\WorkflowTransitionRequest\Event\WorkflowTransitionRequestSettledEvent;
use Sulu\Content\Domain\Model\WorkflowTransitionRequest\WorkflowTransitionRequest;
use Sulu\Page\Domain\Model\PageInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Task\TaskBundle\Entity\TaskExecution;
use Task\TaskStatus;

/**
 * Runs the real event, task manager and repositories, so the listener is proven to be wired and to find the task.
 */
class PublishAtTaskCancellerTest extends SuluTestCase
{
    private EntityManagerInterface $entityManager;

    private TaskManagerInterface $taskManager;

    private TaskRepositoryInterface $taskRepository;

    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        // a fresh kernel per test, because the scheduler of php-task keeps the entities of the test before
        self::ensureKernelShutdown();
        self::bootKernel();
        $this->purgeDatabase();
        $container = $this->getContainer();
        $container->get('security.token_storage')->setToken(null);
        $this->entityManager = $container->get('doctrine.orm.entity_manager');
        $this->taskManager = $container->get('sulu_automation.tasks.manager');
        $this->taskRepository = $container->get('sulu.repository.task');
        $this->eventDispatcher = $container->get('event_dispatcher');
    }

    public function testCancellingTheRequestAbortsItsPublishTask(): void
    {
        $task = $this->createPublishTask('page-1');
        $this->assertCount(1, $this->findPending('page-1'));

        $this->eventDispatcher->dispatch(new WorkflowTransitionRequestCancelledEvent($this->createRequest('page-1')));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertCount(0, $this->findPending('page-1'));
        $this->assertSame(TaskStatus::ABORTED, $this->getStatus($task));
        $this->assertNotNull($this->taskRepository->findById($task->getId()), 'The task stays in the list.');
    }

    public function testPublishingByHandAbortsItsPublishTask(): void
    {
        $task = $this->createPublishTask('page-1');

        $this->eventDispatcher->dispatch(new WorkflowTransitionRequestSettledEvent($this->createRequest('page-1')));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertCount(0, $this->findPending('page-1'));
        $this->assertSame(TaskStatus::ABORTED, $this->getStatus($task));
    }

    public function testTheTaskOfAnotherPageStays(): void
    {
        $this->createPublishTask('page-1');
        $other = $this->createPublishTask('page-2');

        $this->eventDispatcher->dispatch(new WorkflowTransitionRequestCancelledEvent($this->createRequest('page-1')));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertCount(0, $this->findPending('page-1'));
        $this->assertCount(1, $this->findPending('page-2'));
        $this->assertSame(TaskStatus::PLANNED, $this->getStatus($other));
    }

    /**
     * The publish of the task settles the request itself, so it must not abort the task that is running it.
     */
    public function testTheTaskThatIsRunningStays(): void
    {
        $task = $this->createPublishTask('page-1');
        $this->entityManager->createQueryBuilder()
            ->update(TaskExecution::class, 'execution')
            ->set('execution.status', ':status')
            ->where('execution.task = :task')
            ->setParameter('status', 'running')
            ->setParameter('task', $task->getTask())
            ->getQuery()
            ->execute();
        $this->entityManager->clear();

        $this->eventDispatcher->dispatch(new WorkflowTransitionRequestSettledEvent($this->createRequest('page-1')));
        $this->entityManager->flush();

        $this->assertSame(TaskStatus::RUNNING, $this->getStatus($task));
    }

    private function getStatus(Task $task): string
    {
        $executions = $this->getContainer()->get('task.storage.task_execution')->findByTaskUuid((string) $task->getTask()?->getUuid());

        return $executions[0]->getStatus();
    }

    /**
     * @return list<Task>
     */
    private function findPending(string $pageId): array
    {
        return $this->taskRepository->findPendingTasks(PagePublishTaskHandler::class, PageInterface::class, $pageId, 'en');
    }

    private function createPublishTask(string $pageId): Task
    {
        $task = new Task();
        $task->setHandlerClass(PagePublishTaskHandler::class);
        $task->setEntityClass(PageInterface::class);
        $task->setEntityId($pageId);
        $task->setLocale('en');
        $task->setSchedule(new \DateTimeImmutable('+1 week'));
        $task->setScheme('https');
        $task->setHost('localhost');

        $this->taskManager->create($task);
        $this->entityManager->flush();
        // the task is scheduled in a request of its own, so the removal starts from an empty unit of work
        $this->entityManager->clear();

        return $task;
    }

    private function createRequest(string $pageId): WorkflowTransitionRequest
    {
        return new WorkflowTransitionRequest('pages', $pageId, 'en', 'review');
    }
}
