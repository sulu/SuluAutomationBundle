<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Entity;

use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskInterface;
use Sulu\Component\Persistence\Model\AuditableTrait;

/**
 * Represents a task-entity.
 */
class Task implements TaskInterface
{
    const RESOURCE_KEY = 'tasks';

    use AuditableTrait;

    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $handlerClass;

    /**
     * @var \DateTime
     */
    private $schedule;

    /**
     * @var string
     */
    private $locale;

    /**
     * @var string
     */
    private $entityClass;

    /**
     * @var string
     */
    private $entityId;

    /**
     * @var string
     */
    private $taskId;

    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $scheme;

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function setId(string $id): TaskInterface
    {
        $this->id = $id;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getHandlerClass(): string
    {
        return $this->handlerClass;
    }

    /**
     * Set task.
     *
     * @param string $handlerClass
     *
     * @return Task
     */
    public function setHandlerClass(string $handlerClass): self
    {
        $this->handlerClass = $handlerClass;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getSchedule(): \DateTime
    {
        return $this->schedule;
    }

    /**
     * Set schedule.
     *
     * @param \DateTime $schedule
     *
     * @return TaskInterface
     */
    public function setSchedule(\DateTime $schedule): TaskInterface
    {
        $this->schedule = $schedule;

        return $this;
    }

    /**
     * Returns locale.
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Set locale.
     *
     * @param string $locale
     *
     * @return Task
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * Set entity-class.
     *
     * @param string $entityClass
     *
     * @return Task
     */
    public function setEntityClass(string $entityClass): self
    {
        $this->entityClass = $entityClass;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getEntityId(): string
    {
        return $this->entityId;
    }

    /**
     * Set entity-id.
     *
     * @param string $entityId
     *
     * @return Task
     */
    public function setEntityId(string $entityId): self
    {
        $this->entityId = $entityId;

        return $this;
    }

    /**
     * Returns taskId.
     *
     * @return string
     */
    public function getTaskId(): string
    {
        return $this->taskId;
    }

    /**
     * Set taskId.
     *
     * @param string $taskId
     *
     * @return TaskInterface
     */
    public function setTaskId(string $taskId): TaskInterface
    {
        $this->taskId = $taskId;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Set host.
     *
     * @param string $host
     *
     * @return Task
     */
    public function setHost(string $host): self
    {
        $this->host = $host;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * Set scheme.
     *
     * @param string $scheme
     *
     * @return Task
     */
    public function setScheme(string $scheme): self
    {
        $this->scheme = $scheme;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatorFullName(): string
    {
        $creator = $this->getCreator();
        if (!$creator) {
            return '';
        }

        return $creator->getFullName();
    }

    /**
     * {@inheritdoc}
     */
    public function getChangerFullName(): string
    {
        $changer = $this->getChanger();
        if (!$changer) {
            return '';
        }

        return $changer->getFullName();
    }

    public function getTime(): string
    {
        return $this->getSchedule()->format('H:i:s');
    }

    public function getDate(): string
    {
        return $this->getSchedule()->format('Y-m-d');
    }
}
