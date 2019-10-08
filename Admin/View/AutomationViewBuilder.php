<?php

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
            ->addRouterAttributesToFormRequest(['id' => 'entity-id'])
            ->addRouterAttributesToListRequest(['id' => 'entity-id']);
    }

    public function setEntityClass(string $entityClass): AutomationViewBuilderInterface
    {
        $this->addRequestParameter('entity-class', $entityClass);

        return $this;
    }

    public function setEntityId(string $entityId): AutomationViewBuilderInterface
    {
        $this->addRequestParameter('entity-id', $entityId);

        return $this;
    }

    public function setHandlerClass(string $handlerClass): AutomationViewBuilderInterface
    {
        $this->addRequestParameter('handler-class', $handlerClass);

        return $this;
    }

    public function addHandlerClass(string $handlerClass): AutomationViewBuilderInterface
    {
        $handlerClasses = $this->getView()->getOption('requestParameters')['handler-classes'] ?? null;
        if ($handlerClasses) {
            $handlerClasses .= ',' . $handlerClass;
        } else {
            $handlerClasses = $handlerClass;
        }
        $this->addRequestParameter('handler-classes', $handlerClasses);

        return $this;
    }

    private function addRequestParameter(string $key, string $value)
    {
        $oldRequestParameters = $this->getView()->getOption('requestParameters');
        $newRequestParameters = $oldRequestParameters
            ? array_merge($oldRequestParameters, [$key => $value])
            : [$key => $value];

        $this->setRequestParameters($newRequestParameters);
    }

}
