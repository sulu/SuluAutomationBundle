<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tasks\Model;

use Sulu\Component\Persistence\Model\AuditableInterface;

/**
 * Interface for tasks it contains functions which are necessary for managing tasks.
 */
interface TaskInterface extends AuditableInterface
{
    /**
     * Returns id.
     */
    public function getId(): string;

    /**
     * Set id.
     *
     * @return TaskInterface
     */
    public function setId(string $id): self;

    /**
     * Returns task.
     */
    public function getHandlerClass(): string;

    /**
     * Returns schedule.
     */
    public function getSchedule(): \DateTime;

    /**
     * @return TaskInterface
     */
    public function setSchedule(\DateTime $schedule): self;

    /**
     * Returns locale.
     */
    public function getLocale(): string;

    /**
     * Returns entity-class.
     */
    public function getEntityClass(): string;

    /**
     * Returns entity-id.
     */
    public function getEntityId(): string;

    /**
     * Returns taskId.
     */
    public function getTaskId(): string;

    /**
     * Set taskId.
     *
     * @return TaskInterface
     */
    public function setTaskId(string $taskId): self;

    /**
     * Returns host.
     */
    public function getHost(): string;

    /**
     * Returns scheme.
     */
    public function getScheme(): string;

    /**
     * Returns creator full-name.
     */
    public function getCreatorFullName(): string;

    /**
     * Returns creator full-name.
     */
    public function getChangerFullName(): string;
}
