<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\RequestForPublishing;

use Sulu\Content\Application\WorkflowTransitionRequest\Event\WorkflowTransitionRequestActionEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Schedules the publish task as soon as a request is approved, so the requester has nothing left to click.
 *
 * The request is approved by the last approval or by the last check to pass, both are reported by an event. The
 * task belongs to the user who is logged in then, because the permission of that user is checked again when it runs.
 * Without a user, or without the permission, nothing is scheduled and the action in the footer stays as the way to do it
 * by hand. A listener must not fail the approval, so a refusal is not an error here.
 */
final class PublishAtScheduler
{
    public function __construct(
        private readonly PublishAtAction $action,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function __invoke(WorkflowTransitionRequestActionEvent $event): void
    {
        if (!\in_array($event->getAction(), [
            WorkflowTransitionRequestActionEvent::APPROVED,
            WorkflowTransitionRequestActionEvent::VALIDATED,
        ], true)) {
            return;
        }

        if (!$this->tokenStorage->getToken()?->getUser() instanceof UserInterface) {
            return;
        }

        $request = $event->getWorkflowTransitionRequest();
        if (!$this->action->isAvailable($request)) {
            return;
        }

        try {
            $this->action->execute($request, []);
        } catch (AccessDeniedException) {
            // the user may not publish, the footer action stays for someone who may
        }
    }
}
