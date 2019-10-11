<?php


namespace Sulu\Bundle\AutomationBundle\Admin\View;


use Sulu\Bundle\AdminBundle\Admin\View\FormOverlayListViewBuilderInterface;

interface AutomationViewBuilderInterface extends FormOverlayListViewBuilderInterface
{
    public function setEntityClass(string $entityClass): AutomationViewBuilderInterface;
}
