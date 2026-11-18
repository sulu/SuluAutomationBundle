<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\TaskHandler;

use Sulu\Component\Security\Authentication\UserRepositoryInterface;
use Sulu\Content\Application\Security\WorkflowTransitionAdminAuthorizerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Asks the content workflow whether the user of a publish task may still publish the content when the task runs.
 *
 * A task runs from the console without a token, where the workflow lets every publish pass, so the handler has to name
 * the user. The workflow decides with its own rule: the permission `live`, or the permission `edit` while an approved
 * request is open for the content.
 *
 * The authorizer only exists in the admin context. Without it, as in the website kernel, there is nothing to ask and the
 * check of the permission of the task handler stays the only one.
 *
 * @internal
 */
class PublishTaskUserGuard
{
    public function __construct(
        private readonly ?WorkflowTransitionAdminAuthorizerInterface $authorizer,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * A task without a user was scheduled before the tasks carried one, and runs as it did then.
     *
     * @param array{id: string, locale: string, userId?: int|string} $workload
     *
     * @throws AccessDeniedException
     */
    public function assertCanPublish(string $resourceKey, array $workload): void
    {
        if (null === $this->authorizer || !isset($workload['userId'])) {
            return;
        }

        $user = $this->userRepository->findUserById((int) $workload['userId']);
        if (null === $user) {
            throw new AccessDeniedException(\sprintf('User "%s" of the task does not exist anymore.', $workload['userId']));
        }

        $this->authorizer->assertCanPublish($resourceKey, $workload['id'], $workload['locale'], $user);
    }
}
