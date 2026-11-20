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

namespace Sulu\Bundle\AutomationBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\View\ViewHandlerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AutomationBundle\Controller\TaskController;
use Sulu\Bundle\AutomationBundle\Entity\Task;
use Sulu\Bundle\AutomationBundle\Exception\TaskNotFoundException;
use Sulu\Bundle\AutomationBundle\Security\TaskPermissionChecker;
use Sulu\Bundle\AutomationBundle\Tasks\Manager\TaskManagerInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface as AutomationTaskRepositoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\RestHelperInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Task\Execution\TaskExecutionInterface;
use Task\Handler\TaskHandlerFactoryInterface;
use Task\Storage\TaskExecutionRepositoryInterface;
use Task\Storage\TaskRepositoryInterface;
use Task\TaskInterface as PHPTaskInterface;
use Task\TaskStatus;

class TaskControllerPermissionTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<TaskManagerInterface> */
    private ObjectProphecy $taskManager;

    /** @var ObjectProphecy<ViewHandlerInterface> */
    private ObjectProphecy $viewHandler;

    /** @var ObjectProphecy<TaskPermissionChecker> */
    private ObjectProphecy $permissionChecker;

    /** @var ObjectProphecy<AutomationTaskRepositoryInterface> */
    private ObjectProphecy $automationTaskRepository;

    /** @var ObjectProphecy<TaskExecutionRepositoryInterface> */
    private ObjectProphecy $executionRepository;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $entityManager;

    private TaskController $controller;

    protected function setUp(): void
    {
        $this->taskManager = $this->prophesize(TaskManagerInterface::class);
        $this->permissionChecker = $this->prophesize(TaskPermissionChecker::class);
        $this->viewHandler = $this->prophesize(ViewHandlerInterface::class);
        $this->viewHandler->handle(Argument::cetera())->willReturn(new Response());

        $this->automationTaskRepository = $this->prophesize(AutomationTaskRepositoryInterface::class);
        $this->executionRepository = $this->prophesize(TaskExecutionRepositoryInterface::class);

        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->controller = new TaskController(
            $this->viewHandler->reveal(),
            $this->prophesize(TokenStorageInterface::class)->reveal(),
            $this->prophesize(DoctrineListBuilderFactoryInterface::class)->reveal(),
            $this->prophesize(TaskHandlerFactoryInterface::class)->reveal(),
            $this->prophesize(TaskRepositoryInterface::class)->reveal(),
            $this->executionRepository->reveal(),
            $this->prophesize(RestHelperInterface::class)->reveal(),
            $this->taskManager->reveal(),
            $this->entityManager->reveal(),
            $this->prophesize(FieldDescriptorFactoryInterface::class)->reveal(),
            $this->automationTaskRepository->reveal(),
            $this->prophesize(TranslatorInterface::class)->reveal(),
            $this->permissionChecker->reveal(),
        );
    }

    public function testPostWithoutLivePermissionCreatesNoTask(): void
    {
        $this->permissionChecker->checkCurrentUser('Handler', 'Entity', '1', 'de')->willThrow(new AccessDeniedException());
        $this->taskManager->create(Argument::any())->shouldNotBeCalled();

        $this->expectException(AccessDeniedException::class);

        $this->controller->postAction(new Request(
            ['entityClass' => 'Entity', 'entityId' => '1', 'locale' => 'de'],
            ['handlerClass' => 'Handler', 'schedule' => '2030-01-01T10:00:00'],
        ));
    }

    public function testPostWithTheMarkerAbortsThePlannedTasksInTheFlushOfTheNewTask(): void
    {
        $events = [];

        $planned = $this->prophesize(TaskExecutionInterface::class);
        $planned->getStatus()->willReturn(TaskStatus::PLANNED);
        $planned->setStatus(TaskStatus::ABORTED)->will(function() use (&$events) {
            $events[] = 'abort';
        })->shouldBeCalledTimes(1);
        $running = $this->prophesize(TaskExecutionInterface::class);
        $running->getStatus()->willReturn(TaskStatus::RUNNING);
        $running->setStatus(Argument::any())->shouldNotBeCalled();

        $phpTask = $this->prophesize(PHPTaskInterface::class);
        $phpTask->getUuid()->willReturn('uuid-1');
        $other = $this->prophesize(TaskInterface::class);
        $other->getTask()->willReturn($phpTask->reveal());

        $this->automationTaskRepository->findPendingTasks('Handler', 'Entity', '1', 'de')->will(function() use (&$events, $other) {
            $events[] = 'find';

            return [$other->reveal()];
        });
        $this->executionRepository->findByTaskUuid('uuid-1')->willReturn([$planned->reveal(), $running->reveal()]);
        $this->executionRepository->save(Argument::any())->shouldNotBeCalled();
        $this->taskManager->create(Argument::type(Task::class))->will(function(array $args) use (&$events) {
            $events[] = 'create';

            return $args[0];
        })->shouldBeCalledTimes(1);
        $this->entityManager->flush()->will(function() use (&$events) {
            $events[] = 'flush';
        })->shouldBeCalledTimes(1);

        $this->controller->postAction(new Request(
            ['entityClass' => 'Entity', 'entityId' => '1', 'locale' => 'de'],
            ['handlerClass' => 'Handler', 'schedule' => '2030-01-01T10:00:00', 'replacePlanned' => true],
        ));

        $this->assertSame(['find', 'abort', 'create', 'flush'], $events);
    }

    public function testPostWithTheMarkerAbortsNothingWhenTheCreationFails(): void
    {
        $this->automationTaskRepository->findPendingTasks(Argument::cetera())->willReturn([]);
        $this->taskManager->create(Argument::type(Task::class))->willThrow(new \RuntimeException());
        $this->entityManager->flush()->shouldNotBeCalled();

        $this->expectException(\RuntimeException::class);

        $this->controller->postAction(new Request(
            ['entityClass' => 'Entity', 'entityId' => '1', 'locale' => 'de'],
            ['handlerClass' => 'Handler', 'schedule' => '2030-01-01T10:00:00', 'replacePlanned' => true],
        ));
    }

    public function testPostWithoutTheMarkerKeepsThePlannedTasks(): void
    {
        $this->automationTaskRepository->findPendingTasks(Argument::cetera())->shouldNotBeCalled();
        $this->executionRepository->save(Argument::any())->shouldNotBeCalled();
        $this->taskManager->create(Argument::type(Task::class))->shouldBeCalled();

        $this->controller->postAction(new Request(
            ['entityClass' => 'Entity', 'entityId' => '1', 'locale' => 'de'],
            ['handlerClass' => 'Handler', 'schedule' => '2030-01-01T10:00:00'],
        ));
    }

    public function testPutChecksTheStoredValuesOfTheTask(): void
    {
        $this->taskManager->findById('task-id')->willReturn($this->createTask());
        $this->permissionChecker->checkCurrentUser('Handler', 'Entity', '1', 'de')->willThrow(new AccessDeniedException());
        $this->permissionChecker->checkCurrentUser('Other', 'Entity', '1', 'en')->shouldNotBeCalled();
        $this->taskManager->update(Argument::any())->shouldNotBeCalled();

        $this->expectException(AccessDeniedException::class);

        $this->controller->putAction('task-id', new Request(
            ['locale' => 'en'],
            ['handlerClass' => 'Other', 'schedule' => '2030-01-01T10:00:00'],
        ));
    }

    public function testPutChecksTheNewValuesOfTheTask(): void
    {
        $this->taskManager->findById('task-id')->willReturn($this->createTask());
        $this->permissionChecker->checkCurrentUser('Handler', 'Entity', '1', 'de')->shouldBeCalled();
        $this->permissionChecker->checkCurrentUser('Other', 'Entity', '1', 'en')->willThrow(new AccessDeniedException());
        $this->taskManager->update(Argument::any())->shouldNotBeCalled();

        $this->expectException(AccessDeniedException::class);

        $this->controller->putAction('task-id', new Request(
            ['locale' => 'en'],
            ['handlerClass' => 'Other', 'schedule' => '2030-01-01T10:00:00'],
        ));
    }

    public function testDeleteWithoutHandlerPermissionRemovesNoTask(): void
    {
        $this->taskManager->findById('task-id')->willReturn($this->createTask());
        $this->permissionChecker->checkCurrentUserForRemoval('Handler', 'Entity', '1', 'de')->willThrow(new AccessDeniedException());
        $this->taskManager->remove(Argument::any())->shouldNotBeCalled();

        $this->expectException(AccessDeniedException::class);

        $this->controller->deleteAction('task-id');
    }

    public function testDeleteWithHandlerPermissionRemovesTask(): void
    {
        $this->taskManager->findById('task-id')->willReturn($this->createTask());
        $this->permissionChecker->checkCurrentUserForRemoval('Handler', 'Entity', '1', 'de')->shouldBeCalled();
        $this->taskManager->remove('task-id')->shouldBeCalled();

        $this->controller->deleteAction('task-id');
    }

    public function testMultipleDeleteRemovesNothingIfOneTaskDoesNotExist(): void
    {
        $this->taskManager->findById('known')->willReturn($this->createTask());
        $this->taskManager->findById('unknown')->willThrow(new TaskNotFoundException('unknown'));
        $this->permissionChecker->checkCurrentUserForRemoval('Handler', 'Entity', '1', 'de')->shouldBeCalled();
        $this->taskManager->remove(Argument::any())->shouldNotBeCalled();

        $this->expectException(TaskNotFoundException::class);

        $this->controller->cdeleteAction(new Request(['ids' => 'known,unknown']));
    }

    public function testMultipleDeleteRemovesNothingIfOneTaskIsNotAllowed(): void
    {
        $allowed = $this->createTask();
        $denied = $this->createTask();
        $denied->setEntityId('2');
        $this->taskManager->findById('allowed')->willReturn($allowed);
        $this->taskManager->findById('denied')->willReturn($denied);
        $this->permissionChecker->checkCurrentUserForRemoval('Handler', 'Entity', '1', 'de')->shouldBeCalled();
        $this->permissionChecker->checkCurrentUserForRemoval('Handler', 'Entity', '2', 'de')->willThrow(new AccessDeniedException());
        $this->taskManager->remove(Argument::any())->shouldNotBeCalled();

        $this->expectException(AccessDeniedException::class);

        $this->controller->cdeleteAction(new Request(['ids' => 'allowed,denied']));
    }

    private function createTask(): Task
    {
        $task = new Task();
        $task->setHandlerClass('Handler');
        $task->setEntityClass('Entity');
        $task->setEntityId('1');
        $task->setLocale('de');

        return $task;
    }
}
