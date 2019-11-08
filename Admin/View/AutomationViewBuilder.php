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

use Sulu\Bundle\AdminBundle\Admin\View\FormOverlayListViewBuilder;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AutomationBundle\Entity\Task;

class AutomationViewBuilder extends FormOverlayListViewBuilder implements AutomationViewBuilderInterface
{
    public function __construct(string $name, string $path)
    {
        parent::__construct($name, $path);

        $listToolbarActions = [
            new ToolbarAction('sulu_admin.add'),
            new ToolbarAction('sulu_admin.delete'),
        ];

        $this->setResourceKey(Task::RESOURCE_KEY)
            ->setListKey(Task::RESOURCE_KEY)
            ->setFormKey('task_details')
            ->setTabTitle('sulu_automation.automation')
            ->addToolbarActions($listToolbarActions)
            ->addListAdapters(['table'])
            ->setTabOrder(4096)
            ->addRouterAttributesToFormRequest(['id' => 'entityId'])
            ->addRouterAttributesToListRequest(['id' => 'entityId']);
    }

    public function setEntityClass(string $entityClass): AutomationViewBuilderInterface
    {
        $this->addRequestParameter('entityClass', $entityClass);
        $this->addMetadataRequestParameters(['entityClass' => $entityClass]);

        return $this;
    }

    private function addRequestParameter(string $key, string $value): void
    {
        $oldRequestParameters = $this->getView()->getOption('requestParameters');
        $newRequestParameters = $oldRequestParameters
            ? array_merge($oldRequestParameters, [$key => $value])
            : [$key => $value];

        $this->setRequestParameters($newRequestParameters);
    }
}
