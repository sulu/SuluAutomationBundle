<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\RequestForPublishing;

use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\AutomationBundle\Entity\Task;
use Sulu\Bundle\AutomationBundle\RequestForPublishing\Event\PublishAtScheduledEvent;
use Sulu\Bundle\AutomationBundle\Security\TaskPermissionChecker;
use Sulu\Bundle\AutomationBundle\Tasks\Manager\TaskManagerInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Content\Application\RequestWorkflow\WorkflowTransitionRequestStatusResolverInterface;
use Sulu\Content\Application\WorkflowTransitionRequest\Action\WorkflowTransitionRequestActionInterface;
use Sulu\Content\Domain\Model\WorkflowTransitionRequest\WorkflowTransitionRequest;
use Sulu\Content\Domain\Value\WorkflowTransitionRequest\WorkflowTransitionRequestStatusEnum;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Publishes an approved request at the date its form names, by scheduling a publish task for the content. The
 * {@see PublishAtScheduler} does that on approval, this action is the way to do it by hand.
 *
 * Not meant to be extended: the scheduler and the tests double it.
 *
 * The task is cancelled again when the request is cancelled or published by hand, see {@see PublishAtTaskCanceller}.
 */
class PublishAtAction implements WorkflowTransitionRequestActionInterface
{
    public const KEY = 'publish_at';

    public const DATA_KEY = 'publishAt';

    public const TOGGLE_KEY = 'publishAutomatically';

    public function __construct(
        private readonly PublishAtTargets $targets,
        private readonly TaskManagerInterface $taskManager,
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly TaskPermissionChecker $permissionChecker,
        private readonly WorkflowTransitionRequestStatusResolverInterface $statusResolver,
        private readonly RequestStack $requestStack,
        private readonly DomainEventCollectorInterface $domainEventCollector,
        private readonly ?\DateTimeImmutable $now = null,
    ) {
    }

    public static function getKey(): string
    {
        return self::KEY;
    }

    public function getLabel(): string
    {
        return 'sulu_automation.publish_on';
    }

    public function getLabelParameters(WorkflowTransitionRequest $request): array
    {
        $publishAt = $this->getPublishAt($request);

        return null === $publishAt ? [] : ['date' => $publishAt->format('d.m.Y H:i')];
    }

    /**
     * The task publishes without a token later, so the permission to do that is the one of the scheduling user.
     */
    public function getPermission(): string
    {
        return PermissionTypes::LIVE;
    }

    /**
     * With a date ahead the content is published at that date and not before. Once the date has passed, the action
     * refuses it, so the plain publish has to be possible again.
     */
    public function replacesPublish(WorkflowTransitionRequest $request): bool
    {
        if (!$this->targets->supports($request->getResourceKey())) {
            return false;
        }

        $publishAt = $this->getPublishAt($request);

        return null !== $publishAt && $publishAt > ($this->now ?? new \DateTimeImmutable());
    }

    /**
     * Once the task waits, the banner of the form tells it, because there is nothing left for anyone to click.
     */
    public function getStatusMessage(WorkflowTransitionRequest $request): ?array
    {
        $publishAt = $this->getPublishAt($request);
        if (null === $publishAt || !$this->targets->supports($request->getResourceKey())) {
            return null;
        }

        if (WorkflowTransitionRequestStatusEnum::APPROVED !== $this->statusResolver->resolve($request)) {
            return null;
        }

        if (0 === $this->countPendingTasks($request)) {
            return null;
        }

        return [
            'message' => 'sulu_automation.publish_scheduled',
            'parameters' => ['date' => $publishAt->format('d.m.Y H:i')],
        ];
    }

    public function isAvailable(WorkflowTransitionRequest $request): bool
    {
        if (!$this->targets->supports($request->getResourceKey())) {
            return false;
        }

        if (WorkflowTransitionRequestStatusEnum::APPROVED !== $this->statusResolver->resolve($request)) {
            return false;
        }

        if (null === $this->getPublishAt($request)) {
            return false;
        }

        return 0 === $this->countPendingTasks($request);
    }

    private function countPendingTasks(WorkflowTransitionRequest $request): int
    {
        return $this->taskRepository->countPendingTasks(
            $this->targets->getEntityClass($request->getResourceKey()),
            $request->getResourceId(),
            $request->getLocale(),
        );
    }

    public function execute(WorkflowTransitionRequest $request): void
    {
        $publishAt = $this->getPublishAt($request);
        if (null === $publishAt) {
            throw new BadRequestHttpException('The request names no date to publish at.');
        }

        if ($publishAt <= ($this->now ?? new \DateTimeImmutable())) {
            throw new BadRequestHttpException('The date to publish at lies in the past.');
        }

        $resourceKey = $request->getResourceKey();
        $httpRequest = $this->requestStack->getCurrentRequest();

        $task = new Task();
        $task->setHandlerClass($this->targets->getHandlerClass($resourceKey));
        $task->setEntityClass($this->targets->getEntityClass($resourceKey));
        $task->setEntityId($request->getResourceId());
        $task->setLocale($request->getLocale());
        $task->setSchedule($publishAt);
        $task->setScheme($httpRequest?->getScheme() ?? 'https');
        $task->setHost($httpRequest?->getHost() ?? 'localhost');

        // the same check as the admin applies when a task is scheduled by hand
        $this->permissionChecker->checkCurrentUser(
            $task->getHandlerClass(),
            $task->getEntityClass(),
            $task->getEntityId(),
            $task->getLocale(),
        );

        $task = $this->taskManager->create($task);

        $this->domainEventCollector->collect(new PublishAtScheduledEvent($request, $publishAt, (string) $task->getId()));
    }

    /**
     * The form keeps the value of a field that is hidden again, so the date counts only while the toggle is on.
     * A date without a zone is read in the default zone of the server, like the other dates of the admin.
     */
    private function getPublishAt(WorkflowTransitionRequest $request): ?\DateTimeImmutable
    {
        if (true !== ($request->getData()[self::TOGGLE_KEY] ?? null)) {
            return null;
        }

        $value = $request->getData()[self::DATA_KEY] ?? null;
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        return \date_create_immutable($value) ?: null;
    }
}
