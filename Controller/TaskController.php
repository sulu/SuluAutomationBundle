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

namespace Sulu\Bundle\AutomationBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\View\ViewHandlerInterface;
use Sulu\Bundle\AutomationBundle\Admin\AutomationAdmin;
use Sulu\Bundle\AutomationBundle\Entity\Task;
use Sulu\Bundle\AutomationBundle\Exception\TaskNotFoundException;
use Sulu\Bundle\AutomationBundle\TaskHandler\AutomationTaskHandlerInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Manager\TaskManagerInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface as AutomationTaskRepositoryInterface;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\Component\Rest\AbstractRestController;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\FieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\ListBuilderInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Task\Handler\TaskHandlerFactoryInterface;
use Task\Storage\TaskExecutionRepositoryInterface;
use Task\Storage\TaskRepositoryInterface;

/**
 * Provides api for tasks.
 */
class TaskController extends AbstractRestController implements SecuredControllerInterface
{
    /**
     * @var string[]
     */
    private static $scheduleComparators = [
        'future' => ListBuilderInterface::WHERE_COMPARATOR_GREATER_THAN,
        'past' => ListBuilderInterface::WHERE_COMPARATOR_LESS,
    ];

    public function __construct(
        ViewHandlerInterface $viewHandler,
        protected TokenStorageInterface $tokenStorage,
        protected DoctrineListBuilderFactoryInterface $doctrineListBuilderFactory,
        protected TaskHandlerFactoryInterface $taskHandlerFactory,
        protected TaskRepositoryInterface $taskRepository,
        protected TaskExecutionRepositoryInterface $taskExecutionRepository,
        protected RestHelperInterface $doctrineRestHelper,
        protected TaskManagerInterface $taskManager,
        protected EntityManagerInterface $entityManager,
        protected FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        protected AutomationTaskRepositoryInterface $automationTaskRepository,
        protected TranslatorInterface $translator,
    ) {
        parent::__construct($viewHandler, $tokenStorage);
    }

    /**
     * Returns list of tasks.
     */
    public function cgetAction(Request $request): Response
    {
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(Task::RESOURCE_KEY);

        $listBuilder = $this->prepareListBuilder($fieldDescriptors, $request, $this->doctrineListBuilderFactory->create(Task::class));
        /** @var array<string, array<string>> $result */
        $result = $this->executeListBuilder($fieldDescriptors, $request, $listBuilder);
        /** @var User $user */
        $user = $this->tokenStorage->getToken()?->getUser();
        $userLocale = $user->getLocale();

        for ($i = 0; $i < \count($result); ++$i) {
            $result[$i] = $this->extendResponseItem($result[$i], $userLocale);
        }

        return $this->handleView(
            $this->view(
                new PaginatedRepresentation(
                    $result,
                    'tasks',
                    (int) $listBuilder->getCurrentPage(),
                    (int) $listBuilder->getLimit() ?: $listBuilder->count(),
                    $listBuilder->count(),
                ),
            ),
        );
    }

    /**
     * Extends response item with task-name and status.
     *
     * @param string[] $item
     *
     * @return string[]
     *
     * @throws \Task\Handler\TaskHandlerNotExistsException
     */
    private function extendResponseItem(array $item, string $userLocale): array
    {
        $handlerFactory = $this->taskHandlerFactory;
        $handler = $handlerFactory->create($item['handlerClass']);

        if ($handler instanceof AutomationTaskHandlerInterface) {
            $item['taskName'] = $this->translator->trans($handler->getConfiguration()->getTitle(), [], 'admin', $userLocale);
        }

        $task = $this->taskRepository->findByUuid($item['taskId']);
        $executions = $this->taskExecutionRepository->findByTask($task);
        if (0 < \count($executions)) {
            $item['status'] = $executions[0]->getStatus();
        }

        unset($item['taskId']);

        return $item;
    }

