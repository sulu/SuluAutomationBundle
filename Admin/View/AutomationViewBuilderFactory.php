<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Admin\View;

use Sulu\Bundle\AdminBundle\Admin\View\Badge;
use Sulu\Bundle\AdminBundle\Admin\View\FormOverlayListViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AutomationBundle\Entity\Task;

class AutomationViewBuilderFactory implements AutomationViewBuilderFactoryInterface
{
    /**
     * @var ViewBuilderFactoryInterface
     */
    protected $viewBuilderFactory;

    public function __construct(
        ViewBuilderFactoryInterface $viewBuilderFactory,
    ) {
        $this->viewBuilderFactory = $viewBuilderFactory;
    }

    public function createTaskListViewBuilder(string $name, string $path, string $entityClass, bool $addable = true): FormOverlayListViewBuilderInterface
    {
        $taskCountBadge = (new Badge('sulu_automation.get_task_count', '/count', 'value != 0'))
            ->addRequestParameters(['entityClass' => $entityClass])
            ->addRouterAttributesToRequest(['locale' => 'locale', 'id' => 'entityId']);

        /** @var FormOverlayListViewBuilderInterface $formOverlayListViewBuilder */
        $formOverlayListViewBuilder = $this->viewBuilderFactory->createFormOverlayListViewBuilder($name, $path)
            ->setResourceKey(Task::RESOURCE_KEY)
            ->setListKey(Task::RESOURCE_KEY)
            ->setFormKey('task_details')
            ->setTabTitle('sulu_automation.automation')
            ->addToolbarActions(\array_values(\array_filter([
                $addable ? new ToolbarAction('sulu_admin.add') : null,
                new ToolbarAction('sulu_admin.delete'),
            ])))
            ->addTabBadges([$taskCountBadge])
            ->addListAdapters(['table'])
            ->addRequestParameters(['entityClass' => $entityClass])
            ->addMetadataRequestParameters(['entityClass' => $entityClass])
            ->addRouterAttributesToFormMetadata(['id' => 'entityId'])
            ->addRouterAttributesToFormRequest(['id' => 'entityId'])
            ->addRouterAttributesToListRequest(['id' => 'entityId'])
            ->setOption('itemDisabledCondition', 'status != "planned"');

        return $formOverlayListViewBuilder;
    }
}
