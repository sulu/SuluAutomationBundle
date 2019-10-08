<?php


namespace Sulu\Bundle\AutomationBundle\Admin\View;


use Sulu\Bundle\AdminBundle\Admin\View\FormOverlayListViewBuilderInterface;

interface AutomationViewBuilderInterface extends FormOverlayListViewBuilderInterface
{
    public function setEntityClass(string $entityClass): AutomationViewBuilderInterface;

    public function setEntityId(string $entityId): AutomationViewBuilderInterface;

    public function setHandlerClass(string $handlerClass): AutomationViewBuilderInterface;

    public function addHandlerClass(string $handlerClass): AutomationViewBuilderInterface;
}
