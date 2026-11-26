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
 * Checks when a publish task runs whether its user may still publish. The console has no token, where the workflow
 * lets every publish pass, so the user is named. It then requires `live`, or `edit` with an approved request, and
 * refuses disabled or locked users and content an open request has taken over (see `PublishAtAction`).
 *
 * The authorizer exists only in the admin context. Without it, only the permission check of the task handler remains.
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
     * A task without a user predates the user in the workload and runs as before.
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
