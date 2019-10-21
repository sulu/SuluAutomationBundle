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
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Set id.
     *
     * @param string $id
     *
     * @return TaskInterface
     */
    public function setId(string $id): self;

    /**
     * Returns task.
     *
     * @return string
     */
    public function getHandlerClass(): string;

    /**
     * Returns schedule.
     *
     * @return \DateTime
     */
    public function getSchedule(): \DateTime;

    /**
     * Returns locale.
     *
     * @return string
     */
    public function getLocale(): string;

    /**
     * Returns entity-class.
     *
     * @return string
     */
    public function getEntityClass(): string;

    /**
     * Returns entity-id.
     *
     * @return string
     */
    public function getEntityId(): string;

    /**
     * Returns taskId.
     *
     * @return string
     */
    public function getTaskId(): string;

    /**
     * Set taskId.
     *
     * @param string $taskId
     *
     * @return TaskInterface
     */
    public function setTaskId(string $taskId): self;

    /**
     * Returns host.
     *
     * @return string
     */
    public function getHost(): string;

    /**
     * Returns scheme.
     *
     * @return string
     */
    public function getScheme(): string;

    /**
     * Returns creator full-name.
     *
     * @return string
     */
    public function getCreatorFullName(): string;

    /**
     * Returns creator full-name.
     *
     * @return string
     */
    public function getChangerFullName(): string;
}
