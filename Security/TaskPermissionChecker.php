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

namespace Sulu\Bundle\AutomationBundle\Security;

use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Article\Infrastructure\Sulu\Admin\ArticleAdmin;
use Sulu\Bundle\AutomationBundle\Exception\TaskPermissionDeniedException;
use Sulu\Bundle\AutomationBundle\TaskHandler\SecuredTaskHandlerInterface;
use Sulu\Component\Security\Authentication\UserInterface;
use Sulu\Component\Security\Authentication\UserRepositoryInterface;
use Sulu\Component\Security\Authorization\AccessControl\AccessControlManagerInterface;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\Authorization\SecurityCondition;
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Sulu\Snippet\Infrastructure\Sulu\Admin\SnippetAdmin;
use Task\Handler\TaskHandlerFactoryInterface;

/**
 * Every handler names the permission it needs, so scheduling and running a task needs the same permission as doing
 * the same directly.
 */
class TaskPermissionChecker
{
    public function __construct(
        private SecurityCheckerInterface $securityChecker,
        private AccessControlManagerInterface $accessControlManager,
        private UserRepositoryInterface $userRepository,
        private PageRepositoryInterface $pageRepository,
        private TaskHandlerFactoryInterface $taskHandlerFactory,
    ) {
    }

    /**
     * Tells if the logged in user may use the handler on the entity, used to offer only fitting handlers.
     */
    public function isCurrentUserAllowed(object $handler, string $entityClass, string $entityId, string $locale): bool
    {
        $permission = $handler instanceof SecuredTaskHandlerInterface ? $handler->getPermissionType() : null;
        $condition = null === $permission ? null : $this->createCondition($entityClass, $entityId, $locale);

        return null === $condition || $this->securityChecker->hasPermission($condition, $permission);
    }

    /**
     * Checks the logged in user, used when a task is scheduled.
     *
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function checkCurrentUser(string $handlerClass, string $entityClass, string $entityId, string $locale): void
    {
        $permission = $this->getPermission($handlerClass);
        $condition = null === $permission ? null : $this->createCondition($entityClass, $entityId, $locale);
        if (null === $permission || null === $condition) {
            return;
        }

        $this->securityChecker->checkPermission($condition, $permission);
    }

    /**
     * Checks a stored user without a token, because a task runs from the console and the security checker would
     * grant everything there.
     *
     * @throws TaskPermissionDeniedException
     */
    public function checkUser(int|string $userId, string $handlerClass, string $entityClass, string $entityId, string $locale): void
    {
        $user = $this->userRepository->findUserById((int) $userId);

        if (!$user instanceof UserInterface || !$user->getEnabled() || $user->getLocked()) {
            throw new TaskPermissionDeniedException(\sprintf('User "%s" of the task does not exist or is not active anymore.', $userId));
        }

        $permission = $this->getPermission($handlerClass);
        $condition = null === $permission ? null : $this->createCondition($entityClass, $entityId, $locale);
        if (null === $permission || null === $condition) {
            return;
        }

        $permissions = $this->accessControlManager->getUserPermissions($condition, $user);

        if (!($permissions[$permission] ?? false)) {
            throw new TaskPermissionDeniedException(\sprintf('User "%s" of the task has no "%s" permission for "%s" "%s".', $userId, $permission, $entityClass, $entityId));
        }
    }

    private function getPermission(string $handlerClass): ?string
    {
        $handler = $this->taskHandlerFactory->create($handlerClass);

        return $handler instanceof SecuredTaskHandlerInterface ? $handler->getPermissionType() : null;
    }

    /**
     * Entities of other bundles have no known context, so their handlers have to check themselves.
     */
    private function createCondition(string $entityClass, string $entityId, string $locale): ?SecurityCondition
    {
        if (PageInterface::class === $entityClass) {
            // the webspace of a page decides its security context
            return new SecurityCondition(
                $this->pageRepository->getOneBy(['uuid' => $entityId])->getSecurityContext(),
                $locale,
                Page::class,
                $entityId,
            );
        }

        if (SnippetInterface::class === $entityClass) {
            return new SecurityCondition(SnippetAdmin::SECURITY_CONTEXT, $locale);
        }

        if (ArticleInterface::class === $entityClass) {
            return new SecurityCondition(ArticleAdmin::SECURITY_CONTEXT, $locale);
        }

        return null;
    }
}
