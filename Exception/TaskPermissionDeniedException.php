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

namespace Sulu\Bundle\AutomationBundle\Exception;

/**
 * Will be thrown when the user of a task is not allowed to publish or unpublish its entity anymore.
 */
class TaskPermissionDeniedException extends \RuntimeException
{
}
