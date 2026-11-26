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
 * Schedules the publish task once the last approval or check approves a request. The task belongs to the logged-in
 * user, whose permission is checked again when it runs. Without a user, a future date or the permission, nothing is
 * scheduled and the footer action remains. A listener must not fail the approval, so a refusal is no error.
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
            $this->action->execute($request);
        } catch (AccessDeniedException) {
            // @ignoreException the user may not publish, the footer action stays for someone who may
        }
    }
}
