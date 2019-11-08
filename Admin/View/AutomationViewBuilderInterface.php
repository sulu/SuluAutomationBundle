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

use Sulu\Bundle\AdminBundle\Admin\View\FormOverlayListViewBuilderInterface;

interface AutomationViewBuilderInterface extends FormOverlayListViewBuilderInterface
{
    public function setEntityClass(string $entityClass): self;
}
