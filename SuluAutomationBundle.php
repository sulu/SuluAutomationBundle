<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle;

use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskInterface;
use Sulu\Bundle\PersistenceBundle\PersistenceBundleTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Entry point for this bundle.
 */
class SuluAutomationBundle extends Bundle
{
    use PersistenceBundleTrait;

    public function build(ContainerBuilder $container): void
    {
        $this->buildPersistence(
            [
                TaskInterface::class => 'sulu.model.task.class',
            ],
            $container
        );
    }
}
