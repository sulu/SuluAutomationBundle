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

interface AutomationViewBuilderFactoryInterface
{
    /**
     * The fourth argument `bool $addable = true` removes the add action of the list if it is false, because the user
     * may use no handler. It becomes part of the signature in the next minor release, calling the method without it
     * is deprecated.
     */
    public function createTaskListViewBuilder(string $name, string $path, string $entityClass /* , bool $addable = true */): FormOverlayListViewBuilderInterface;
}
