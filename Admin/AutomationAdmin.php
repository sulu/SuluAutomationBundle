<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AutomationBundle\Admin\View\AutomationViewBuilderFactoryInterface;
use Sulu\Bundle\PageBundle\Admin\PageAdmin;
use Sulu\Bundle\PageBundle\Document\BasePageDocument;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

/**
 * Admin integration of the bundle.
 */
class AutomationAdmin extends Admin
{
    const SECURITY_CONTEXT = 'sulu_automation.automation.tasks';

    public static function getPriority(): int
    {
        return PageAdmin::getPriority() - 1;
    }

    /**
     * @var AutomationViewBuilderFactoryInterface
     */
    protected $automationViewBuilderFactory;

    /**
     * @var SecurityCheckerInterface
     */
    protected $securityChecker;

    public function __construct(
        AutomationViewBuilderFactoryInterface $automationViewBuilderFactory,
        SecurityCheckerInterface $securityChecker
    ) {
        $this->automationViewBuilderFactory = $automationViewBuilderFactory;
        $this->securityChecker = $securityChecker;
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if ($viewCollection->has(PageAdmin::EDIT_FORM_VIEW)
            && $this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::EDIT)
        ) {
            $viewCollection->add(
                $this->automationViewBuilderFactory->createTaskListViewBuilder(
                    PageAdmin::EDIT_FORM_VIEW . '.automation',
                    '/automation',
                    BasePageDocument::class
                )
                    ->setTabOrder(4096)
                    ->setParent(PageAdmin::EDIT_FORM_VIEW)
            );
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
