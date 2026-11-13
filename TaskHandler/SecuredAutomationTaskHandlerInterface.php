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

namespace Sulu\Bundle\AutomationBundle\TaskHandler;

/**
 * Names the permission a user needs on the entity to schedule the task and to have it run.
 */
interface SecuredAutomationTaskHandlerInterface extends AutomationTaskHandlerInterface
{
    /**
     * One of the {@see \Sulu\Component\Security\Authorization\PermissionTypes}.
     */
    public function getPermissionType(): string;
}
