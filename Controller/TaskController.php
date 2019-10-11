<?php

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
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\ViewHandlerInterface;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializerInterface;
use Sulu\Bundle\AutomationBundle\Admin\AutomationAdmin;
use Sulu\Bundle\AutomationBundle\Entity\Task;
use Sulu\Bundle\AutomationBundle\Exception\TaskNotFoundException;
use Sulu\Bundle\AutomationBundle\TaskHandler\AutomationTaskHandlerInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Manager\TaskManagerInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskInterface;
use Sulu\Component\Rest\AbstractRestController;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\FieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\ListBuilderInterface;
use Sulu\Component\Rest\ListBuilder\ListRepresentation;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Task\Handler\TaskHandlerFactoryInterface;
use Task\Storage\TaskExecutionRepositoryInterface;
use Task\Storage\TaskRepositoryInterface;

/**
 * Provides api for tasks.
 */
class TaskController extends AbstractRestController implements ClassResourceInterface, SecuredControllerInterface
{
    private static $scheduleComparators = [
        'future' => ListBuilderInterface::WHERE_COMPARATOR_GREATER_THAN,
        'past' => ListBuilderInterface::WHERE_COMPARATOR_LESS,
    ];

    /**
     * @var ListBuilderInterface
     */
    protected $doctrineListBuilderFactory;

    /**
     * @var TaskHandlerFactoryInterface
     */
    protected $taskHandlerFactory;

    /**
     * @var TaskRepositoryInterface
     */
    protected $taskRepository;

    /**
     * @var TaskExecutionRepositoryInterface
     */
    protected $taskExecutionRepository;

    /**
     * @var RestHelperInterface
     */
    protected $restHelper;

    /**
     * @var TaskManagerInterface
     */
    protected $taskManager;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var FieldDescriptorFactoryInterface
     */
    protected $fieldDescriptorFactory;

    public function __construct(
        ViewHandlerInterface $viewHandler,
        TokenStorageInterface $tokenStorage,
        DoctrineListBuilderFactoryInterface $doctrineListBuilderFactory,
        TaskHandlerFactoryInterface $taskHandlerFactory,
        TaskRepositoryInterface $taskRepository,
        TaskExecutionRepositoryInterface $taskExecutionRepository,
        RestHelperInterface $doctrineRestHelper,
        TaskManagerInterface $taskManager,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        FieldDescriptorFactoryInterface $fieldDescriptorFactory
    ) {
        parent::__construct($viewHandler, $tokenStorage);
        $this->doctrineListBuilderFactory = $doctrineListBuilderFactory;
        $this->taskHandlerFactory = $taskHandlerFactory;
        $this->taskRepository = $taskRepository;
        $this->taskExecutionRepository = $taskExecutionRepository;
        $this->restHelper = $doctrineRestHelper;
        $this->taskManager = $taskManager;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->fieldDescriptorFactory = $fieldDescriptorFactory;
    }

    /**
     * Returns fields for tasks.
     *
     * @return Response
     */
    public function cgetFieldsAction()
    {
        return $this->handleView($this->view(array_values($this->getFieldDescriptors())));
    }

    /**
     * Returns list of tasks.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function cgetAction(Request $request)
    {
        $fieldDescriptors = $this->getFieldDescriptors(DoctrineFieldDescriptorInterface::class);

        $listBuilder = $this->prepareListBuilder($fieldDescriptors, $request, $this->doctrineListBuilderFactory->create(Task::class));
        $result = $this->executeListBuilder($fieldDescriptors, $request, $listBuilder);

        for ($i = 0; $i < count($result); ++$i) {
            $result[$i] = $this->extendResponseItem($result[$i]);
        }

        return $this->handleView(
            $this->view(
                new ListRepresentation(
                    $result,
                    'tasks',
                    'get_tasks',
                    $request->query->all(),
                    $listBuilder->getCurrentPage(),
                    $listBuilder->getLimit(),
                    $listBuilder->count()
                )
            )
        );
    }

    /**
     * Extends response item with task-name and status.
     *
     * @param array $item
     *
     * @return array
     */
    private function extendResponseItem($item)
    {
        $handlerFactory = $this->taskHandlerFactory;
        $handler = $handlerFactory->create($item['handlerClass']);

        if ($handler instanceof AutomationTaskHandlerInterface) {
            $item['taskName'] = $handler->getConfiguration()->getTitle();
        }

        $task = $this->taskRepository->findByUuid($item['taskId']);
        $executions = $this->taskExecutionRepository->findByTask($task);
        if (0 < count($executions)) {
            $item['status'] = $executions[0]->getStatus();
        }

        unset($item['taskId']);

        return $item;
    }

    /**
     * Prepares list-builder.
     *
     * @param FieldDescriptorInterface[] $fieldDescriptors
     * @param Request $request
     * @param ListBuilderInterface $listBuilder
     *
     * @return ListBuilderInterface
     */
    private function prepareListBuilder(array $fieldDescriptors, Request $request, ListBuilderInterface $listBuilder)
    {
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);
        $listBuilder->addSelectField($fieldDescriptors['handlerClass']);
        $listBuilder->addSelectField($fieldDescriptors['taskId']);

