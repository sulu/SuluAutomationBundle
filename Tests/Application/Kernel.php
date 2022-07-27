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
    public function registerBundles(): iterable
    {
        $bundles = parent::registerBundles();

        $bundles[] = new TaskBundle();
        $bundles[] = new SuluAutomationBundle();

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        $context = $this->getContext();
        $loader->load(__DIR__ . '/config/config.yml');
    }

    protected function getKernelParameters(): array
    {
        $parameters = parent::getKernelParameters();

        $gedmoReflection = new \ReflectionClass(\Gedmo\Exception::class);
        $parameters['gedmo_directory'] = \dirname($gedmoReflection->getFileName());

        return $parameters;
    }
}
