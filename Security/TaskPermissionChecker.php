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
use Sulu\Bundle\AutomationBundle\TaskHandler\SecuredAutomationTaskHandlerInterface;
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
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Task\Handler\TaskHandlerFactoryInterface;

/**
 * Checks the permission the handler of a task names, for the user who schedules and for the user who runs it.
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

    public function isCurrentUserAllowed(object $handler, string $entityClass, string $entityId, string $locale): bool
    {
        if (!$handler instanceof SecuredAutomationTaskHandlerInterface) {
            return true;
        }

        try {
            $condition = $this->createCondition($handler, $entityClass, $entityId, $locale);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return $this->securityChecker->hasPermission($condition, $handler->getPermissionType());
    }

    public function checkCurrentUser(string $handlerClass, string $entityClass, string $entityId, string $locale): void
    {
        $handler = $this->taskHandlerFactory->create($handlerClass);
        if (!$handler instanceof SecuredAutomationTaskHandlerInterface) {
            return;
        }

        try {
            $condition = $this->createCondition($handler, $entityClass, $entityId, $locale);
        } catch (\InvalidArgumentException $exception) {
            throw new AccessDeniedException($exception->getMessage(), $exception);
        }

        $this->securityChecker->checkPermission($condition, $handler->getPermissionType());
    }

    /**
     * Without a token, because a task runs from the console and the security checker would grant everything there.
     *
     * @throws TaskPermissionDeniedException
     */
    public function checkUser(int|string $userId, string $handlerClass, string $entityClass, string $entityId, string $locale): void
    {
        $handler = $this->taskHandlerFactory->create($handlerClass);
        if (!$handler instanceof SecuredAutomationTaskHandlerInterface) {
            return;
        }

        $user = $this->userRepository->findUserById((int) $userId);

        if (!$user instanceof UserInterface || !$user->getEnabled() || $user->getLocked()) {
            throw new TaskPermissionDeniedException(\sprintf('User "%s" of the task does not exist or is not active anymore.', $userId));
        }

        try {
            $condition = $this->createCondition($handler, $entityClass, $entityId, $locale);
        } catch (\InvalidArgumentException $exception) {
            throw new TaskPermissionDeniedException($exception->getMessage(), 0, $exception);
        }

        $permission = $handler->getPermissionType();
        $permissions = $this->accessControlManager->getUserPermissions($condition, $user);

        if (!($permissions[$permission] ?? false)) {
            throw new TaskPermissionDeniedException(\sprintf('User "%s" of the task has no "%s" permission for "%s" "%s".', $userId, $permission, $entityClass, $entityId));
        }
    }

    /**
     * The request names handler and entity independently, so they have to fit. An entity without a known context is
     * refused for a secured handler.
     *
     * @throws \InvalidArgumentException
     */
    private function createCondition(SecuredAutomationTaskHandlerInterface $handler, string $entityClass, string $entityId, string $locale): SecurityCondition
    {
        if (!$handler->supports($entityClass)) {
            throw new \InvalidArgumentException(\sprintf('Task handler "%s" does not support "%s".', $handler::class, $entityClass));
        }

        if (PageInterface::class === $entityClass) {
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

        throw new \InvalidArgumentException(\sprintf('No security context known for "%s".', $entityClass));
    }
}
