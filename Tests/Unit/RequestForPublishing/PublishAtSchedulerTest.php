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
use Sulu\Bundle\AutomationBundle\RequestForPublishing\PublishAtAction;
use Sulu\Bundle\AutomationBundle\RequestForPublishing\PublishAtScheduler;
use Sulu\Content\Application\WorkflowTransitionRequest\Event\WorkflowTransitionRequestActionEvent;
use Sulu\Content\Domain\Model\WorkflowTransitionRequest\WorkflowTransitionRequest;
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
        $action->execute(Argument::type(WorkflowTransitionRequest::class), [])->shouldBeCalledOnce();

        $this->createScheduler($action)($this->createEvent(WorkflowTransitionRequestActionEvent::APPROVED));
    }

    public function testSchedulesWhenTheLastCheckPasses(): void
    {
        $action = $this->prophesize(PublishAtAction::class);
        $action->isAvailable(Argument::type(WorkflowTransitionRequest::class))->willReturn(true);
        $action->execute(Argument::type(WorkflowTransitionRequest::class), [])->shouldBeCalledOnce();

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

    private function createScheduler($action, bool $user = true): PublishAtScheduler
    {
        $token = $this->prophesize(TokenInterface::class);
        $token->getUser()->willReturn($user ? $this->prophesize(UserInterface::class)->reveal() : null);
        $tokenStorage = $this->prophesize(TokenStorageInterface::class);
        $tokenStorage->getToken()->willReturn($token->reveal());

        return new PublishAtScheduler($action->reveal(), $tokenStorage->reveal());
    }

    private function createEvent(string $action): WorkflowTransitionRequestActionEvent
    {
        return new WorkflowTransitionRequestActionEvent(
            new WorkflowTransitionRequest('pages', 'page-uuid', 'en', 'review'),
            $action,
        );
    }
}
