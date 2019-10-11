<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AutomationBundle\Admin\View\AutomationViewBuilder;
use Sulu\Bundle\AutomationBundle\Handler\DocumentPublishHandler;
use Sulu\Bundle\AutomationBundle\Handler\DocumentUnpublishHandler;
use Sulu\Bundle\PageBundle\Admin\PageAdmin;
use Sulu\Bundle\PageBundle\Document\BasePageDocument;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;

/**
 * Admin integration of the bundle.
 */
class AutomationAdmin extends Admin
{
    const SECURITY_CONTEXT = 'sulu_automation.automation.tasks';

    const LIST_VIEW = 'sulu_automation.list';
    const EDIT_FORM_VIEW = 'sulu_automation.edit_form';

    /**
     * @var ViewBuilderFactoryInterface
     */
    protected $viewBuilderFactory;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var WebspaceManagerInterface
     */
    protected $webspaceManager;

    public function __construct(
        ViewBuilderFactoryInterface $viewBuilderFactory,
        WebspaceManagerInterface $webspaceManager,
        string $title
    )
    {
        $this->title = $title;
        $this->viewBuilderFactory = $viewBuilderFactory;
        $this->webspaceManager = $webspaceManager;
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        $automationViewBuilder = new AutomationViewBuilder(static::LIST_VIEW, '/automation');
        $automationViewBuilder
            ->setEntityClass(BasePageDocument::class)
            ->setParent(PageAdmin::EDIT_FORM_VIEW);

        $viewCollection->add($automationViewBuilder);
    }

    /**
     * {@inheritdoc}
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
