<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Exception;

/**
 * Will be thrown if task was not found.
 */
class TaskNotFoundException extends \Exception
{
    /**
     * @var string
     */
    private $id;

    public function __construct(string $id)
    {
        parent::__construct(\sprintf('Task with id "%s" was not found', $id));

        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
