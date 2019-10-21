<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tests\Handler;

use Sulu\Bundle\AutomationBundle\TaskHandler\AutomationTaskHandlerInterface;
use Sulu\Bundle\AutomationBundle\TaskHandler\TaskHandlerConfiguration;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Represents a test-handler.
 */
class SecondHandler implements AutomationTaskHandlerInterface
{
    const TITLE = 'sulu_automation.second_handler';

    /**
     * {@inheritdoc}
     */
    public function configureOptionsResolver(OptionsResolver $optionsResolver): OptionsResolver
    {
        return $optionsResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $entityClass): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(): TaskHandlerConfiguration
    {
        return TaskHandlerConfiguration::create(self::TITLE);
    }

    /**
     * {@inheritdoc}
     */
    public function handle($workload)
    {
        // do nothing
    }
}
