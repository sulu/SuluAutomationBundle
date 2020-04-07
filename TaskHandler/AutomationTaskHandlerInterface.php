<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\TaskHandler;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Task\Handler\TaskHandlerInterface;

/**
 * Interface for automation task-handler.
 */
interface AutomationTaskHandlerInterface extends TaskHandlerInterface
{
    /**
     * Configures options-resolver to validate workload.
     */
    public function configureOptionsResolver(OptionsResolver $optionsResolver): OptionsResolver;

    /**
     * Returns true if handler supports given class.
     */
    public function supports(string $entityClass): bool;

    /**
     * Returns configuration for this task-handler.
     */
    public function getConfiguration(): TaskHandlerConfiguration;
}
