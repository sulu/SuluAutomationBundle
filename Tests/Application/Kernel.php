<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tests\Application;

use Sulu\Bundle\AutomationBundle\SuluAutomationBundle;
use Sulu\Bundle\TestBundle\Kernel\SuluTestKernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Task\TaskBundle\TaskBundle;

/**
 * Test kernel.
 */
class Kernel extends SuluTestKernel
{
    public function registerBundles()
    {
        $bundles = parent::registerBundles();

        $bundles[] = new TaskBundle();
        $bundles[] = new SuluAutomationBundle();

        return $bundles;
    }

    /**
     * {@inheritdoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        parent::registerContainerConfiguration($loader);

        $context = $this->getContext();
        $loader->load(__DIR__ . '/config/config.yml');
    }
}