        if ($entityClass = $request->get('entity-class')) {
            $listBuilder->where($fieldDescriptors['entityClass'], $entityClass);
        }

        if ($entityId = $request->get('entity-id')) {
            $listBuilder->where($fieldDescriptors['entityId'], $entityId);
        }

        if ($locale = $request->get('locale')) {
            $listBuilder->where($fieldDescriptors['locale'], $locale);
        }

        if ($handlerClasses = $request->get('handler-class')) {
            $handlerClassList = explode(',', $handlerClasses);
            if (0 < count($handlerClassList)) {
                $listBuilder->in($fieldDescriptors['handlerClass'], $handlerClassList);
            }
        }

        if (null !== ($schedule = $request->get('schedule'))
            && in_array($schedule, array_keys(self::$scheduleComparators))
        ) {
            $listBuilder->where($fieldDescriptors['schedule'], new \DateTime(), self::$scheduleComparators[$schedule]);
        }

        return $listBuilder;
    }

    /**
     * Executes given list-builder and returns result.
     *
     * @param FieldDescriptorInterface[] $fieldDescriptors
     * @param Request $request
     * @param ListBuilderInterface $listBuilder
     *
     * @return array
     */
    private function executeListBuilder(array $fieldDescriptors, Request $request, ListBuilderInterface $listBuilder)
    {
        if (null === ($idsParameter = $request->get('ids'))) {
            return $listBuilder->execute();
        }

        $ids = array_filter(explode(',', $request->get('ids')));
        $listBuilder->in($fieldDescriptors['id'], $ids);

        $sorted = [];
        foreach ($listBuilder->execute() as $item) {
            $sorted[array_search($item['id'], $ids)] = $item;
        }

        ksort($sorted);

        return array_values($sorted);
    }

    /**
     * Returns task for given id.
     *
     * @param int $id
     *
     * @return Response
     *
     * @throws TaskNotFoundException
     */
    public function getAction($id)
    {
        return $this->handleView($this->view($this->taskManager->findById($id)));
    }

    /**
     * Create new task.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function postAction(Request $request)
    {
        $data = array_merge(
            [
                'scheme' => $request->getScheme(),
                'host' => $request->getHost(),
                'entityId' => $request->get('entity-id'),
                'entityClass' => $request->get('entity-class'),
                'locale' => $request->get('locale'),
            ],
            array_filter($request->request->all())
        );

        /** @var TaskInterface $task */
        $task = $this->serializer->deserialize(
            json_encode($data),
            Task::class,
            'json',
            DeserializationContext::create()->setGroups(['api'])
        );

        $task->setSchedule(date_create_from_format('Y-m-d:H:i:s', $data['date'] . ':' . $data['time']));
        $this->taskManager->create($task);

        $this->entityManager->flush();

        return $this->handleView($this->view($task));
    }

    /**
     * Update task with given id.
     *
     * @param int $id
     * @param Request $request
     *
     * @return Response
     */
    public function putAction($id, Request $request)
    {
        $data = array_merge(
            [
                'scheme' => $request->getScheme(),
                'host' => $request->getHost(),
                'entityId' => $request->get('entity-id'),
                'entityClass' => $request->get('entity-class'),
                'locale' => $request->get('locale'),
            ],
            array_filter($request->request->all())
        );

        $task = $this->serializer->deserialize(
            json_encode($data),
            Task::class,
            'json',
            DeserializationContext::create()->setGroups(['api'])
        );

        $task->setSchedule(date_create_from_format('Y-m-d:H:i:s', $data['date'] . ':' . $data['time']));
        $task = $this->taskManager->update($task);

        $this->entityManager->flush();

        return $this->handleView($this->view($task));
    }

    /**
     * Removes task with given id.
     *
     * @param int $id
     *
     * @return Response
     */
    public function deleteAction($id)
    {
        $manager = $this->taskManager;
        $manager->remove($id);

        $this->entityManager->flush();

        return $this->handleView($this->view());
    }

    /**
     * Removes multiple tasks identified by ids parameter.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function cdeleteAction(Request $request)
    {
        $ids = array_filter(explode(',', $request->get('ids')));
        foreach ($ids as $id) {
            $this->taskManager->remove($id);
        }

        $this->entityManager->flush();

        return $this->handleView($this->view());
    }

    /**
     * Returns field-descriptors for task-entity.
     *
     * @param string $type
     *
     * @return FieldDescriptorInterface[]
     */
    private function getFieldDescriptors($type = null)
    {
        return $this->fieldDescriptorFactory->getFieldDescriptors(Task::RESOURCE_KEY);
    }

    /**
     * {@inheritdoc}
     */
    public function getSecurityContext()
    {
        return AutomationAdmin::SECURITY_CONTEXT;
    }
}
