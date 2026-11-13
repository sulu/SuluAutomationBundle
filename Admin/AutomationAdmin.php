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

namespace Sulu\Bundle\AutomationBundle\Admin;

use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Article\Infrastructure\Sulu\Admin\ArticleAdmin;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormGroup;
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;
use Sulu\Bundle\AutomationBundle\Admin\View\AutomationViewBuilderFactoryInterface;
use Sulu\Bundle\AutomationBundle\TaskHandler\AutomationTaskHandlerInterface;
use Sulu\Bundle\AutomationBundle\TaskHandler\SecuredTaskHandlerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Infrastructure\Sulu\Admin\PageAdmin;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Sulu\Snippet\Infrastructure\Sulu\Admin\SnippetAdmin;
use Task\TaskBundle\Handler\TaskHandlerFactory;

/**
 * Admin integration of the bundle.
 */
class AutomationAdmin extends Admin
{
    public const SECURITY_CONTEXT = 'sulu_automation.automation.tasks';

    public static function getPriority(): int
    {
        return PageAdmin::getPriority() - 1;
    }

    public function __construct(
        private AutomationViewBuilderFactoryInterface $automationViewBuilderFactory,
        private SecurityCheckerInterface $securityChecker,
        private GroupProviderInterface $groupProvider,
        private ?TaskHandlerFactory $taskHandlerFactory = null,
        private ?WebspaceManagerInterface $webspaceManager = null,
    ) {
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        $this->configurePageView($viewCollection);
        $this->configureSnippetView($viewCollection);
        $this->configureArticleView($viewCollection);
    }

    private function configurePageView(ViewCollection $viewCollection): void
    {
        if ($viewCollection->has(PageAdmin::EDIT_FORM_VIEW)
            && $this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::EDIT)
        ) {
            $viewCollection->add(
                $this->automationViewBuilderFactory->createTaskListViewBuilder(
                    PageAdmin::EDIT_FORM_VIEW . '.automation',
                    '/automation',
                    PageInterface::class,
                    $this->hasUsableHandler(PageInterface::class, $this->getPageSecurityContexts()),
                )
                    ->setTabOrder(4096)
                    ->setParent(PageAdmin::EDIT_FORM_VIEW),
            );
        }
    }

    private function configureSnippetView(ViewCollection $viewCollection): void
    {
        if ($viewCollection->has(SnippetAdmin::EDIT_TABS_VIEW)
            && $this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::EDIT)
        ) {
            $viewCollection->add(
                $this->automationViewBuilderFactory->createTaskListViewBuilder(
                    SnippetAdmin::EDIT_TABS_VIEW . '.automation',
                    '/automation',
                    SnippetInterface::class,
                    $this->hasUsableHandler(SnippetInterface::class, [SnippetAdmin::SECURITY_CONTEXT]),
                )
                    ->setTabOrder(4096)
                    ->setParent(SnippetAdmin::EDIT_TABS_VIEW),
            );
        }
    }

    private function configureArticleView(ViewCollection $viewCollection): void
    {
        $groups = $this->groupProvider->getGroups(ArticleInterface::TEMPLATE_TYPE);

        foreach ($groups as $group) {
            $groupIdentifier = $group->identifier;

            if ($viewCollection->has(ArticleAdmin::EDIT_TABS_VIEW . '_' . $groupIdentifier)
                && $this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::EDIT)
            ) {
                $viewCollection->add(
                    $this->automationViewBuilderFactory->createTaskListViewBuilder(
                        ArticleAdmin::EDIT_TABS_VIEW . '.automation_' . $groupIdentifier,
                        '/automation',
                        ArticleInterface::class,
                        $this->hasUsableHandler(ArticleInterface::class, $this->getArticleSecurityContexts($groups, $group)),
                    )
                        ->setTabOrder(4096)
                        ->setParent(ArticleAdmin::EDIT_TABS_VIEW . '_' . $groupIdentifier),
                );
            }
        }
    }

    /**
     * The tab stays for everybody, but adding a task makes no sense if no handler is usable anywhere.
     * Which one is usable on a single entity is decided when the form of the overlay is loaded.
     *
     * @param string[] $securityContexts
     */
    private function hasUsableHandler(string $entityClass, array $securityContexts): bool
    {
        if (null === $this->taskHandlerFactory) {
            return true;
        }

        foreach ($this->taskHandlerFactory->getHandlers() as $handler) {
            if (!$handler instanceof AutomationTaskHandlerInterface || !$handler->supports($entityClass)) {
                continue;
            }

            if (!$handler instanceof SecuredTaskHandlerInterface) {
                return true;
            }

            foreach ($securityContexts as $securityContext) {
                if ($this->securityChecker->hasPermission($securityContext, $handler->getPermissionType())) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function getPageSecurityContexts(): array
    {
        if (null === $this->webspaceManager) {
            return [];
        }

        $securityContexts = [];
        foreach ($this->webspaceManager->getWebspaceCollection() as $webspace) {
            $securityContexts[] = PageAdmin::getPageSecurityContext($webspace->getKey());
        }

        return $securityContexts;
    }

    /**
     * @param array<string, FormGroup> $groups
     *
     * @return string[]
     */
    private function getArticleSecurityContexts(array $groups, FormGroup $group): array
    {
        $securityContexts = [ArticleAdmin::SECURITY_CONTEXT];

        // only Sulu 3.1 has a security context per group
        if (\method_exists(ArticleAdmin::class, 'getGroupSecurityContext')) {
            $securityContexts[] = ArticleAdmin::getGroupSecurityContext($groups, $group);
        }

        return $securityContexts;
    }

    /**
     * @return mixed[]
     */
    public function getSecurityContexts()
    {
        return [
            'Sulu' => [
                'Automation' => [
                    self::SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                        PermissionTypes::ADD,
                        PermissionTypes::EDIT,
                        PermissionTypes::DELETE,
                    ],
                ],
            ],
        ];
    }
}
