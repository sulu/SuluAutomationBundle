<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Routing\ClassResourceInterface;
use JMS\Serializer\DeserializationContext;
use Sulu\Bundle\AutomationBundle\Admin\AutomationAdmin;
use Sulu\Bundle\AutomationBundle\Entity\Task;
use Sulu\Bundle\AutomationBundle\Exception\TaskNotFoundException;
use Sulu\Bundle\AutomationBundle\TaskHandler\AutomationTaskHandlerInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Manager\TaskManagerInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\FieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\ListBuilderInterface;
use Sulu\Component\Rest\ListBuilder\ListRepresentation;
use Sulu\Component\Rest\RestController;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides api for tasks.
 */
class TaskController extends RestController implements ClassResourceInterface, SecuredControllerInterface
{
    private static $scheduleComparators = [
        'future' => ListBuilderInterface::WHERE_COMPARATOR_GREATER_THAN,
        'past' => ListBuilderInterface::WHERE_COMPARATOR_LESS,
    ];

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
        $factory = $this->get('sulu_core.doctrine_list_builder_factory');

        $listBuilder = $this->prepareListBuilder($fieldDescriptors, $request, $factory->create(Task::class));
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
        $handlerFactory = $this->get('task.handler.factory');
        $handler = $handlerFactory->create($item['handlerClass']);

        if ($handler instanceof AutomationTaskHandlerInterface) {
            $item['taskName'] = $handler->getConfiguration()->getTitle();
        }

        $task = $this->get('task.repository.task')->findByUuid($item['taskId']);
        $executions = $this->get('task.repository.task_execution')->findByTask($task);
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
        $restHelper = $this->get('sulu_core.doctrine_rest_helper');
        $restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);
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
        $manager = $this->getTaskManager();

        return $this->handleView($this->view($manager->findById($id)));
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
            ],
            array_filter($request->request->all())
        );

        $manager = $this->getTaskManager();
        $task = $this->get('serializer')->deserialize(
            json_encode($data),
            Task::class,
            'json',
            DeserializationContext::create()->setGroups(['api'])
        );
        $task = $manager->create($task);

        $this->getEntityManager()->flush($task);

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
                'id' => $id,
                'scheme' => $request->getScheme(),
                'host' => $request->getHost(),
            ],
            array_filter($request->request->all())
        );

        $task = $this->get('serializer')->deserialize(
            json_encode($data),
            Task::class,
            'json',
            DeserializationContext::create()->setGroups(['api'])
        );

        $manager = $this->getTaskManager();
        $task = $manager->update($task);

        $this->getEntityManager()->flush($task);

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
        $manager = $this->getTaskManager();
        $manager->remove($id);

        $this->getEntityManager()->flush();

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
        $manager = $this->getTaskManager();

        $ids = array_filter(explode(',', $request->get('ids')));
        foreach ($ids as $id) {
            $manager->remove($id);
        }

        $this->getEntityManager()->flush();

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
        return $this->get('sulu_core.list_builder.field_descriptor_factory')
            ->getFieldDescriptorForClass(Task::class, [], $type);
    }

    /**
     * Returns task-manager.
     *
     * @return TaskManagerInterface
     */
    private function getTaskManager()
    {
        return $this->get('sulu_automation.tasks.manager');
    }

    /**
     * Returns entity-manager.
     *
     * @return EntityManagerInterface
     */
    private function getEntityManager()
    {
        return $this->get('doctrine.orm.entity_manager');
    }

    /**
     * {@inheritdoc}
     */
    public function getSecurityContext()
    {
        return AutomationAdmin::TASK_SECURITY_CONTEXT;
    }
}