    /**
     * Prepares list-builder.
     *
     * @param FieldDescriptorInterface[] $fieldDescriptors
     */
    private function prepareListBuilder(array $fieldDescriptors, Request $request, ListBuilderInterface $listBuilder): ListBuilderInterface
    {
        $this->doctrineRestHelper->initializeListBuilder($listBuilder, $fieldDescriptors);
        $listBuilder->addSelectField($fieldDescriptors['handlerClass']);
        $listBuilder->addSelectField($fieldDescriptors['taskId']);

        /** @var string|null $entityClass */
        $entityClass = $request->query->getString('entityClass');
        if ($entityClass) {
            $listBuilder->where($fieldDescriptors['entityClass'], $entityClass);
        }

        /** @var string|null $entityId */
        $entityId = $request->query->getString('entityId');
        if ($entityId) {
            $listBuilder->where($fieldDescriptors['entityId'], $entityId);
        }

        /** @var string|null $locale */
        $locale = $request->query->getString('locale');
        if ($locale) {
            $listBuilder->where($fieldDescriptors['locale'], $locale);
        }

        /** @var string|null $handlerClasses */
        $handlerClasses = $request->query->getString('handlerClass');
        if ($handlerClasses) {
            $listBuilder->in($fieldDescriptors['handlerClass'], \explode(',', $handlerClasses));
        }

        /** @var string|null $schedule */
        $schedule = $request->query->getString('schedule');
        if ($schedule && \array_key_exists($schedule, self::$scheduleComparators)
        ) {
            $listBuilder->where($fieldDescriptors['schedule'], (new \DateTimeImmutable())->format('Y-m-d\TH:i:s'), self::$scheduleComparators[$schedule]);
        }

        return $listBuilder;
    }

    /**
     * Executes given list-builder and returns result.
     *
     * @param FieldDescriptorInterface[] $fieldDescriptors
     *
     * @return mixed[]
     */
    private function executeListBuilder(array $fieldDescriptors, Request $request, ListBuilderInterface $listBuilder): array
    {
        /** @var string|null $idsParameter */
        $idsParameter = $request->query->getString('ids');
        if (!$idsParameter) {
            return $listBuilder->execute();
        }

        $ids = \array_filter(\explode(',', $idsParameter));
        $listBuilder->in($fieldDescriptors['id'], $ids);

        $sorted = [];
        foreach ($listBuilder->execute() as $item) {
            $sorted[\array_search($item['id'], $ids, true)] = $item;
        }

        \ksort($sorted);

        return \array_values($sorted);
    }

    /**
     * Returns task for given id.
     *
     * @throws TaskNotFoundException
     */
    public function getAction(string $id): Response
    {
        return $this->handleView($this->view($this->taskManager->findById($id)));
    }

    /**
     * Returns count of pending tasks for entity with given id.
     */
    public function getCountAction(Request $request): Response
    {
        $entityClass = (string) $request->query->get('entityClass');
        $entityId = (string) $request->query->get('entityId');
        $locale = $request->query->has('locale') ? (string) $request->query->get('locale') : null;

        return $this->handleView($this->view([
            'count' => $this->automationTaskRepository->countPendingTasks($entityClass, $entityId, $locale),
        ]));
    }

    /**
     * Create new task.
     */
    public function postAction(Request $request): Response
    {
        $task = new Task();
        $task->setScheme($request->getScheme());
        $task->setHost($request->getHost());
        $task->setEntityId((string) $request->query->get('entityId'));
        $task->setEntityClass((string) $request->query->get('entityClass'));
        $task->setLocale((string) $request->query->get('locale'));
        $task->setHandlerClass((string) $request->request->get('handlerClass'));
        $task->setSchedule(new \DateTimeImmutable((string) $request->request->get('schedule')));

        $this->taskManager->create($task);

        $this->entityManager->flush();

        return $this->handleView($this->view($task));
    }

    /**
     * Update task with given id.
     */
    public function putAction(string $id, Request $request): Response
    {
        /** @var Task $task */
        $task = $this->taskManager->findById($id);
        $task->setScheme($request->getScheme());
        $task->setHost($request->getHost());
        $task->setLocale((string) $request->query->get('locale'));
        $task->setHandlerClass((string) $request->request->get('handlerClass'));
        $task->setSchedule(new \DateTimeImmutable((string) $request->request->get('schedule')));

        $task = $this->taskManager->update($task);

        $this->entityManager->flush();

        return $this->handleView($this->view($task));
    }

    /**
     * Removes task with given id.
     */
    public function deleteAction(string $id): Response
    {
        $manager = $this->taskManager;
        $manager->remove($id);

        $this->entityManager->flush();

        return $this->handleView($this->view());
    }

    /**
     * Removes multiple tasks identified by ids parameter.
     */
    public function cdeleteAction(Request $request): Response
    {
        /** @var string $idsParameter */
        $idsParameter = $request->query->getString('ids');
        $ids = \array_filter(\explode(',', $idsParameter));
        foreach ($ids as $id) {
            $this->taskManager->remove($id);
        }

        $this->entityManager->flush();

        return $this->handleView($this->view());
    }

    /**
     * @return string
     */
    public function getSecurityContext()
    {
        return AutomationAdmin::SECURITY_CONTEXT;
    }
}
