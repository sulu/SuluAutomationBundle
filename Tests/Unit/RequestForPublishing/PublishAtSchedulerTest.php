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
use Sulu\Bundle\AutomationBundle\RequestForPublishing\PublishAtAction;
use Sulu\Bundle\AutomationBundle\RequestForPublishing\PublishAtResourceResolver;
use Sulu\Bundle\AutomationBundle\RequestForPublishing\PublishAtScheduler;
use Sulu\Bundle\AutomationBundle\RequestForPublishing\PublishAtTargets;
use Sulu\Bundle\AutomationBundle\Security\TaskPermissionChecker;
use Sulu\Bundle\AutomationBundle\Tasks\Manager\TaskManagerInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface;
use Sulu\Content\Application\RequestWorkflow\WorkflowTransitionRequestStatusResolverInterface;
use Sulu\Content\Application\WorkflowTransitionRequest\Event\WorkflowTransitionRequestActionEvent;
use Sulu\Content\Domain\Model\WorkflowTransitionRequest\WorkflowTransitionRequest;
use Sulu\Content\Domain\Value\WorkflowTransitionRequest\WorkflowTransitionRequestStatusEnum;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

class PublishAtSchedulerTest extends TestCase
{
    use ProphecyTrait;

    public function testSchedulesWhenTheLastApprovalComesIn(): void
    {
        $action = $this->prophesize(PublishAtAction::class);
        $action->isAvailable(Argument::type(WorkflowTransitionRequest::class))->willReturn(true);
        $action->execute(Argument::type(WorkflowTransitionRequest::class))->shouldBeCalledOnce();

        $this->createScheduler($action)($this->createEvent(WorkflowTransitionRequestActionEvent::APPROVED));
    }

    public function testSchedulesWhenTheLastCheckPasses(): void
    {
        $action = $this->prophesize(PublishAtAction::class);
        $action->isAvailable(Argument::type(WorkflowTransitionRequest::class))->willReturn(true);
        $action->execute(Argument::type(WorkflowTransitionRequest::class))->shouldBeCalledOnce();

        $this->createScheduler($action)($this->createEvent(WorkflowTransitionRequestActionEvent::VALIDATED));
    }

    /**
     * An approval that does not finish the request, or one that comes after a task exists, is not available.
     */
    public function testSchedulesNothingWhenTheActionIsNotAvailable(): void
    {
        $action = $this->prophesize(PublishAtAction::class);
        $action->isAvailable(Argument::type(WorkflowTransitionRequest::class))->willReturn(false);
        $action->execute(Argument::cetera())->shouldNotBeCalled();

        $this->createScheduler($action)($this->createEvent(WorkflowTransitionRequestActionEvent::APPROVED));
    }

    public function testIgnoresOtherEvents(): void
    {
        $action = $this->prophesize(PublishAtAction::class);
        $action->isAvailable(Argument::cetera())->shouldNotBeCalled();
        $action->execute(Argument::cetera())->shouldNotBeCalled();

        $this->createScheduler($action)($this->createEvent(WorkflowTransitionRequestActionEvent::REJECTED));
    }

    /**
     * Without a user the task would run without a permission check.
     */
    public function testSchedulesNothingWithoutAUser(): void
    {
        $action = $this->prophesize(PublishAtAction::class);
        $action->execute(Argument::cetera())->shouldNotBeCalled();

        $this->createScheduler($action, user: false)($this->createEvent(WorkflowTransitionRequestActionEvent::VALIDATED));
    }

    public function testDoesNotFailTheApprovalWhenTheUserMayNotPublish(): void
    {
        $action = $this->prophesize(PublishAtAction::class);
        $action->isAvailable(Argument::cetera())->willReturn(true);
        $action->execute(Argument::cetera())->willThrow(new AccessDeniedException('no'))->shouldBeCalledOnce();

        $this->createScheduler($action)($this->createEvent(WorkflowTransitionRequestActionEvent::APPROVED));
    }

    /**
     * The action refuses a date that has passed with an exception the scheduler does not catch, so it must not
     * be asked to run. Otherwise the approval would roll back and the request could never be approved.
     */
    public function testSchedulesNothingAndDoesNotFailForADateThatHasPassed(): void
    {
        $taskManager = $this->prophesize(TaskManagerInterface::class);
        $taskManager->create(Argument::any())->shouldNotBeCalled();

        $statusResolver = $this->prophesize(WorkflowTransitionRequestStatusResolverInterface::class);
        $statusResolver->resolve(Argument::any())->willReturn(WorkflowTransitionRequestStatusEnum::APPROVED);

        $action = new PublishAtAction(
            new PublishAtTargets(),
            $taskManager->reveal(),
            $this->prophesize(TaskRepositoryInterface::class)->reveal(),
            $this->prophesize(TaskPermissionChecker::class)->reveal(),
            $statusResolver->reveal(),
            new RequestStack(),
            $this->prophesize(DomainEventCollectorInterface::class)->reveal(),
            $this->prophesize(PublishAtResourceResolver::class)->reveal(),
            new \DateTimeImmutable('2026-11-18T10:00:00+00:00'),
        );

        $request = new WorkflowTransitionRequest('pages', 'page-uuid', 'en', 'review');
        $request->setData(['publishAutomatically' => true, 'publishAt' => '2026-11-01T10:00:00+00:00']);

        $this->createScheduler($action, wrapped: false)(
            new WorkflowTransitionRequestActionEvent($request, WorkflowTransitionRequestActionEvent::APPROVED),
        );
    }

    private function createScheduler($action, bool $user = true, bool $wrapped = true): PublishAtScheduler
    {
        $token = $this->prophesize(TokenInterface::class);
        $token->getUser()->willReturn($user ? $this->prophesize(UserInterface::class)->reveal() : null);
        $tokenStorage = $this->prophesize(TokenStorageInterface::class);
        $tokenStorage->getToken()->willReturn($token->reveal());

        return new PublishAtScheduler($wrapped ? $action->reveal() : $action, $tokenStorage->reveal());
    }

    private function createEvent(string $action): WorkflowTransitionRequestActionEvent
    {
        return new WorkflowTransitionRequestActionEvent(
            new WorkflowTransitionRequest('pages', 'page-uuid', 'en', 'review'),
            $action,
        );
    }
}
