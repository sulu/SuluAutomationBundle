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
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;
use Sulu\Bundle\AutomationBundle\Admin\View\AutomationViewBuilderFactoryInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Infrastructure\Sulu\Admin\PageAdmin;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Sulu\Snippet\Infrastructure\Sulu\Admin\SnippetAdmin;

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
                )
                    ->setTabOrder(4096)
                    ->setParent(SnippetAdmin::EDIT_TABS_VIEW),
            );
        }
    }

    private function configureArticleView(ViewCollection $viewCollection): void
    {
        $groups = $this->groupProvider->getGroups();

        foreach ($groups as $group) {
            $groupIdentifier = $group->identifier;

            if ($viewCollection->has(ArticleAdmin::EDIT_TABS_VIEW . '_' . $groupIdentifier)
                && $this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::EDIT)
            ) {
                $viewCollection->add(
                    $this->automationViewBuilderFactory->createTaskListViewBuilder(
                        ArticleAdmin::EDIT_TABS_VIEW . '.automation',
                        '/automation',
                        ArticleInterface::class,
                    )
                        ->setTabOrder(4096)
                        ->setParent(ArticleAdmin::EDIT_TABS_VIEW . '_default'),
                );
            }
        }
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
