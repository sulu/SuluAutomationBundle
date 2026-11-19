<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\RequestForPublishing\Event;

use Sulu\Bundle\ActivityBundle\Domain\Event\DomainEvent;
use Sulu\Content\Domain\Model\WorkflowTransitionRequest\WorkflowTransitionRequest;

/**
 * The publish of the content was scheduled for the date its approved request names, so the activity log and
 * anything listening to domain events, like a notifier, can tell who is waiting for what.
 */
class PublishAtScheduledEvent extends DomainEvent
{
    public function __construct(
        private readonly WorkflowTransitionRequest $request,
        private readonly \DateTimeImmutable $publishAt,
        private readonly string $taskId,
        private readonly ?string $resourceTitle = null,
        private readonly ?string $resourceWebspaceKey = null,
        private readonly ?string $resourceSecurityContext = null,
    ) {
        parent::__construct();
    }

    public function getEventType(): string
    {
        return 'publish_scheduled';
    }

    public function getEventContext(): array
    {
        return [
            'publishAt' => $this->publishAt->format(\DATE_ATOM),
            'taskId' => $this->taskId,
            'workflowTransitionRequestId' => $this->request->getId(),
        ];
    }

    public function getResourceKey(): string
    {
        return $this->request->getResourceKey();
    }

    public function getResourceId(): string
    {
        return $this->request->getResourceId();
    }

    public function getResourceLocale(): ?string
    {
        return $this->request->getLocale();
    }

    public function getResourceTitle(): ?string
    {
        return $this->resourceTitle;
    }

    public function getResourceWebspaceKey(): ?string
    {
        return $this->resourceWebspaceKey;
    }

    public function getResourceSecurityContext(): ?string
    {
        return $this->resourceSecurityContext;
    }

    public function getPublishAt(): \DateTimeImmutable
    {
        return $this->publishAt;
    }

    public function getRequest(): WorkflowTransitionRequest
    {
        return $this->request;
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }
}
