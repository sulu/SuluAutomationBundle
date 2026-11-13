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
use Sulu\Bundle\AutomationBundle\Security\TaskPermissionChecker;
use Sulu\Bundle\AutomationBundle\Tasks\Manager\TaskManagerInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface as AutomationTaskRepositoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\RestHelperInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Task\Handler\TaskHandlerFactoryInterface;
use Task\Storage\TaskExecutionRepositoryInterface;
use Task\Storage\TaskRepositoryInterface;

class TaskControllerPermissionTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<TaskManagerInterface> */
    private ObjectProphecy $taskManager;

    /** @var ObjectProphecy<TaskPermissionChecker> */
    private ObjectProphecy $permissionChecker;

    private TaskController $controller;

    protected function setUp(): void
    {
        $this->taskManager = $this->prophesize(TaskManagerInterface::class);
        $this->permissionChecker = $this->prophesize(TaskPermissionChecker::class);

        $this->controller = new TaskController(
            $this->prophesize(ViewHandlerInterface::class)->reveal(),
            $this->prophesize(TokenStorageInterface::class)->reveal(),
            $this->prophesize(DoctrineListBuilderFactoryInterface::class)->reveal(),
            $this->prophesize(TaskHandlerFactoryInterface::class)->reveal(),
            $this->prophesize(TaskRepositoryInterface::class)->reveal(),
            $this->prophesize(TaskExecutionRepositoryInterface::class)->reveal(),
            $this->prophesize(RestHelperInterface::class)->reveal(),
            $this->taskManager->reveal(),
            $this->prophesize(EntityManagerInterface::class)->reveal(),
            $this->prophesize(FieldDescriptorFactoryInterface::class)->reveal(),
            $this->prophesize(AutomationTaskRepositoryInterface::class)->reveal(),
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

    public function testPutWithoutLivePermissionUpdatesNoTask(): void
    {
        $task = new Task();
        $task->setHandlerClass('Handler');
        $task->setEntityClass('Entity');
        $task->setEntityId('1');
        $this->taskManager->findById('task-id')->willReturn($task);
        $this->permissionChecker->checkCurrentUser('Handler', 'Entity', '1', 'de')->willThrow(new AccessDeniedException());
        $this->taskManager->update(Argument::any())->shouldNotBeCalled();

        $this->expectException(AccessDeniedException::class);

        $this->controller->putAction('task-id', new Request(
            ['locale' => 'de'],
            ['handlerClass' => 'Handler', 'schedule' => '2030-01-01T10:00:00'],
        ));
    }
}
