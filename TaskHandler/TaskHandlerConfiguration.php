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

/**
 * Contains configuration for task-handler.
 */
class TaskHandlerConfiguration
{
    /**
     * Create a new configuration.
     *
     * @return TaskHandlerConfiguration
     */
    public static function create(string $title)
    {
        return new self($title);
    }

    /**
     * @var string
     */
    private $title;

    private function __construct(string $title)
    {
        $this->title = $title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
