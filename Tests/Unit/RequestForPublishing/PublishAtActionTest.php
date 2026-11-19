<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tests\Unit\RequestForPublishing;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\AutomationBundle\Entity\Task;
use Sulu\Bundle\AutomationBundle\RequestForPublishing\Event\PublishAtScheduledEvent;
use Sulu\Bundle\AutomationBundle\RequestForPublishing\PublishAtAction;
use Sulu\Bundle\AutomationBundle\RequestForPublishing\PublishAtTargets;
use Sulu\Bundle\AutomationBundle\Security\TaskPermissionChecker;
use Sulu\Bundle\AutomationBundle\TaskHandler\PagePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\Tasks\Manager\TaskManagerInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Content\Application\RequestWorkflow\WorkflowTransitionRequestStatusResolverInterface;
use Sulu\Content\Domain\Model\WorkflowTransitionRequest\WorkflowTransitionRequest;
use Sulu\Content\Domain\Value\WorkflowTransitionRequest\WorkflowTransitionRequestStatusEnum;
use Sulu\Page\Domain\Model\PageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PublishAtActionTest extends TestCase
{
    use ProphecyTrait;

    private const NOW = '2026-11-18T10:00:00+00:00';

    public function testKeyLabelAndPermission(): void
    {
        $action = $this->createAction();

        $this->assertSame('publish_at', PublishAtAction::getKey());
        $this->assertSame('sulu_automation.publish_on', $action->getLabel());
        $this->assertSame(PermissionTypes::LIVE, $action->getPermission());
    }

    public function testLabelNamesTheDate(): void
    {
        $request = $this->createRequest(data: ['publishAutomatically' => true, 'publishAt' => '2026-11-25T10:00:00+01:00']);

        $this->assertSame(['date' => '25.11.2026 10:00'], $this->createAction()->getLabelParameters($request));
    }

    public function testLabelHasNoParametersWithoutADate(): void
    {
        $this->assertSame([], $this->createAction()->getLabelParameters($this->createRequest(data: [])));
    }

    public function testReplacesThePublishWhileTheDateLiesAhead(): void
    {
        $this->assertTrue($this->createAction()->replacesPublish($this->createRequest()));
    }

    public function testLeavesThePublishOnceTheDateHasPassed(): void
    {
        $request = $this->createRequest(data: ['publishAutomatically' => true, 'publishAt' => '2026-11-01T10:00:00+00:00']);

        $this->assertFalse($this->createAction()->replacesPublish($request));
    }

    public function testLeavesThePublishWithoutADate(): void
    {
        $this->assertFalse($this->createAction()->replacesPublish($this->createRequest(data: [])));
    }

    public function testLeavesThePublishOfContentWithoutAPublishTask(): void
    {
        $this->assertFalse($this->createAction()->replacesPublish($this->createRequest(resourceKey: 'products')));
    }

    /**
     * The form keeps the date of a field that was filled in and hidden again.
     */
    public function testIgnoresTheDateWhileTheToggleIsOff(): void
    {
        $action = $this->createAction();
        $request = $this->createRequest(data: ['publishAutomatically' => false, 'publishAt' => '2026-12-01T10:00:00+00:00']);

        $this->assertFalse($action->replacesPublish($request));
        $this->assertFalse($action->isAvailable($request));
        $this->assertSame([], $action->getLabelParameters($request));
    }

    public function testIgnoresTheDateWithoutTheToggle(): void
    {
        $request = $this->createRequest(data: ['publishAt' => '2026-12-01T10:00:00+00:00']);

        $this->assertFalse($this->createAction()->isAvailable($request));
    }

    public function testExecuteRefusesADateWhileTheToggleIsOff(): void
    {
        $request = $this->createRequest(data: ['publishAutomatically' => false, 'publishAt' => '2026-12-01T10:00:00+00:00']);

        $this->expectException(BadRequestHttpException::class);

        $this->createAction()->execute($request);
    }

    public function testStatusMessageTellsThatThePublishIsScheduled(): void
    {
        $action = $this->createAction(pendingTasks: 1);

        $this->assertSame(
            ['message' => 'sulu_automation.publish_scheduled', 'parameters' => ['date' => '01.12.2026 10:00']],
            $action->getStatusMessage($this->createRequest()),
        );
    }

    public function testStatusMessageStaysSilentWithoutAScheduledTask(): void
    {
        $this->assertNull($this->createAction(pendingTasks: 0)->getStatusMessage($this->createRequest()));
    }

    public function testStatusMessageStaysSilentWhileTheToggleIsOff(): void
    {
        $request = $this->createRequest(data: ['publishAutomatically' => false, 'publishAt' => '2026-12-01T10:00:00+00:00']);

        $this->assertNull($this->createAction(pendingTasks: 1)->getStatusMessage($request));
    }

    public function testStatusMessageStaysSilentUntilTheRequestIsApproved(): void
    {
        $action = $this->createAction(status: WorkflowTransitionRequestStatusEnum::PENDING, pendingTasks: 1);

        $this->assertNull($action->getStatusMessage($this->createRequest()));
    }

    public function testIsAvailableForAnApprovedRequestWithAFutureDate(): void
    {
        $this->assertTrue($this->createAction()->isAvailable($this->createRequest()));
    }

    public function testIsNotAvailableForContentWithoutAPublishTask(): void
    {
        $this->assertFalse($this->createAction()->isAvailable($this->createRequest(resourceKey: 'products')));
    }

    public function testIsNotAvailableUntilTheRequestIsApproved(): void
    {
        $action = $this->createAction(status: WorkflowTransitionRequestStatusEnum::PENDING);

        $this->assertFalse($action->isAvailable($this->createRequest()));
    }

    public function testIsNotAvailableWithoutADate(): void
    {
        $this->assertFalse($this->createAction()->isAvailable($this->createRequest(data: [])));
    }

    public function testIsNotAvailableWithADateThatCannotBeRead(): void
    {
        $this->assertFalse($this->createAction()->isAvailable($this->createRequest(data: ['publishAutomatically' => true, 'publishAt' => 'next week maybe'])));
    }

    public function testIsNotAvailableWhenAPublishIsAlreadyScheduled(): void
    {
        $this->assertFalse($this->createAction(pendingTasks: 1)->isAvailable($this->createRequest()));
    }

    public function testExecuteSchedulesThePublishOfTheContent(): void
    {
        $permissionChecker = $this->prophesize(TaskPermissionChecker::class);
        $permissionChecker->checkCurrentUser(PagePublishTaskHandler::class, PageInterface::class, 'page-uuid', 'en')
            ->shouldBeCalledOnce();

        $scheduled = null;
        $taskManager = $this->prophesize(TaskManagerInterface::class);
        $taskManager->create(Argument::type(TaskInterface::class))->will(static function(array $args) use (&$scheduled) {
            $scheduled = $args[0];
            $scheduled->setId('task-1');

            return $args[0];
        })->shouldBeCalledOnce();

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://admin.example.org/admin/api/x'));

        $collected = null;
        $collector = $this->prophesize(DomainEventCollectorInterface::class);
        $collector->collect(Argument::type(PublishAtScheduledEvent::class))->will(static function(array $args) use (&$collected): void {
            $collected = $args[0];
        })->shouldBeCalledOnce();

        $this->createAction(
            taskManager: $taskManager,
            permissionChecker: $permissionChecker,
            requestStack: $requestStack,
            domainEventCollector: $collector,
        )->execute($this->createRequest());

        $this->assertInstanceOf(PublishAtScheduledEvent::class, $collected);
        $this->assertSame('publish_scheduled', $collected->getEventType());
        $this->assertSame('pages', $collected->getResourceKey());
        $this->assertSame('page-uuid', $collected->getResourceId());
        $this->assertSame('en', $collected->getResourceLocale());
        $this->assertSame('task-1', $collected->getTaskId());
        $this->assertSame('2026-12-01T10:00:00+00:00', $collected->getEventContext()['publishAt']);

        $this->assertInstanceOf(Task::class, $scheduled);
        $this->assertSame(PagePublishTaskHandler::class, $scheduled->getHandlerClass());
        $this->assertSame(PageInterface::class, $scheduled->getEntityClass());
        $this->assertSame('page-uuid', $scheduled->getEntityId());
        $this->assertSame('en', $scheduled->getLocale());
        $this->assertEquals(new \DateTimeImmutable('2026-12-01T10:00:00+00:00'), $scheduled->getSchedule());
        $this->assertSame('https', $scheduled->getScheme());
        $this->assertSame('admin.example.org', $scheduled->getHost());
    }

    public function testExecuteRefusesADateInThePast(): void
    {
        $taskManager = $this->prophesize(TaskManagerInterface::class);
        $taskManager->create(Argument::any())->shouldNotBeCalled();

        $this->expectException(BadRequestHttpException::class);

        $this->createAction(taskManager: $taskManager)
            ->execute($this->createRequest(data: ['publishAutomatically' => true, 'publishAt' => '2026-11-01T10:00:00+00:00']));
    }

    public function testExecuteRefusesARequestWithoutADate(): void
    {
        $taskManager = $this->prophesize(TaskManagerInterface::class);
        $taskManager->create(Argument::any())->shouldNotBeCalled();

        $this->expectException(BadRequestHttpException::class);

        $this->createAction(taskManager: $taskManager)->execute($this->createRequest(data: []));
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function createRequest(string $resourceKey = 'pages', ?array $data = null): WorkflowTransitionRequest
    {
        $request = new WorkflowTransitionRequest($resourceKey, 'page-uuid', 'en', 'review');
        $request->setData($data ?? ['publishAutomatically' => true, 'publishAt' => '2026-12-01T10:00:00+00:00']);

        return $request;
    }

    private function createAction(
        WorkflowTransitionRequestStatusEnum $status = WorkflowTransitionRequestStatusEnum::APPROVED,
        int $pendingTasks = 0,
        $taskManager = null,
        $permissionChecker = null,
        ?RequestStack $requestStack = null,
        $domainEventCollector = null,
    ): PublishAtAction {
        $statusResolver = $this->prophesize(WorkflowTransitionRequestStatusResolverInterface::class);
        $statusResolver->resolve(Argument::type(WorkflowTransitionRequest::class))->willReturn($status);

        $taskRepository = $this->prophesize(TaskRepositoryInterface::class);
        $taskRepository->countPendingTasks(Argument::cetera())->willReturn($pendingTasks);

        $taskManager ??= $this->prophesize(TaskManagerInterface::class);
        $domainEventCollector ??= $this->prophesize(DomainEventCollectorInterface::class);
        $permissionChecker ??= $this->prophesize(TaskPermissionChecker::class);

        return new PublishAtAction(
            new PublishAtTargets(),
            $taskManager->reveal(),
            $taskRepository->reveal(),
            $permissionChecker->reveal(),
            $statusResolver->reveal(),
            $requestStack ?? new RequestStack(),
            $domainEventCollector->reveal(),
            new \DateTimeImmutable(self::NOW),
        );
    }
}
