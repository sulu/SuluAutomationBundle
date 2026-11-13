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

namespace Sulu\Bundle\AutomationBundle\Tests\Unit\Admin\View;

use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AutomationBundle\Admin\View\AutomationViewBuilderFactory;

class AutomationViewBuilderFactoryTest extends TestCase
{
    public function testToolbarActionsAreAList(): void
    {
        $factory = new AutomationViewBuilderFactory(new ViewBuilderFactory());

        foreach ([true, false] as $addable) {
            $view = $factory->createTaskListViewBuilder('view', '/automation', 'Entity', $addable)->getView();

            $actions = $view->getOption('toolbarActions');
            // a list with gaps would be serialized as an object, which the admin cannot iterate
            $this->assertSame(\array_keys($actions), $actions ? \range(0, \count($actions) - 1) : []);
            $this->assertSame(
                $addable ? ['sulu_admin.add', 'sulu_admin.delete'] : ['sulu_admin.delete'],
                \array_map(static fn ($action) => $action->getType(), $actions),
            );
        }
    }
}
