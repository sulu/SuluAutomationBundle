<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Events;

/**
 * Event args for task-remove event.
 */
class TaskRemoveEvent extends TaskEvent
{
    use CancelTrait;
}
